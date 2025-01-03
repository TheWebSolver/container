<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Helper;

use Closure;
use ReflectionParameter;
use Psr\Container\ContainerInterface;
use TheWebSolver\Codegarage\Container\Container;
use TheWebSolver\Codegarage\Container\Pool\Stack;
use TheWebSolver\Codegarage\Container\Attribute\ListenTo;
use TheWebSolver\Codegarage\Container\Data\SharedBinding;
use TheWebSolver\Codegarage\Container\Event\BuildingEvent;
use TheWebSolver\Codegarage\Container\Traits\DependencySetter;
use Psr\Container\ContainerExceptionInterface as ContainerError;
use TheWebSolver\Codegarage\Container\Error\BadResolverArgument;
use TheWebSolver\Codegarage\Container\Interfaces\ListenerRegistry;
use TheWebSolver\Codegarage\Container\Traits\EventDispatcherSetter;

class ParamResolver {
	/** @use EventDispatcherSetter<BuildingEvent> */
	use EventDispatcherSetter, DependencySetter;

	public function __construct(
		protected readonly Container $app,
		protected readonly Stack $result = new Stack(),
	) {}

	/**
	 * @return mixed[]
	 * @throws ContainerError When resolving dependencies fails.
	 */
	public function resolve(): array {
		foreach ( $this->reflections as $param ) {
			$result = match ( true ) {
				isset( $this->dependencies[ $param->name ] )   => $this->dependencies[ $param->name ],
				( $val = $this->fromEvent( $param ) ) !== null => $val,
				default                                        => $param
			};

			$this->result->set( key: $param->name, value: $param === $result ? $this->from( $param ) : $result );
		}

		return $this->result->getItems();
	}

	/** @throws ContainerError When resolving dependencies fails. */
	public function fromUntypedOrBuiltin( ReflectionParameter $param ): mixed {
		$value = $this->app->getContextualFor( typeHintOrParamName: "\${$param->name}" );

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

		if ( ! $event instanceof BuildingEvent || ! ( $bound = $event->getBinding() ) ) {
			return null;
		}

		if ( ! $bound instanceof SharedBinding ) {
			return ( $value = $bound->material ) instanceof Closure ? $value() : $this->app->get( $value );
		}

		$this->app->setInstance( $entry, $this->ensureObject( $bound->material, $type, $param->name, $entry ) );

		return $this->app->get( $entry );
	}

	protected static function defaultFrom( ReflectionParameter $param, ContainerError $error ): mixed {
		return match ( true ) {
			$param->isDefaultValueAvailable() => $param->getDefaultValue(),
			$param->isVariadic()              => array(),
			default                           => throw $error,
		};
	}

	private function isApp( string $type ): bool {
		return ContainerInterface::class === $type
			|| Container::class === $type
			|| is_subclass_of( $type, Container::class );
	}

	private function maybeAddEventListenerFromAttributeOf( ReflectionParameter $param, string $entry ): void {
		if (
			! $this->dispatcher instanceof ListenerRegistry
				|| $this->app->isListenerFetchedFrom( $entry, ListenTo::class )
		) {
			return;
		}

		$this->app->setListenerFetchedFrom( $entry, ListenTo::class );

		if ( empty( $attributes = $param->getAttributes( ListenTo::class ) ) ) {
			return;
		}

		/** @var ListenerRegistry<BuildingEvent> */
		$dispatcher = $this->dispatcher;
		$attribute  = $attributes[0]->newInstance();
		$priorities = $dispatcher->getPriorities( $entry );
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
