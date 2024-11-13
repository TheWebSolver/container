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
	/** @var Stack<array<class-string,class-string>> */
	protected Stack $resolvedInstances;
	protected EventManager $eventManager;

	private ?ReflectionClass $reflector;

	/**
	 * @param Stack<Binding>                               $bindings
	 * @param CollectionStack<string,Closure|class-string> $contextual
	 * @param CollectionStack<string,string>               $tags
	 * @param CollectionStack<int,Closure>                 $rebounds
	 * @param CollectionStack<string,bool>                 $fetchedListenerAttributes
	 */
	final public function __construct(
		protected readonly Stack $bindings = new Stack(),
		protected readonly Param $dependencies = new Param(),
		protected readonly Artefact $artefact = new Artefact(),
		protected readonly Aliases $aliases = new Aliases(),
		protected readonly CollectionStack $contextual = new CollectionStack(),
		protected readonly CollectionStack $tags = new CollectionStack(),
		protected readonly CollectionStack $rebounds = new CollectionStack(),
		protected readonly CollectionStack $fetchedListenerAttributes = new CollectionStack(),
		EventManager $eventManager = null
	) {
		$this->eventManager      = EventType::registerDispatchersTo( $eventManager ?? new EventManager() );
		$this->resolvedInstances = new Stack();
		$this->resolved          = new Stack();
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
		$entry = $this->getEntryFrom( alias: $id );

		return $this->resolved->has( $entry ) || $this->resolvedInstances->has( $entry );
	}

	protected function hasContextualFor( string $entry ): bool {
		return $this->contextual->has( $entry );
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

	/** @return string Returns alias itself if cannot find any entry related to the given alias. */
	public function getEntryFrom( string $alias ): string {
		return $this->aliases->get( id: $alias, asEntry: false );
	}

	public function getBinding( string $id ): ?Binding {
		return $this->bindings[ $id ] ?? null;
	}

	/**
	 * @see Container::register() For details on how entry is bound to the container.
	 * @throws ContainerError When concrete not found for given {@param $id}.
	 * @return Closure|class-string|string Alias if not a class-string.
	 */
	public function getConcrete( string $id ): Closure|string {
		if ( ( ! $bound = $this->getBinding( $id ) ) ) {
			return $id;
		}

		$material = $bound->concrete;

		return match ( true ) {
			default                      => null,
			$material === $id            => $id,
			is_array( $material )        => isset( $material[ $id ] ) ? $this->getEntryFrom( $material[ $id ] ) : null,
			$material instanceof Closure => $material,
			$bound->isInstance()         => $id,
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

	public function getContextual( string $for, string $context ): Closure|string|null {
		return $this->contextual->get( $this->getEntryFrom( alias: $for ), $context );
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
		if ( null !== ( $bound = $this->fromContextual( $context ) ) ) {
			return $bound;
		}

		if ( ! $this->aliases->has( id: $context, asEntry: true ) ) {
			return null;
		}

		foreach ( $this->aliases->get( id: $context, asEntry: true ) as $alias ) {
			if ( null !== ( $bound = $this->fromContextual( context: $alias ) ) ) {
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

	/**
	 * @param T $instance
	 * @return T
	 * @template T of object
	 */
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
	 * @param EventType|string|string[]|Closure $constraint
	 * @return ($constraint is EventType ? EventBuilder : ContextBuilder)
	 * @throws LogicException When unregistered Event Dispatcher provided to add event listener.
	 */
	public function when( EventType|string|array|Closure $constraint ): ContextBuilder|EventBuilder {
		if ( $constraint instanceof EventType ) {
			return ( $registry = $this->eventManager->getDispatcher( $constraint ) )
				? new EventBuilder( app: $this, type: $constraint, registry: $registry )
				: throw new LogicException(
					message: sprintf( 'Cannot add Event Listener for the "%s" Event Type.', $constraint->name )
				);
		}

		$constraint = $constraint instanceof Closure ? Unwrap::forBinding( $constraint ) : $constraint;
		$ids        = array_map( $this->getEntryFrom( ... ), array: Unwrap::asArray( $constraint ) );

		return new ContextBuilder( for: $ids, app: $this, contextual: $this->contextual );
	}

	/**
	 * @param class-string<T>|Closure $with
	 * @return ($with is class-string ? T : mixed)
	 * @throws ContainerExceptionInterface When cannot build `$with`.
	 * @throws ContainerError              When non-instantiable class-string `$with` given.
	 * @template T
	 */
	public function build( string|Closure $with, bool $dispatch = true ): mixed {
		if ( $with instanceof Closure ) {
			return $with( $this, $this->dependencies->latest() ?? array() );
		}

		try {
			$reflector       = $this->reflector ?? Unwrap::classReflection( $with );
			$this->reflector = $reflector;
		} catch ( ReflectionException | LogicException $e ) {
			throw ContainerError::whenResolving( entry: $with, exception: $e, artefact: $this->artefact );
		}

		if ( null === ( $constructor = $reflector->getConstructor() ) ) {
			return new $with();
		}

		$this->artefact->push( value: $with );

		try {
			$dispatcher = $dispatch ? $this->eventManager->getDispatcher( EventType::Building ) : null;
			$resolver   = new ParamResolver( $this, $this->dependencies, $dispatcher );
			$resolved   = $resolver->resolve( dependencies: $constructor->getParameters() );
		} catch ( ContainerExceptionInterface $e ) {
			$this->artefact->pull();

			throw $e;
		}

		$this->artefact->pull();

		return $reflector->newInstanceArgs( args: $resolved );
	}

	/** @return iterable<int,object> */
	public function tagged( string $name ) {
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
	 * Invokes `$with` callback when bound `$id` is updated again using any of the binding methods
	 * such as `Container::set()`, `Container::singleton()` & `Container::instance()`.
	 *
	 * @param Closure(object $obj, Container $app): ?object $with
	 * @return mixed The resolved data, `null` if nothing was bound before.
	 * @throws EntryNotFound When binding not found for the given id `$of`.
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

	protected function register( string $id, Closure|string|null $concrete, bool $singleton ): void {
		$this->maybePurgeIfAliasOrInstance( $id );

		$material = match ( true ) {
			! $concrete || $concrete === $id => $id,
			$concrete instanceof Closure     => $concrete,
			default                          => array( $id => $concrete )
		};

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
		$entry           = $this->getEntryFrom( alias: $id );
		$this->reflector = $reflector;

		if ( ( $bound = $this->getBinding( $entry ) ) && $bound->isInstance() ) {
			if ( ! $dispatch ) {
				return $bound->concrete;
			}

			$this->prepareReflectorForAfterBuildEvent( $entry, $bound->concrete );

			return $this->dispatchAfterBuildEventForInstance( $entry, $bound->concrete, resolved: false );
		}

		/** @var class-string|Closure At this point, it can only be a class-string or a closure. */
		$material = $this->getConcrete( $entry );
		$concrete = $material instanceof Closure ? $entry : $material;

		$this->dependencies->push(
			value: $dispatch ? $this->dispatchBeforeBuildEvent( entry: $concrete, params: $with ) : $with
		);

		$resolved = $this->build( $material, $dispatch );

		$this->dependencies->pull();

		if ( ! $this->isResolvedAsObject( $concrete, $resolved ) ) {
			return $resolved;
		}

		if ( $dispatch ) {
			$this->prepareReflectorForAfterBuildEvent( $concrete, $resolved );
		}

		$resolved = match ( $shouldBeSingleton = $this->isSingletonFor( $entry ) ) {
			false => $dispatch ? $this->dispatchAfterBuildEvent( $concrete, $resolved ) : $resolved,
			true  => $dispatch
				? $this->dispatchAfterBuildEventForInstance( $entry, $resolved, resolved: true )
				: $this->setInstance( $entry, $resolved )
		};

		if ( ! $shouldBeSingleton ) {
			$this->resolved->set( key: $entry, value: $resolved::class );
		}

		unset( $this->reflector );

		return $resolved;
	}

	/** @phpstan-assert-if-true =object $resolved */
	protected function isResolvedAsObject( string $concrete, mixed $resolved ): bool {
		return $resolved instanceof $concrete;
	}

	protected function isSingletonFor( string $entry ): bool {
		return true === $this->getBinding( $entry )?->isSingleton();
	}

	protected function fromContextual( string $context ): Closure|string|null {
		return ( $artefact = $this->artefact->latest() )
			? $this->getContextual( for: $artefact, context: $context )
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
	private function dispatchBeforeBuildEvent( string $entry, array|ArrayAccess $params ): array|ArrayAccess {
		$event = $this->eventManager
			->getDispatcher( EventType::BeforeBuild )
			?->dispatch( new BeforeBuildEvent( $entry, $params ) );

		return $event instanceof BeforeBuildEvent && ( $eventParams = $event->getParams() ) ? $eventParams : $params;
	}

	private function dispatchAfterBuildEvent( string $id, object $resolved ): object {
		return AfterBuildHandler::handleWith( $this, $id, $resolved, $this->artefact, $this->reflector );
	}

	private function dispatchAfterBuildEventForInstance( string $entry, object $instance, bool $resolved ): object {
		$baseClass = $instance::class;
		$eventId   = $resolved ? $baseClass : $entry;

		if ( $this->resolvedInstances->has( key: $entry ) ) {
			return $resolved ? $this->setInstance( $entry, $instance ) : $instance;
		}

		$instance = $this->dispatchAfterBuildEvent( $eventId, $instance );

		$this->eventManager->getDispatcher( EventType::AfterBuild )?->reset( $eventId );
		$this->resolvedInstances->set( key: $entry, value: array( $baseClass => $instance::class ) );

		return $this->setInstance( $entry, $instance );
	}

	/** @param class-string|object $concrete */
	private function prepareReflectorForAfterBuildEvent( string $entry, string|object $concrete ): ?ReflectionClass {
		return $this->isListenerFetchedFrom( $entry, attributeName: DecorateWith::class )
			? $this->reflector   = null
			: $this->reflector ??= new ReflectionClass( $concrete );
	}
}
