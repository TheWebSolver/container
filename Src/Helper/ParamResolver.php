<?php
/**
 * Parameter Resolver for either class or method during auto-wiring.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Helper;

use Closure;
use ReflectionParameter;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Pool\Param;
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;
use TheWebSolver\Codegarage\Lib\Container\Attribute\ListenTo;
use TheWebSolver\Codegarage\Lib\Container\Event\BuildingEvent;
use Psr\Container\ContainerExceptionInterface as ContainerError;
use TheWebSolver\Codegarage\Lib\Container\Error\BadResolverArgument;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\ListenerRegistry;

class ParamResolver {
	public function __construct(
		protected readonly Container $app,
		protected readonly Param $stack,
		protected (EventDispatcherInterface&ListenerRegistry)|null $dispatcher,
		protected readonly Stack $result = new Stack(),
	) {}

	/**
	 * @param ReflectionParameter[] $dependencies
	 * @return mixed[]
	 * @throws ContainerError When resolving dependencies fails.
	 */
	public function resolve( array $dependencies ): array {
		foreach ( $dependencies as $param ) {
			$result = match ( true ) {
				$this->stack->has( $param->name )              => $this->stack->get( $param->name ),
				null !== ( $val = $this->fromEvent( $param ) ) => $val,
				default                                        => $param
			};

			$this->result->set( key: $param->getName(), value:  $param === $result ? $this->from( $param ) : $result );
		}

		return $this->result->getItems();
	}

	/** @throws ContainerError When resolving dependencies fails. */
	public function fromUntypedOrBuiltin( ReflectionParameter $param ): mixed {
		$value = $this->app->getContextualFor( typeHintOrParamName: "\${$param->getName()}" );

		return null === $value
			? static::defaultFrom( $param, error: BadResolverArgument::noParam( ref: $param ) )
			: Unwrap::andInvoke( $value, $this->app );
	}

	/** @throws ContainerError When resolving dependencies fails. */
	public function fromTyped( ReflectionParameter $param, string $type ): mixed {
		$context = $this->app->getContextualFor( typeHintOrParamName: $type );

		try {
			return match ( true ) {
				$this->isApp( $type )       => $this->app,
				is_string( $context )       => $this->app->get( id: $context ),
				$context instanceof Closure => Unwrap::andInvoke( $context, $this->app ),
				default                     => $this->app->get( id: $type )
			};
		} catch ( ContainerError $unresolvable ) {
			return static::defaultFrom( $param, error: $unresolvable );
		}
	}

	protected function from( ReflectionParameter $param ): mixed {
		$type = Unwrap::paramTypeFrom( reflection: $param );

		return $type ? $this->fromTyped( $param, $type ) : $this->fromUntypedOrBuiltin( $param );
	}

	protected function fromEvent( ReflectionParameter $param ): mixed {
		if ( ! $type = Unwrap::paramTypeFrom( reflection: $param, checkBuiltIn: false ) ) {
			return null;
		}

		$entry = "{$type}:{$param->name}";

		$this->maybeAddEventListenerFromAttributeOf( $param, $entry );

		if ( ! $this->dispatcher?->hasListeners( forEntry: $entry ) ) {
			return null;
		}

		if ( $this->app->isInstance( $entry ) ) {
			return $this->app->get( $entry );
		}

		$event = $this->dispatcher->dispatch( new BuildingEvent( $this->app, paramTypeWithName: $entry ) );

		if ( ! $event instanceof BuildingEvent || ! ( $binding = $event->getBinding() ) ) {
			return null;
		}

		$material = $binding->material;

		if ( $binding->isInstance() ) {
			$this->app->setInstance( $entry, $this->ensureObject( $material, $type, $param->name, $entry ) );

			return $this->app->get( $entry );
		}

		return $material instanceof Closure ? $material() : $material;
	}

	protected static function defaultFrom( ReflectionParameter $param, ContainerError $error ): mixed {
		return match ( true ) {
			$param->isDefaultValueAvailable() => $param->getDefaultValue(),
			$param->isVariadic()              => array(),
			default                           => throw $error,
		};
	}

	private function isApp( string $type ): bool {
		return ContainerInterface::class === $type || Container::class === $type;
	}

	private function maybeAddEventListenerFromAttributeOf( ReflectionParameter $param, string $entry ): void {
		$scopeName     = $param->getDeclaringClass()?->getName() ?? $param->getDeclaringFunction()->getName();
		$listenerScope = "{$scopeName}:{$entry}";

		if (
			! $this->dispatcher instanceof ListenerRegistry
				|| $this->app->isListenerFetchedFrom( $listenerScope, ListenTo::class )
		) {
			return;
		}

		$this->app->setListenerFetchedFrom( $listenerScope, ListenTo::class );

		if ( empty( $attributes = $param->getAttributes( ListenTo::class ) ) ) {
			return;
		}

		/** @var ListenerRegistry<BuildingEvent> */
		$dispatcher = $this->dispatcher;
		$attribute  = $attributes[0]->newInstance();
		$priorities = $dispatcher->getPriorities();
		$priority   = $attribute->isFinal ? $priorities['high'] + 1 : $priorities['low'] - 1;

		$dispatcher->addListener( ( $attribute->listener )( ... ), $entry, $priority );
	}

	/**
	 * @return object
	 * @throws BadResolverArgument When param is not of value type.
	 */
	private function ensureObject( mixed $value, string $type, string $name, string $id ) {
		return $value instanceof $type ? $value : throw BadResolverArgument::forBuildingParam( $type, $name, $id );
	}
}
