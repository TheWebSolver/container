<?php
/**
 * The container.
 *
 * @package TheWebSolver\Codegarage\Container
 *
 * @phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing
 * @phpcs:disable Squiz.Commenting.FunctionComment.WrongStyle
 * @phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
 * @phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch
 * @phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container;

use Closure;
use WeakMap;
use Exception;
use TypeError;
use ArrayAccess;
use LogicException;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use TheWebSolver\Codegarage\Lib\Container\Pool\Param;
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;
use TheWebSolver\Codegarage\Lib\Container\Data\Aliases;
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Helper\Unwrap;
use TheWebSolver\Codegarage\Lib\Container\Pool\Artefact;
use TheWebSolver\Codegarage\Lib\Container\Event\EventType;
use TheWebSolver\Codegarage\Lib\Container\Helper\Generator;
use TheWebSolver\Codegarage\Lib\Container\Error\EntryNotFound;
use TheWebSolver\Codegarage\Lib\Container\Event\BuildingEvent;
use TheWebSolver\Codegarage\Lib\Container\Helper\EventBuilder;
use TheWebSolver\Codegarage\Lib\Container\Error\ContainerError;
use TheWebSolver\Codegarage\Lib\Container\Helper\ParamResolver;
use TheWebSolver\Codegarage\Lib\Container\Event\AfterBuildEvent;
use TheWebSolver\Codegarage\Lib\Container\Event\EventDispatcher;
use TheWebSolver\Codegarage\Lib\Container\Helper\ContextBuilder;
use TheWebSolver\Codegarage\Lib\Container\Helper\MethodResolver;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\Resettable;
use TheWebSolver\Codegarage\Lib\Container\Attribute\DecorateWith;
use TheWebSolver\Codegarage\Lib\Container\Event\BeforeBuildEvent;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\TaggableEvent;
use TheWebSolver\Codegarage\Lib\Container\Traits\ListenerRegistrar;
use TheWebSolver\Codegarage\Lib\Container\Error\BadResolverArgument;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\ListenerRegistry;
/**
 * @template-implements ArrayAccess<string,mixed>
 */
class Container implements ArrayAccess, ContainerInterface, Resettable {
	/** @var ?static */
	protected static $instance;

	/** @var WeakMap<EventType,EventDispatcherInterface&ListenerRegistry> */
	protected WeakMap $eventDispatchers;

	// phpcs:disable Squiz.Commenting.FunctionComment.SpacingAfterParamType
	/**
	 * @param WeakMap<EventType,EventDispatcherInterface&ListenerRegistry> $eventDispatchers
	 * @param Stack<Binding>                                               $bindings
	 * @param Stack<array<string,Closure|string|null>>                     $contextual
	 * @param Stack<array<int,string>>                                     $tags
	 * @param Stack<Closure[]>                                             $rebounds
	 * @param Stack<array<class-string|Closure>>                           $extenders
	 * @param Stack<bool>                                                  $resolved
	 */
	// phpcs:enable Squiz.Commenting.FunctionComment.SpacingAfterParamType
	final public function __construct(
		protected readonly Stack $bindings = new Stack(),
		protected readonly Param $paramPool = new Param(),
		protected readonly Artefact $artefact = new Artefact(),
		protected readonly Aliases $aliases = new Aliases(),
		protected readonly Stack $resolved = new Stack(),
		protected readonly Stack $contextual = new Stack(),
		protected readonly Stack $extenders = new Stack(),
		protected readonly Stack $tags = new Stack(),
		protected readonly Stack $rebounds = new Stack(),
		?WeakMap $eventDispatchers = null,
	) {
		$this->polyfillEventDispatchers( $eventDispatchers );
		$this->extenders->asCollection();
		$this->rebounds->asCollection();
		$this->tags->asCollection();
	}

	/**
	 * @param WeakMap<EventType,EventDispatcherInterface &ListenerRegistry> $eventDispatchers
	 * @throws LogicException When all three Event Type Dispatchers were not provided.
	 */
	private function polyfillEventDispatchers( ?WeakMap $eventDispatchers ): void {
		if ( $eventDispatchers ) {
			if ( 3 !== $eventDispatchers->count() ) {
				throw new LogicException( 'All three Event Type Dispatchers must be provided.' );
			}

			return;
		}

		$beforeBuild = new /** @template-implements ListenerRegistry<BeforeBuildEvent> */
		class() implements ListenerProviderInterface, ListenerRegistry {
			/** @use ListenerRegistrar<BeforeBuildEvent> */
			use ListenerRegistrar;

			protected function isValid( object $event ): bool {
				return $event instanceof BeforeBuildEvent;
			}

			protected function shouldFire( TaggableEvent $event, string $currentEntry ): bool {
				$entry = $event->getEntry();

				return $entry === $currentEntry || is_subclass_of( $entry, $currentEntry, allow_string: true );
			}
		};

		$building = new /** @template-implements ListenerRegistry<BuildingEvent> */
		class() implements ListenerProviderInterface, ListenerRegistry {
			/** @use ListenerRegistrar<BuildingEvent> */
			use ListenerRegistrar;

			protected function isValid( object $event ): bool {
				return $event instanceof BuildingEvent;
			}

			protected function shouldFire( TaggableEvent $event, string $currentEntry ): bool {
				return $event->getEntry() === $currentEntry;
			}
		};

		$afterBuild = new /** @template-implements ListenerRegistry<AfterBuildEvent> */
		class() implements ListenerProviderInterface, ListenerRegistry {
			/** @use ListenerRegistrar<AfterBuildEvent> */
			use ListenerRegistrar;

			protected function isValid( object $event ): bool {
				return $event instanceof AfterBuildEvent;
			}

			protected function shouldFire( TaggableEvent $event, string $currentEntry ): bool {
				return $event->getEntry() === $currentEntry
					|| $event instanceof AfterBuildEvent && $event->getResolved() instanceof $currentEntry;
			}
		};

		/** @var WeakMap<EventType,EventDispatcherInterface&ListenerRegistry> */
		$dispatchers                           = new WeakMap();
		$dispatchers[ EventType::BeforeBuild ] = new EventDispatcher( provider: $beforeBuild );
		$dispatchers[ EventType::Building ]    = new EventDispatcher( provider: $building );
		$dispatchers[ EventType::AfterBuild ]  = new EventDispatcher( provider: $afterBuild );
		$this->eventDispatchers                = $dispatchers;
	}

	public static function boot(): static {
		return static::$instance ??= new static();
	}

	/*
	 |================================================================================================
	 | ASSERTION METHODS
	 |================================================================================================
	 */

	/** @param string $key The key. */
	public function offsetExists( $key ): bool {
		return $this->has( id: $key );
	}

	public function hasBinding( string $id ): bool {
		return $this->bindings->has( key: $id );
	}

	public function isAlias( string $id ): bool {
		return $this->aliases->has( $id, asEntry: false );
	}

	public function isInstance( string $id ): bool {
		return true === $this->getBinding( $id )?->isInstance();
	}

	/** @return bool `true` if has binding or given ID is an alias, `false` otherwise. */
	public function has( string $id ): bool {
		return $this->hasBinding( $id ) || $this->isAlias( $id );
	}

	public function resolved( string $id ): bool {
		$entry = $this->getEntryFrom( alias: $id );

		return $this->resolved->has( key: $entry ) || $this->isInstance( id: $entry );
	}

	protected function hasContextualFor( string $entry ): bool {
		return ! empty( $this->contextual[ $entry ] );
	}

	/*
	 |================================================================================================
	 | GETTER METHODS
	 |================================================================================================
	 */

	/** @param string $key The key. */
	#[\ReturnTypeWillChange]
	public function offsetGet( $key ): mixed {
		return $this->get( $key );
	}

	/** @return string Returns alias itself if cannot find any entry related to the given alias. */
	public function getEntryFrom( string $alias ): string {
		return $this->aliases->get( id: $alias, asEntry: false );
	}

	public function getBinding( string $id ): ?Binding {
		return $this->bindings[ $id ] ?? null;
	}

	/** @param array<string,mixed>|ArrayAccess<object|string,mixed> $params */
	public function resolveWithoutEvents( string $id, array|ArrayAccess $params = array() ): mixed {
		return $this->resolve( $id, $params, dispatch: false );
	}

	/**
	 * @param callable|string     $callback Possible options are:
	 * - `string`   -> 'classname::methodname'
	 * - `string`   -> 'classname#' . spl_object_id($classInstance) . '::methodname'
	 * - `callable` -> $classInstance->methodname(...) as first-class callable
	 * - `callable` -> array($classInstance, 'methodname').
	 * @param array<string,mixed> $params The method's injected parameters.
	 * @throws BadResolverArgument When method cannot be resolved or no `$default`.
	 */
	public function call(
		callable|string $callback,
		array $params = array(),
		?string $defaultMethod = null
	): mixed {
		return ( new MethodResolver( $this, $this->eventDispatchers[ EventType::Building ], $this->artefact ) )
			->resolve( $callback, $defaultMethod, $params );
	}

	/**
	 * @param array<string,mixed>|ArrayAccess<object|string,mixed> $with
	 * @throws NotFoundExceptionInterface  When entry with given $id was not found in the container.
	 * @throws ContainerExceptionInterface When cannot resolve concrete from the given $id.
	 */
	public function get( string $id, array|ArrayAccess $with = array() ): mixed {
		try {
			return $this->resolve( $id, $with, dispatch: true );
		} catch ( Exception $e ) {
			throw $this->has( $id ) || $e instanceof ContainerExceptionInterface
				? $e
				: EntryNotFound::for( $id, previous: $e );
		}
	}

	public function getContextual( string $for, string $context ): Closure|string|null {
		return $this->contextual[ $this->getEntryFrom( alias: $for ) ][ $context ] ?? null;
	}

	/**
	 * @access private
	 * @internal This should never be used as an API to get the contextual data. Contextual
	 *           data becomes invalidated as soon as entry is resolved coz the respective
	 *           entry (artefact) is pulled immediately from the stack which makes the
	 *           contextual data stored to the pool to be orphaned & non-retrievable.
	 *           (unless same contextual data is used again to resolve an entry).
	 *           Use `Container::getContextual()` to get stored context data.
	 */
	public function getContextualFor( string $context ): Closure|string|null {
		if ( null !== ( $binding = $this->fromContextual( $context ) ) ) {
			return $binding;
		}

		if ( ! $this->aliases->has( id: $context, asEntry: true ) ) {
			return null;
		}

		foreach ( $this->aliases->get( id: $context, asEntry: true ) as $alias ) {
			if ( null !== ( $binding = $this->fromContextual( context: $alias ) ) ) {
				return $binding;
			}
		}

		return null;
	}

	/** @return Stack<bool> */
	public function getResolved(): Stack {
		return $this->resolved;
	}

	/*
	 |================================================================================================
	 | SETTER METHODS
	 |================================================================================================
	 */

	/**
	 * @param string              $key
	 * @param Closure|string|null $value
	 */
	public function offsetSet( $key, $value ): void {
		$this->set( id: $key, concrete: $value );
	}

	/** @throws LogicException When entry ID and alias is same. */
	public function setAlias( string $entry, string $alias ): void {
		$this->aliases->set( $entry, $alias );
	}

	/** @param string|string[] $ids */
	public function tag( string|array $ids, string $tag, string ...$tags ): void {
		foreach ( array( $tag, ...$tags ) as $key ) {
			foreach ( Unwrap::asArray( thing: $ids ) as $id ) {
				$this->tags->set( $key, value: $id );
			}
		}
	}

	/**
	 * If a classname was previously aliased, it is recommended to pass that classname
	 * instead of an alias as {@param `$id`} to prevent that alias from being purged.
	 */
	public function set( string $id, Closure|string|null $concrete = null ): void {
		$this->register( $id, $concrete, singleton: false );
	}

	public function setShared( string $id, Closure|string|null $concrete = null ): void {
		$this->register( $id, $concrete, singleton: true );
	}

	public function setInstance( string $id, object $instance ): object {
		$hasEntry = $this->has( $id );

		$this->aliases->remove( $id );

		$this->bindings->set( key: $id, value: new Binding( concrete: $instance, instance: true ) );

		if ( $hasEntry ) {
			$this->rebound( $id );
		}

		return $instance;
	}

	/**
	 * @param Closure|string $entry Either a first-class callable from instantiated class method, or
	 *                              a normalized string with `Unwrap::asString()` (*preferred*).
	 * @throws TypeError When `$entry` first-class callable was created using static method.
	 */
	public function setMethod( Closure|string $entry, Closure $callback ): void {
		$this->set( id: MethodResolver::keyFrom( id: $entry ), concrete: $callback );
	}

	/*
	 |================================================================================================
	 | CREATOR METHODS
	 |================================================================================================
	 */

	/**
	 * @param EventType|string|string[]|Closure $constraint
	 * @return ($constraint is EventType ? EventBuilder : ContextBuilder)
	 */
	public function when( EventType|string|array|Closure $constraint ): ContextBuilder|EventBuilder {
		if ( $constraint instanceof EventType ) {
			return new EventBuilder( app: $this, type: $constraint, registry: $this->eventDispatchers[ $constraint ] );
		}

		$constraint = $constraint instanceof Closure ? Unwrap::forBinding( $constraint ) : $constraint;
		$ids      = array_map( $this->getEntryFrom( ... ), array: Unwrap::asArray( $constraint ) );

		return new ContextBuilder( for: $ids, app: $this, contextual: $this->contextual );
	}

	/**
	 * @param class-string|Closure $with
	 * @return array{0:?ReflectionClass,1:mixed}
	 */
	public function build( string|Closure $with, bool $dispatch = true, ?ReflectionClass $reflector = null ): array {
		if ( $with instanceof Closure ) {
			return array( $reflector, $with( $this, $this->paramPool->latest() ) );
		}

		$reflector ??= $this->getReflectionOf( classname: $with );

		if ( null === ( $constructor = $reflector->getConstructor() ) ) {
			return array( $reflector, new $with() );
		}

		$this->artefact->push( value: $with );

		try {
			$dispatcher = $dispatch ? $this->eventDispatchers[ EventType::Building ] : null;
			$resolver   = new ParamResolver( $this, $this->paramPool, $dispatcher );
			$resolved   = $resolver->resolve( dependencies: $constructor->getParameters() );
		} catch ( ContainerExceptionInterface $e ) {
			$this->artefact->pull();

			throw $e;
		}

		$this->artefact->pull();

		return array( $reflector, $reflector->newInstanceArgs( args: $resolved ) );
	}

	/** @return iterable<int,object> */
	public function tagged( string $name ) {
		return ! $this->tags->has( key: $name ) ? array() : new Generator(
			count: $this->tags->withKey( $name ),
			generator: function () use ( $name ) {
				foreach ( $this->tags[ $name ] ?? array() as $id ) {
					yield $this->get( $id );
				}
			},
		);
	}

	// TODO: Remove once EventType::AfterBuild is implemented.
	public function extend( string $id, Closure $closure ): void {
		$id = $this->getEntryFrom( alias: $id );

		if ( $this->isInstance( $id ) && is_object( $instance = $this->bindings[ $id ]->concrete ) ) {
			$this->bindings->set( key: $id, value: new Binding( concrete: $instance, instance: true ) );

			$this->rebound( $id );

			return;
		}

		$this->extenders->set( key: $id, value: $closure );

		if ( $this->resolved( $id ) ) {
			$this->rebound( $id );
		}
	}

	/**
	 * Invokes `$with` callback when bound `$id` is updated again using any of the binding methods
	 * such as `Container::set()`, `Container::singleton()` & `Container::instance()`.
	 *
	 * @param Closure(object $obj, Container $app): ?object $with
	 * @return mixed The resolved data, `null` if nothing was bound before.
	 * @throws NotFoundExceptionInterface When binding not found for the given id `$of`.
	 */
	public function useRebound( string $of, Closure $with ): mixed {
		$this->rebounds->set( key: $of, value: $with );

		return $this->has( $of ) ? $this->get( $of ) : throw EntryNotFound::forRebound( $of );
	}

	/*
	 |================================================================================================
	 | DESTRUCTOR METHODS
	 |================================================================================================
	 */

	/** @param string $key */
	public function offsetUnset( $key ): void {
		unset( $this->bindings[ $key ], $this->resolved[ $key ] );
	}

	public function removeExtenders( string $id ): void {
		$this->extenders->remove( key: $this->getEntryFrom( $id ) );
	}

	public function removeInstance( string $id ): bool {
		return $this->isInstance( $id ) && $this->bindings->remove( key: $id );
	}

	public function reset( ?string $collectionId = null ): void {
		$props = get_object_vars( $this );

		array_walk( $props, static fn( mixed $pool ) => $pool instanceof Resettable && $pool->reset() );
	}

	/*
	 |================================================================================================
	 | HELPER METHODS
	 |================================================================================================
	 */

	protected function rebound( string $id ): void {
		$old = $this->get( $id );

		foreach ( ( $this->rebounds[ $id ] ?? array() ) as $new ) {
			$new( $old, $this );
		}
	}

	protected function register( string $entry, Closure|string|null $concrete, bool $singleton ): void {
		$this->maybePurgeIfAliasOrInstance( $entry );

		$binding = match ( true ) {
			! $concrete || $concrete === $entry => $entry,
			$concrete instanceof Closure        => $concrete,
			default                             => array( $entry => $concrete )
		};

		$this->bindings->set( key: $entry, value: new Binding( $binding, $singleton ) );

		if ( $this->resolved( $entry ) ) {
			$this->rebound( $entry );
		}
	}

	/** @param ArrayAccess<object|string,mixed>|array<string,mixed> $with */
	protected function resolve(
		string $id,
		array|ArrayAccess $with,
		bool $dispatch,
		?ReflectionClass $reflector = null
	): mixed {
		$id    = $this->getEntryFrom( alias: $id );
		$bound = $this->getConcrete( $id );

		// We'll allow using either entry/abstract or a concrete for listening to the event.
		$entry = $bound instanceof Closure ? $id : $bound;

		if ( $dispatch ) {
			/** @var BeforeBuildEvent */
			$event = $this->eventDispatchers[ EventType::BeforeBuild ]?->dispatch(
				event: new BeforeBuildEvent( $entry, params: $with )
			);

			$with = $event->getParams();
		}

		$needsBuild = ! empty( $with ) || $this->hasContextualFor( entry: $id );

		if ( $this->isInstance( $id ) && ! $needsBuild ) {
			return $this->bindings[ $id ]->concrete;
		}

		$this->paramPool->push( value: $with ?? array() );

		[ $reflector, $resolved ] = $this->build( $bound, $dispatch, $reflector );

		if ( true === $this->getBinding( $id )?->isSingleton() && ! $needsBuild ) {
			$this->bindings->set( key: $id, value: new Binding( concrete: $resolved, instance: true ) );
		}

		if ( $dispatch ) {
			$resolved = $this->dispatchAfterBuildEvent( $entry, $resolved, $reflector );
		}

		$this->paramPool->pull();
		$this->resolved->set( key: $id, value: true );

		return $resolved;
	}

	protected function dispatchAfterBuildEvent( string $id, mixed $resolved, ?ReflectionClass $reflector ): mixed {
		/** @var EventDispatcherInterface&ListenerRegistry<AfterBuildEvent> */
		$eventDispatcher = $this->eventDispatchers[ EventType::AfterBuild ];

		if ( $reflector && ! empty( $attributes = $reflector->getAttributes( DecorateWith::class ) ) ) {
			[ $low, $high ] = $eventDispatcher->getPriorities();
			$attribute      = $attributes[0]->newInstance();
			$priority       = $attribute->isFinal ? $high + 1 : $low - 1;

			$eventDispatcher->addListener( ( $attribute->listener )( ... ), forEntry: $id, priority: $priority );
		}

		/** @var AfterBuildEvent */
		$event = $eventDispatcher->dispatch( event: new AfterBuildEvent( $resolved, entry: $id ) );

		foreach ( $event->getDecorators()[ $id ] ?? array() as $decorator ) {
			$resolved = $this->decorate( $resolved, $decorator );
		}

		foreach ( $event->getUpdaters()[ $id ] ?? array() as $updater ) {
			$updater( $resolved, $this );
		}

		return $resolved;
	}

	/** @param string|Closure $decorator */
	protected function decorate( mixed $resolved, string|Closure $decorator ): mixed {
		if ( $decorator instanceof Closure ) {
			return $decorator( $resolved, $this );
		}

		$reflection = $this->getReflectionOf( $this->getEntryFrom( $decorator ) );
		$args       = array( $this->getDecoratorParamFrom( $reflection, $resolved )->getName() => $resolved );

		return $this->resolve( $decorator, with: $args, dispatch: true, reflector: $reflection );
	}

	/**
	 * @param class-string $classname
	 * @throws ContainerError When invalid or un-instantiable classname given.
	 */
	protected function getReflectionOf( string $classname ): ReflectionClass {
		try {
			$reflector = new ReflectionClass( $classname );
		} catch ( ReflectionException $error ) {
			throw ContainerError::unResolvableEntry( id: $classname, previous: $error );
		}

		return ! $reflector->isInstantiable()
			? throw ContainerError::unInstantiableEntry( id: $classname, artefact: $this->artefact )
			: $reflector;
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

	/** @see Container::register() For details on how entry is bound to the container. */
	public function getConcrete( string $entry ): Closure|string {
		if ( ! $binding = $this->getBinding( $entry ) ) {
			return $entry;
		}

		$concrete = $binding->concrete;

		return match ( true ) {
			default => throw ContainerError::unResolvableEntry( $entry ),

			// Both abstract and concrete is same, or entry is already resolved as an instance.
			$concrete === $entry || $binding->isInstance() => $entry,

			// Resolve using the user-defined Closure.
			$concrete instanceof Closure => $concrete,

			// Map of [abstract or its alias => concrete or its alias]. Beyond this, the entry is unresolvable.
			is_array( $concrete ) => $this->getEntryFrom(
				$concrete[ $entry ] ?? throw ContainerError::unResolvableEntry( $entry )
			),
		};
	}

	protected function fromContextual( string $context ): Closure|string|null {
		return is_string( $artefact = $this->artefact->latest() )
			? $this->getContextual( for: $artefact, context: $context )
			: null;
	}

	protected function maybePurgeIfAliasOrInstance( string $id ): void {
		$this->removeInstance( $id );
		$this->aliases->remove( $id );
	}
}
