<?php
/**
 * Handles events dispatched after build event.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Event\Manager;

use Closure;
use LogicException;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use Psr\EventDispatcher\EventDispatcherInterface;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Helper\Unwrap;
use TheWebSolver\Codegarage\Lib\Container\Pool\Artefact;
use TheWebSolver\Codegarage\Lib\Container\Event\EventType;
use TheWebSolver\Codegarage\Lib\Container\Error\ContainerError;
use TheWebSolver\Codegarage\Lib\Container\Event\AfterBuildEvent;
use TheWebSolver\Codegarage\Lib\Container\Attribute\DecorateWith;
use TheWebSolver\Codegarage\Lib\Container\Error\BadResolverArgument;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\ListenerRegistry;

class AfterBuildHandler {
	/** Placeholders: 1: Decorating classname 2: debug type of resolved value */
	public const INVALID_TYPE_HINT_OR_NOT_FIRST_PARAM = 'Decorating class "%s" has invalid type-hint or not accepting the resolved object as first parameter when decorating "%2$s".';
	/** Placeholder: %s: Decorating classname */
	public const ZERO_PARAM_IN_CONSTRUCTOR = 'Decorating class "%s" does not have any parameter in its constructor.';

	/** @var (EventDispatcherInterface&ListenerRegistry<AfterBuildEvent>) */
	public EventDispatcherInterface&ListenerRegistry $eventDispatcher;
	private ?string $currentDecoratorClass = null;
	private mixed $resolved;

	public function __construct( private readonly Container $app, private readonly string $entry ) {
		if ( $dispatcher = $app->getEventManager()->getDispatcher( EventType::AfterBuild ) ) {
			$this->eventDispatcher = $dispatcher;
		}
	}

	/**
	 * @throws BadResolverArgument When `$resolved` Parameter could not be determined in decorator class.
	 * @throws ContainerError      When decorator class is not a valid class-string or not instantiable.
	 */
	// phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.WrongNumber -- Actual number is vague.
	public static function handleWith(
		Container $app,
		string $entry,
		mixed $resolved,
		Artefact $artefact,
		?ReflectionClass $reflector
	): mixed {
		$handler = new self( $app, $entry );

		try {
			$artefact->push( $entry );

			$resolved = $handler->withListenerFromAttributeOf( $reflector )->handle( $resolved );

			$artefact->pull();

			return $resolved;
		} catch ( ReflectionException | LogicException $exception ) {
			// "BadResolverArgument" is not caught. It is not part of the container error.
			throw ContainerError::whenResolving( $handler->getLastDecorator() ?? $entry, $exception, $artefact );
		}
	}

	public function getLastDecorator(): ?string {
		return $this->currentDecoratorClass;
	}

	public function withListenerFromAttributeOf( ?ReflectionClass $reflection ): self {
		if ( ! $this->hasDispatcher() || empty( $attrs = $reflection?->getAttributes( DecorateWith::class ) ) ) {
			return $this;
		}

		$priorities = $this->eventDispatcher->getPriorities();
		$attribute  = $attrs[0]->newInstance();
		$priority   = $attribute->isFinal ? $priorities['high'] + 1 : $priorities['low'] - 1;

		$this->eventDispatcher->addListener( ( $attribute->listener )( ... ), $this->entry, $priority );

		return $this;
	}

	/**
	 * @throws BadResolverArgument When `$resolved` Parameter could not be determined in decorator class.
	 * @throws ReflectionException When decorator class is provided but it is not a valid class-string.
	 * @throws LogicException      When decorator class is provided but it cannot be instantiated.
	 */
	public function handle( mixed $resolved ): mixed {
		if ( ! $this->hasDispatcher() ) {
			return $resolved;
		}

		$event = $this->eventDispatcher->dispatch( event: new AfterBuildEvent( $this->entry ) );

		if ( ! $event instanceof AfterBuildEvent ) {
			return $resolved;
		}

		$this->resolved = $resolved;

		foreach ( $event->getDecorators()->get( $this->entry ) ?? array() as $decorator ) {
			$this->resolved = $this->decorateWith( $decorator );
		}

		foreach ( $event->getUpdaters()->get( $this->entry ) ?? array() as $update ) {
			$update( $this->resolved, $this->app );
		}

		return $this->resolved;
	}

	private function decorateWith( string|Closure $decorator ): mixed {
		if ( $decorator instanceof Closure ) {
			return $decorator( $this->resolved, $this->app );
		}

		$this->currentDecoratorClass = $decorator;

		$entry      = $this->app->getEntryFrom( $decorator );
		$reflection = Unwrap::classReflection( $entry );
		$args       = array( $this->getDecoratorParamFrom( $reflection )->getName() => $this->resolved );

		return $this->app->resolve( $decorator, with: $args, dispatch: true, reflector: $reflection );
	}

	private function getDecoratorParamFrom( ReflectionClass $reflection ): ReflectionParameter {
		if ( ! $param = $this->firstParameterIn( decorator: $reflection ) ) {
			$this->throwBadArgument( self::ZERO_PARAM_IN_CONSTRUCTOR );
		}

		return $this->isValidParameterInDecorator( $param )
			? $param
			: $this->throwBadArgument( self::INVALID_TYPE_HINT_OR_NOT_FIRST_PARAM, get_debug_type( $this->resolved ) );
	}

	private function hasDispatcher(): bool {
		return isset( $this->eventDispatcher );
	}

	private function firstParameterIn( ReflectionClass $decorator ): ?ReflectionParameter {
		return $decorator->getConstructor()?->getParameters()[0] ?? null;
	}

	private function isValidParameterInDecorator( ReflectionParameter $param ): bool {
		return ( $type = Unwrap::paramTypeFrom( reflection: $param ) ) && $this->resolved instanceof $type;
	}

	private function throwBadArgument( string $msg, string $type = null ): never {
		throw new BadResolverArgument( sprintf( $msg, $this->currentDecoratorClass, $type ) );
	}
}
