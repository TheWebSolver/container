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
	public function __construct( private readonly Container $app, private readonly Artefact $artefact ) {}

	public function handle( string $id, mixed $resolved, ?ReflectionClass $reflector ): mixed {
		/** @var (EventDispatcherInterface&ListenerRegistry<AfterBuildEvent>)|null */
		$eventDispatcher = $this->app->getEventManager()->getDispatcher( EventType::AfterBuild );

		if ( ! $eventDispatcher ) {
			return $resolved;
		}

		if ( $reflector && ! empty( $attributes = $reflector->getAttributes( DecorateWith::class ) ) ) {
			[ $low, $high ] = $eventDispatcher->getPriorities();
			$attribute      = $attributes[0]->newInstance();
			$priority       = $attribute->isFinal ? $high + 1 : $low - 1;

			$eventDispatcher->addListener( ( $attribute->listener )( ... ), forEntry: $id, priority: $priority );
		}

		/** @var ?AfterBuildEvent */
		$event = $eventDispatcher->dispatch( event: new AfterBuildEvent( entry: $id ) );

		if ( ! $event ) {
			return $resolved;
		}

		foreach ( $event->getDecorators()[ $id ] ?? array() as $decorator ) {
			$resolved = $this->decorate( $resolved, $decorator );
		}

		foreach ( $event->getUpdaters()[ $id ] ?? array() as $updater ) {
			$updater( $resolved, $this->app );
		}

		return $resolved;
	}

	// phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch
	/**
	 * @param class-string|(Closure(mixed, Container): mixed) $decorator
	 * @throws ContainerError When non-resolvable or non-instantiable $decorator given.
	 */
	// phpcs:enable
	protected function decorate( mixed $resolved, string|Closure $decorator ): mixed {
		if ( $decorator instanceof Closure ) {
			return $decorator( $resolved, $this->app );
		}

		$entry = $this->app->getEntryFrom( $decorator );

		try {
			$reflection = Unwrap::classReflection( $entry );
		} catch ( ReflectionException | LogicException $e ) {
			throw ContainerError::whenResolving( $entry, exception: $e, artefact: $this->artefact );
		}

		$args = array( $this->getDecoratorParamFrom( $reflection, $resolved )->getName() => $resolved );

		return $this->app->resolve( $decorator, with: $args, dispatch: true, reflector: $reflection );
	}

	/** @throws BadResolverArgument When $resolved value Parameter could not be determined. */
	protected function getDecoratorParamFrom( ReflectionClass $reflection, mixed $resolved ): ReflectionParameter {
		$params = $reflection->getConstructor()?->getParameters();
		$class  = $reflection->getName();

		if ( null === $params || ! ( $param = ( $params[0] ?? null ) ) ) {
			throw new BadResolverArgument(
				sprintf( 'Decorating class "%s" does not have any parameters in its constructor.', $class )
			);
		}

		$isResolvedObject = ( $type = Unwrap::paramTypeFrom( reflection: $param ) )
			&& is_object( $resolved )
			&& is_a( $resolved, class: $type );

		return $isResolvedObject ? $param : throw new BadResolverArgument(
			sprintf(
				'Decorating class "%s" has invalid type-hint or not accepting the resolved object as first parameter.',
				$class
			)
		);
	}
}
