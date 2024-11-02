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
		protected readonly Param $pool,
		protected readonly ?EventDispatcherInterface $dispatcher,
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
				$this->pool->has( $param->name )               => $this->pool->getFrom( $param->name ),
				null !== ( $val = $this->fromEvent( $param ) ) => $val,
				default                                        => $param
			};

			$this->result->set( key: $param->getName(), value:  $param === $result ? $this->from( $param ) : $result );
		}

		return $this->result->getItems();
	}

	/** @throws ContainerError When resolving dependencies fails. */
	public function fromUntypedOrBuiltin( ReflectionParameter $param ): mixed {
		$value = $this->app->getContextualFor( context: "\${$param->getName()}" );

		return null === $value
			? static::defaultFrom( $param, error: BadResolverArgument::noParam( ref: $param ) )
			: Unwrap::andInvoke( $value, $this->app );
	}

	/** @throws ContainerError When resolving dependencies fails. */
	public function fromTyped( ReflectionParameter $param, string $type ): mixed {
		$context = $this->app->getContextualFor( context: $type );

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

	protected function for( ReflectionParameter $param, mixed $result ): void {
		match ( true ) {
			$param->isVariadic() => $this->result->set( key: $param->getName(), value: $result ),
			default              => $this->result->set( key: $param->getName(), value: $result )
		};
	}

	protected function from( ReflectionParameter $param ): mixed {
		$type = Unwrap::paramTypeFrom( reflection: $param );

		return $type ? $this->fromTyped( $param, $type ) : $this->fromUntypedOrBuiltin( $param );
	}

	protected function fromEvent( ReflectionParameter $param ): mixed {
		if ( ! $type = Unwrap::paramTypeFrom( reflection: $param, checkBuiltIn: false ) ) {
			return null;
		}

		$id = Stack::keyFrom( id: $type, name: $param->getName() );

		$this->maybeAddEventListenerFromAttributeOf( $param, $id );

		// We'll resolve the instance directly from the app binding instead of using "Container::get()" method.
		// This is done so that Event Dispatcher is bypassed which might otherwise trigger an infinite loop.
		if ( $this->app->isInstance( $id ) ) {
			return $this->app->getBinding( $id )?->concrete;
		}

		$event = $this->dispatcher?->dispatch( new BuildingEvent( $this->app, paramTypeWithName: $id ) );

		// We'll confirm whether dispatched event has been returned by the Event Dispatcher.
		// This is done as a type check as well as allows making the resolver testable.
		if ( ! $event instanceof BuildingEvent ) {
			return null;
		}

		$binding = $event->getBinding();

		if ( $binding?->isInstance() && is_object( $instance = $binding->concrete ) ) {
			$this->app->setInstance( $id, $instance );
		}

		return $binding?->concrete;
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

	private function maybeAddEventListenerFromAttributeOf( ReflectionParameter $param, string $id ): void {
		if ( ! $this->dispatcher instanceof ListenerRegistry ) {
			return;
		}

		if ( empty( $attributes = $param->getAttributes( name: ListenTo::class ) ) ) {
			return;
		}

		/** @var ListenerRegistry<BuildingEvent> */
		$dispatcher         = $this->dispatcher;
		$attribute          = $attributes[0]->newInstance();
		[$lowest, $highest] = $dispatcher->getPriorities();

		/*
		| Prioritize the Event Listener based on whether it should be treated as final or not.
		|
		| When it is set as final:
		|  - user-defined Event Listeners will be listened before it.
		|  - user-defined Event Listeners may halt this Event Listener.
		|
		| When it is not set as final:
		|  - it will be listened before user-defined Event Listeners.
		|  - it may halt the user-defined Event Listeners.
		*/
		$priority = $attribute->isFinal ? $highest + 1 : $lowest - 1;

		$dispatcher->addListener( listener: ( $attribute->listener )( ... ), forEntry: $id, priority: $priority );
	}
}
