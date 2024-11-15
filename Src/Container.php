<?php
/**
 * The container.
 *
 * @package TheWebSolver\Codegarage\Container
 *
 * @phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint -- Generics & Closure type-hint OK.
 * @phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch -- Generics & Closure type-hint OK.
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container;

use Closure;
use Exception;
use TypeError;
use ArrayAccess;
use LogicException;
use ReflectionClass;
use ReflectionException;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use TheWebSolver\Codegarage\Lib\Container\Pool\Param;
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;
use TheWebSolver\Codegarage\Lib\Container\Data\Aliases;
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Helper\Unwrap;
use TheWebSolver\Codegarage\Lib\Container\Pool\Artefact;
use TheWebSolver\Codegarage\Lib\Container\Event\EventType;
use TheWebSolver\Codegarage\Lib\Container\Helper\Generator;
use TheWebSolver\Codegarage\Lib\Container\Error\EntryNotFound;
use TheWebSolver\Codegarage\Lib\Container\Helper\EventBuilder;
use TheWebSolver\Codegarage\Lib\Container\Error\ContainerError;
use TheWebSolver\Codegarage\Lib\Container\Helper\ParamResolver;
use TheWebSolver\Codegarage\Lib\Container\Pool\CollectionStack;
use TheWebSolver\Codegarage\Lib\Container\Helper\ContextBuilder;
use TheWebSolver\Codegarage\Lib\Container\Helper\MethodResolver;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\Resettable;
use TheWebSolver\Codegarage\Lib\Container\Attribute\DecorateWith;
use TheWebSolver\Codegarage\Lib\Container\Event\BeforeBuildEvent;
use TheWebSolver\Codegarage\Lib\Container\Error\BadResolverArgument;
use TheWebSolver\Codegarage\Lib\Container\Event\Manager\EventManager;
use TheWebSolver\Codegarage\Lib\Container\Event\Manager\AfterBuildHandler;

/** @template-implements ArrayAccess<string,mixed> */
class Container implements ArrayAccess, ContainerInterface, Resettable {
	/** @var ?static */
	protected static $instance;
	/** @var Stack<class-string> */
	protected Stack $resolved;
	protected Artefact $artefact;
	protected Param $dependencies;
	/** @var Stack<array<class-string,class-string>> */
	protected Stack $resolvedInstances;
	private ?ReflectionClass $reflector;
	protected EventManager $eventManager;

	/**
	 * @param Stack<Binding>                               $bindings
	 * @param CollectionStack<string,Closure|class-string> $contextual
	 * @param CollectionStack<string,string>               $tags
	 * @param CollectionStack<int,Closure>                 $rebounds
	 * @param CollectionStack<string,bool>                 $fetchedListenerAttributes
	 */
	final public function __construct(
		protected Stack $bindings = new Stack(),
		protected Aliases $aliases = new Aliases(),
		protected CollectionStack $contextual = new CollectionStack(),
		protected CollectionStack $tags = new CollectionStack(),
		protected CollectionStack $rebounds = new CollectionStack(),
		protected CollectionStack $fetchedListenerAttributes = new CollectionStack(),
		EventManager $eventManager = null
	) {
		$this->eventManager      = EventType::registerDispatchersTo( $eventManager ?? new EventManager() );
		$this->resolvedInstances = new Stack();
		$this->resolved          = new Stack();
		$this->dependencies      = new Param();
		$this->artefact          = new Artefact();
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

	public function hasResolved( string $id ): bool {
		$entry = $this->getEntryFrom( $id );

		return $this->resolvedInstances->has( $entry ) ?: $this->resolved->has( $entry );
	}

	/**
	 * @param class-string $attributeName
	 * @access private
	 */
	public function isListenerFetchedFrom( string $entry, string $attributeName ): bool {
		return true === $this->fetchedListenerAttributes->get( key: $attributeName, index: $entry );
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

	/**
	 * @param string|class-string $id
	 * @return class-string
	 */
	public function getEntryFrom( string $id ): string {
		return $this->aliases->has( $id ) ? $this->aliases->get( $id ) : $id;
	}

	public function getBinding( string $id ): ?Binding {
		return $this->bindings[ $id ] ?? null;
	}

	/**
	 * @param string|class-string $id
	 * @return object|class-string|string Alias if not a class-string.
	 * @throws ContainerError When concrete not found for given {@param $id}.
	 * @see Container::register() For details on how entry is bound to the container.
	 */
	public function getConcrete( string $id ): object|string {
		if ( ( ! $bound = $this->getBinding( $id ) ) ) {
			return $id;
		}

		$material = $bound->material;

		return match ( true ) {
			default                                                => null,
			$material === $id || $bound->isInstance()              => $id,
			is_string( $material ) || $material instanceof Closure => $material,
		} ?? throw ContainerError::unResolvableEntry( $id );
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
		$dispatcher = $this->eventManager->getDispatcher( EventType::Building );

		return ( new MethodResolver( $this, $dispatcher, $this->artefact ) )
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

	public function getContextual( string $id, string $typeHintOrParamName ): Closure|string|null {
		return $this->contextual->get( $this->getEntryFrom( $id ), $typeHintOrParamName );
	}

	/**
	 * @access private
	 * @internal This should never be used as an API to get the contextual
	 *           data except when resolving the current artefact.
	 */
	public function getContextualFor( string $typeHintOrParamName ): Closure|string|null {
		if ( null !== ( $bound = $this->fromContextual( $typeHintOrParamName ) ) ) {
			return $bound;
		}

		if ( ! $this->aliases->has( id: $typeHintOrParamName, asEntry: true ) ) {
			return null;
		}

		foreach ( $this->aliases->get( id: $typeHintOrParamName, asEntry: true ) as $alias ) {
			if ( null !== ( $bound = $this->fromContextual( $alias ) ) ) {
				return $bound;
			}
		}

		return null;
	}

	/** @return class-string|array<class-string,class-string>|null */
	public function getResolved( string $id ): string|array|null {
		$entry = $this->getEntryFrom( $id );

		return $this->resolvedInstances[ $entry ] ?? $this->resolved[ $entry ];
	}

	public function getEventManager(): EventManager {
		return $this->eventManager;
	}

	/*
	|================================================================================================
	| SETTER METHODS
	|================================================================================================
	*/

	/**
	 * @param string               $key
	 * @param callable|string|null $value
	 */
	public function offsetSet( $key, $value ): void {
		$this->set( id: $key, concrete: $value );
	}

	/**
	 * @param class-string $entry
	 * @throws LogicException When entry ID and alias is same.
	 */
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
	 * @param string|class-string               $id
	 * @param string|class-string|callable|null $concrete
	 */
	public function set( string $id, callable|string|null $concrete = null ): void {
		$this->register( $id, concreteOrItsAlias: $concrete ?? $id, singleton: false );
	}

	/**
	 * @param class-string               $id
	 * @param class-string|callable|null $concrete
	 */
	public function setShared( string $id, callable|string|null $concrete = null ): void {
		$this->register( $id, concreteOrItsAlias: $concrete ?? $id, singleton: true );
	}

	/**
	 * @param T $instance
	 * @return T
	 * @template T of object
	 */
	public function setInstance( string $id, object $instance ): object {
		$hasEntry = $this->has( $id );

		$this->aliases->remove( $id );

		$this->bindings->set( key: $id, value: new Binding( material: $instance, instance: true ) );

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

	/**
	 * @param class-string $attributeName
	 * @access private
	 */
	public function setListenerFetchedFrom( string $entry, string $attributeName ): void {
		$this->fetchedListenerAttributes->set( key: $attributeName, value: true, index: $entry );
	}

	/*
	|================================================================================================
	| CREATOR METHODS
	|================================================================================================
	*/

	/**
	 * @param EventType|string|string[]|Closure $concrete Closure for instantiated class method binding.
	 * @return ($concrete is EventType ? EventBuilder : ContextBuilder)
	 * @throws LogicException When unregistered Event Dispatcher provided to add event listener.
	 */
	public function when( EventType|string|array|Closure $concrete ): ContextBuilder|EventBuilder {
		if ( $concrete instanceof EventType ) {
			return ( $registry = $this->eventManager->getDispatcher( $concrete ) )
				? new EventBuilder( app: $this, type: $concrete, registry: $registry )
				: throw new LogicException(
					message: sprintf( 'Cannot add Event Listener for the "%s" Event Type.', $concrete->name )
				);
		}

		$concrete = $concrete instanceof Closure ? Unwrap::forBinding( $concrete ) : $concrete;
		$ids      = array_map( $this->getEntryFrom( ... ), array: Unwrap::asArray( $concrete ) );

		return new ContextBuilder( for: $ids, app: $this, contextual: $this->contextual );
	}

	/**
	 * @param class-string<T>|Closure $concrete
	 * @return ($concrete is class-string ? T : mixed)
	 * @throws ContainerExceptionInterface When cannot build `$concrete`.
	 * @throws ContainerError              When non-instantiable class-string `$concrete` given.
	 * @template T
	 */
	public function build( string|Closure $concrete, bool $dispatch = true ): mixed {
		if ( $concrete instanceof Closure ) {
			return $concrete( $this, $this->dependencies->latest() ?? array() );
		}

		try {
			$reflector       = $this->reflector ?? Unwrap::classReflection( $concrete );
			$this->reflector = $reflector;
		} catch ( ReflectionException | LogicException $e ) {
			throw ContainerError::whenResolving( entry: $concrete, exception: $e, artefact: $this->artefact );
		}

		if ( null === ( $constructor = $reflector->getConstructor() ) ) {
			return new $concrete();
		}

		$this->artefact->push( value: $concrete );

		try {
			$dispatcher = $dispatch ? $this->eventManager->getDispatcher( EventType::Building ) : null;
			$resolver   = new ParamResolver( $this, $this->dependencies, $dispatcher );
			$resolved   = $resolver->resolve( dependencies: $constructor->getParameters() );
		} catch ( ContainerExceptionInterface $e ) {
			throw $e;
		}

		$this->artefact->pull();

		return $reflector->newInstanceArgs( args: $resolved );
	}

	/** @return iterable<int,object> */
	public function tagged( string $name ): iterable {
		return ! $this->tags->has( key: $name ) ? array() : new Generator(
			count: $this->tags->countFor( $name ),
			generator: function () use ( $name ) {
				foreach ( ( $this->tags->get( $name ) ?? array() ) as $id ) {
					yield $this->get( $id );
				}
			},
		);
	}

	/**
	 * Invokes `$callback` when bound `$id` is updated again using any of the binding methods.
	 *
	 * @param Closure(object $obj, Container $app): ?object $callback
	 * @return mixed The resolved data, `null` if nothing was bound before.
	 * @throws EntryNotFound When binding not found for the given id `$id`.
	 */
	public function useRebound( string $id, Closure $callback ): mixed {
		$this->rebounds->set( key: $id, value: $callback );

		return $this->has( $id ) ? $this->get( $id ) : throw EntryNotFound::forRebound( $id );
	}

	/*
	|================================================================================================
	| DESTRUCTOR METHODS
	|================================================================================================
	*/

	/** @param string $key */
	public function offsetUnset( $key ): void {
		unset( $this->bindings[ $key ], $this->resolved[ $key ], $this->resolvedInstances[ $key ] );
	}

	public function removeInstance( string $entry ): bool {
		return $this->isInstance( $entry ) && $this->bindings->remove( key: $entry );
	}

	public function removeResolved( string $entry ): bool {
		return $this->resolvedInstances->remove( $entry ) ?: $this->resolved->remove( $entry );
	}

	public function reset( ?string $collectionId = null ): void {
		$props = get_object_vars( $this );

		array_walk( $props, static fn( mixed $pool ) => $pool instanceof Resettable && $pool->reset( $collectionId ) );
	}

	/*
	|================================================================================================
	| HELPER METHODS
	|================================================================================================
	*/

	/**
	 * @param string $id The abstract or a concrete/alias.
	 */
	protected function rebound( string $id ): void {
		$updated = $this->resolveWithoutEvents( $id );

		foreach ( ( $this->rebounds->get( $id ) ?? array() ) as $listener ) {
			$listener( $updated, $this );
		}
	}

	/**
	 * @param string|class-string          $id
	 * @param string|class-string|callable $concreteOrItsAlias
	 */
	protected function register( string $id, callable|string $concreteOrItsAlias, bool $singleton ): void {
		$this->maybePurgeIfAliasOrInstance( $id );

		$material = is_callable( $concreteOrItsAlias )
			? $concreteOrItsAlias( ... )
			: $this->getEntryFrom( $id === $concreteOrItsAlias ? $id : $concreteOrItsAlias );

		$this->bindings->set( key: $id, value: new Binding( $material, $singleton ) );

		if ( $this->hasResolved( $id ) ) {
			$this->rebound( $id );
		}
	}

	/** @param ArrayAccess<object|string,mixed>|array<string,mixed> $with */
	public function resolve(
		string $id,
		array|ArrayAccess $with,
		bool $dispatch,
		?ReflectionClass $reflector = null
	): mixed {
		$entry           = $this->getEntryFrom( $id );
		$this->reflector = $reflector;

		if ( ( $bound = $this->getBinding( $entry ) ) && $bound->isInstance() ) {
			if ( ! $dispatch ) {
				return $bound->material;
			}

			$this->prepareReflectorAfterBuilding( $entry, $bound->material );

			return $this->dispatchAfterBuildingInstance( $entry, $bound->material, resolved: false );
		}

		/** @var class-string|Closure At this point, it can only be a class-string or a closure. */
		$material = $this->getConcrete( $entry );
		$concrete = $material instanceof Closure ? $entry : $material;

		$this->dependencies->push(
			value: $dispatch ? $this->dispatchBeforeBuilding( entry: $concrete, params: $with ) : $with
		);

		$built = $this->build( $material, $dispatch );

		$this->dependencies->pull();

		if ( ! $this->isResolvedAsObject( $concrete, $built ) ) {
			return $built;
		}

		if ( $dispatch ) {
			$this->prepareReflectorAfterBuilding( $concrete, $built );
		}

		$object = match ( $shared = $this->shouldBeSingleton( $entry ) ) {
			false => $dispatch ? $this->dispatchAfterBuilding( $concrete, $built ) : $built,
			true  => $dispatch
				? $this->dispatchAfterBuildingInstance( $entry, $built, resolved: true )
				: $this->setInstance( $entry, $built )
		};

		if ( ! $shared ) {
			$this->resolved->set( key: $entry, value: $object::class );
		}

		unset( $this->reflector );

		return $object;
	}

	/** @phpstan-assert-if-true =object $built */
	protected function isResolvedAsObject( string $concrete, mixed $built ): bool {
		return $built instanceof $concrete;
	}

	protected function shouldBeSingleton( string $entry ): bool {
		return true === $this->getBinding( $entry )?->isSingleton();
	}

	protected function fromContextual( string $constraint ): Closure|string|null {
		return ( $artefact = $this->artefact->latest() )
			? $this->getContextual( $artefact, $constraint )
			: null;
	}

	protected function maybePurgeIfAliasOrInstance( string $id ): void {
		$this->removeInstance( $id );
		$this->aliases->remove( $id );
	}

	/**
	 * @param array<string,mixed>|ArrayAccess<object|string,mixed> $params
	 * @return array<string,mixed>|ArrayAccess<object|string,mixed>
	 */
	private function dispatchBeforeBuilding( string $entry, array|ArrayAccess $params ): array|ArrayAccess {
		$event = $this->eventManager
			->getDispatcher( EventType::BeforeBuild )
			?->dispatch( new BeforeBuildEvent( $entry, $params ) );

		return $event instanceof BeforeBuildEvent && ( $eventParams = $event->getParams() ) ? $eventParams : $params;
	}

	private function dispatchAfterBuilding( string $id, object $resolved ): object {
		return AfterBuildHandler::handleWith( $this, $id, $resolved, $this->artefact, $this->reflector );
	}

	private function dispatchAfterBuildingInstance( string $entry, object $built, bool $resolved ): object {
		if ( $this->resolvedInstances->has( key: $entry ) ) {
			return $built;
		}

		$baseClass = $built::class;
		$eventId   = $resolved ? $baseClass : $entry;
		$built     = $this->dispatchAfterBuilding( $eventId, $built );

		$this->eventManager->getDispatcher( EventType::AfterBuild )?->reset( $eventId );
		$this->resolvedInstances->set( key: $entry, value: array( $baseClass => $built::class ) );

		return $this->setInstance( $entry, $built );
	}

	/** @param class-string|object $concrete */
	private function prepareReflectorAfterBuilding( string $entry, string|object $concrete ): ?ReflectionClass {
		return $this->isListenerFetchedFrom( $entry, attributeName: DecorateWith::class )
			? $this->reflector   = null
			: $this->reflector ??= new ReflectionClass( $concrete );
	}
}
