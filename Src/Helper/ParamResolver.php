<?php
/**
 * Parameter Resolver for either class or method during auto-wiring.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Helper;

use Closure;
use TypeError;
use ReflectionNamedType;
use ReflectionParameter;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Pool\Param;
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;
use TheWebSolver\Codegarage\Lib\Container\Pool\IndexStack;
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
		protected readonly IndexStack $result = new IndexStack(),
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

			$this->for( $param, result: $param === $result ? $this->from( $result ) : $result );
		}

		$this->pool->pull();

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
		$value = $this->app->getContextualFor( context: $type );

		try {
			return match ( true ) {
				$this->isApp( $type )     => $this->app,
				is_string( $value )       => $this->app->get( id: $value ),
				$value instanceof Closure => Unwrap::andInvoke( $value, $this->app ),
				default                   => $this->app->get( id: $type )
			};
		} catch ( ContainerError $unresolvable ) {
			return static::defaultFrom( $param, error: $unresolvable );
		}
	}

	private function isApp( string $type ): bool {
		return ContainerInterface::class === $type || Container::class === $type;
	}

	protected function for( ReflectionParameter $param, mixed $result ): void {
		match ( true ) {
			$param->isVariadic() => $this->result->restackWith( newValue: $result, mergeArray: true ),
			default              => $this->result->set( value: $result )
		};
	}

	protected function from( ReflectionParameter $param ): mixed {
		$type = Unwrap::paramTypeFrom( reflection: $param );

		return $type ? $this->fromTyped( $param, $type ) : $this->fromUntypedOrBuiltin( $param );
	}

	protected function fromEvent( ReflectionParameter $param ): mixed {
		if ( ! ( $type = $param->getType() ) instanceof ReflectionNamedType ) {
			return null;
		}

		$id = Stack::keyFrom( id: $this->app->getEntryFrom( $type->getName() ), name: $param->getName() );

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

	private function maybeAddEventListenerFromAttributeOf( ReflectionParameter $param, string $id ): void {
		if ( ! $this->dispatcher instanceof ListenerRegistry ) {
			return;
		}

		if ( empty( $attributes = $param->getAttributes( name: ListenTo::class ) ) ) {
			return;
		}

		$attribute = $attributes[0]->newInstance();

		if ( ! is_callable( $attribute->listener ) ) {
			throw new TypeError(
				sprintf(
					'Event Listener passed to "%s" Attribute must be a callable string or an array.',
					ListenTo::class
				)
			);
		}

		// We'll push Event Listener supplied as the Parameter Attribute as a last Listener.
		// This is done so any user-defined Event Listener will be listened before it.
		// Or, in other case, user-defined Listener may halt this Event Listener.
		if ( $attribute->isFinal ) {
			$this->dispatcher->addListener( listener: ( $attribute->listener )( ... ), forEntry: $id );

			return;
		}

		$listeners = $this->dispatcher->getListeners( forEntry: $id );

		$this->dispatcher->reset( collectionId: $id );

		// We'll push Event Listener supplied as the Parameter Attribute as first Listener.
		// This is done so any user-defined Event Listener will take precedence over it.
		// Or, in other case, this Event Listener may halt the user-defined Listeners.
		foreach ( array( ( $attribute->listener )( ... ), ...$listeners ) as $listener ) {
			$this->dispatcher->addListener( $listener, forEntry: $id );
		}
	}

	protected static function defaultFrom( ReflectionParameter $param, ContainerError $error ): mixed {
		return match ( true ) {
			$param->isDefaultValueAvailable() => $param->getDefaultValue(),
			$param->isVariadic()              => array(),
			default                           => throw $error,
		};
	}
}
