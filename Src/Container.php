<?php
// phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint -- Generics & Closure type-hint OK.
// phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch  -- Generics & Closure type-hint OK.

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container;

use Closure;
use Exception;
use TypeError;
use ArrayAccess;
use ReflectionClass;
use ReflectionException;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use TheWebSolver\Codegarage\Container\Pool\Param;
use TheWebSolver\Codegarage\Container\Pool\Stack;
use TheWebSolver\Codegarage\Container\Data\Aliases;
use TheWebSolver\Codegarage\Container\Data\Binding;
use TheWebSolver\Codegarage\Container\Helper\Unwrap;
use TheWebSolver\Codegarage\Container\Pool\Artefact;
use TheWebSolver\Codegarage\Container\Event\EventType;
use TheWebSolver\Codegarage\Container\Traits\Resetter;
use TheWebSolver\Codegarage\Container\Helper\Generator;
use TheWebSolver\Codegarage\Container\Data\SharedBinding;
use TheWebSolver\Codegarage\Container\Error\LogicalError;
use TheWebSolver\Codegarage\Container\Error\EntryNotFound;
use TheWebSolver\Codegarage\Container\Helper\EventBuilder;
use TheWebSolver\Codegarage\Container\Error\ContainerError;
use TheWebSolver\Codegarage\Container\Helper\ParamResolver;
use TheWebSolver\Codegarage\Container\Pool\CollectionStack;
use TheWebSolver\Codegarage\Container\Helper\ContextBuilder;
use TheWebSolver\Codegarage\Container\Helper\MethodResolver;
use TheWebSolver\Codegarage\Container\Interfaces\Resettable;
use TheWebSolver\Codegarage\Container\Attribute\DecorateWith;
use TheWebSolver\Codegarage\Container\Event\BeforeBuildEvent;
use TheWebSolver\Codegarage\Container\Error\BadResolverArgument;
use TheWebSolver\Codegarage\Container\Event\Manager\EventManager;
use TheWebSolver\Codegarage\Container\Interfaces\ListenerRegistry;
use TheWebSolver\Codegarage\Container\Event\Manager\AfterBuildHandler;

/** @template-implements ArrayAccess<string,mixed> */
class Container implements ArrayAccess, ContainerInterface, Resettable {
	use Resetter;

	/** @var ?static */
	protected static $instance;
	/** @var Stack<class-string> */
	protected Stack $resolved;
	protected Artefact $artefact;
	protected Param $dependencies;
	/** @var Stack<array<class-string,class-string>> */
	protected Stack $resolvedInstances;
	private ?ReflectionClass $reflector;

	/**
	 * @param Stack<Binding|SharedBinding>                 $bindings
	 * @param CollectionStack<string,Closure|class-string> $contextManager
	 * @param CollectionStack<string,string>               $tags
	 * @param CollectionStack<int,Closure>                 $rebounds
	 * @param CollectionStack<string,bool>                 $fetchedListenerAttributes
	 */
	final public function __construct(
		protected Stack $bindings = new Stack(),
		protected Aliases $aliases = new Aliases(),
		protected CollectionStack $contextManager = new CollectionStack(),
		protected CollectionStack $tags = new CollectionStack(),
		protected CollectionStack $rebounds = new CollectionStack(),
		protected CollectionStack $fetchedListenerAttributes = new CollectionStack(),
		protected EventManager $eventManager = new EventManager()
	) {
		EventType::registerDispatchersTo( $this->eventManager );

		$this->resolvedInstances = new Stack();
		$this->resolved          = new Stack();
		$this->dependencies      = new Param();
		$this->artefact          = new Artefact();
	}

	public static function boot(): static {
		return static::$instance ??= new static();
	}

	/** @param static $instance */
	public static function use( self $instance ): static {
		return static::$instance ??= $instance;
	}

	/** @param string $key The key. */
	public function offsetExists( $key ): bool {
		return $this->has( $key );
	}

	public function hasBinding( string $id ): bool {
		return $this->bindings->has( $id );
	}

	public function isAlias( string $id ): bool {
		return $this->aliases->has( $id, asEntry: false );
	}

	public function isInstance( string $id ): bool {
		return $this->getBinding( $id ) instanceof SharedBinding;
	}

	/** @return bool `true` if has binding or given ID is an alias, `false` otherwise. */
	public function has( string $id ): bool {
		return $this->hasBinding( $id ) ?: $this->isAlias( $id );
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

	public function getBinding( string $id ): Binding|SharedBinding|null {
		return $this->bindings[ $id ] ?? null;
	}

	/**
	 * @param callable|string     $entry Can either be a fully-qualified classname or:
	 * - `string`   -> 'classname::methodname'
	 * - `string`   -> 'classname#' . spl_object_id($classInstance) . '::methodname'
	 * - `callable` -> $classInstance->methodname(...) as first-class callable
	 * - `callable` -> array($classInstance, 'methodname').
	 * @param array<string,mixed> $args The method's injected parameters.
	 * @throws BadResolverArgument When method cannot be resolved or no `$methodName`.
	 */
	public function call( callable|string $entry, array $args = array(), ?string $methodName = null ): mixed {
		return ( new MethodResolver( $this, $this->artefact ) )
			->usingEventDispatcher( $this->eventManager->getDispatcher( EventType::Building ) )
			->withCallback( $entry, $methodName )
			->withParameter( $args )
			->resolve();
	}

	/**
	 * @param string|class-string<T>                               $id
	 * @param array<string,mixed>|ArrayAccess<object|string,mixed> $args
	 * @return ($id is class-string<T> ? T : mixed)
	 * @throws NotFoundExceptionInterface  When entry with given $id was not found in the container.
	 * @throws ContainerExceptionInterface When cannot resolve concrete from the given $id.
	 * @template T
	 */
	public function get( string $id, array|ArrayAccess $args = array() ): mixed {
		try {
			return $this->resolve( $id, $args, dispatch: true );
		} catch ( Exception $e ) {
			$this->throwContainerException( $id, $e );
		}
	}

	/** @param string $concrete Class|method name for whom {@param $typeHintOrParamName} is contextually bound. */
	public function getContextual( string $concrete, string $typeHintOrParamName ): Closure|string|null {
		return $this->contextManager->get( $concrete, $typeHintOrParamName );
	}

	/**
	 * @access private
	 * @internal This should never be used as an API to get the contextual data.
	 *           This is used internally when resolving the current artefact.
	 */
	public function getContextualFor( string $typeHintOrParamName ): Closure|string|null {
		if ( null !== ( $contextualBinding = $this->fromContextual( $typeHintOrParamName ) ) ) {
			return $contextualBinding;
		}

		if ( ! $this->aliases->has( $typeHintOrParamName, asEntry: true ) ) {
			return null;
		}

		foreach ( $this->aliases->get( $typeHintOrParamName, asEntry: true ) as $alias ) {
			if ( null !== ( $contextualBinding = $this->fromContextual( $alias ) ) ) {
				return $contextualBinding;
			}
		}

		return null;
	}

	/** @return class-string|array<class-string,class-string>|null */
	public function getResolved( string $id ): string|array|null {
		$entry = $this->getEntryFrom( $id );

		return $this->resolvedInstances[ $entry ] ?? $this->resolved[ $entry ];
	}

	public function getListenerRegistry( EventType $eventType ): ?ListenerRegistry {
		return $this->eventManager->getDispatcher( $eventType );
	}

	/**
	 * @param string|class-string               $key
	 * @param string|class-string|callable|null $value
	 */
	public function offsetSet( $key, $value ): void {
		$this->set( $key, $value );
	}

	/**
	 * @param class-string $entry
	 * @throws LogicalError When entry ID and alias is same.
	 */
	public function setAlias( string $entry, string $alias ): void {
		$this->aliases->set( $entry, $alias );
	}

	/** @param string|string[] $ids */
	public function tag( string|array $ids, string $tag, string ...$tags ): void {
		foreach ( array( $tag, ...$tags ) as $key ) {
			foreach ( Unwrap::asArray( $ids ) as $value ) {
				$this->tags->set( $key, $value );
			}
		}
	}

	/**
	 * @param string|class-string               $id
	 * @param string|class-string|callable|null $concrete
	 */
	public function set( string $id, callable|string|null $concrete = null ): void {
		$this->register( $id, concreteOrAlias: $concrete ?? $id, shared: false );
	}

	/**
	 * @param class-string               $id
	 * @param class-string|callable|null $concrete
	 */
	public function setShared( string $id, callable|string|null $concrete = null ): void {
		$this->register( $id, concreteOrAlias: $concrete ?? $id, shared: true );
	}

	/**
	 * @param T $instance
	 * @return T
	 * @template T of object
	 */
	public function setInstance( string $id, object $instance ): object {
		$hasEntry = $this->has( $id );

		$this->aliases->remove( $id );

		$this->bindings->set( $id, value: new SharedBinding( $instance ) );

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
		$this->set( id: MethodResolver::keyFrom( $entry ), concrete: $callback );
	}

	/**
	 * @param class-string $attributeName
	 * @access private
	 */
	public function setListenerFetchedFrom( string $entry, string $attributeName ): void {
		$this->fetchedListenerAttributes->set( key: $attributeName, value: true, index: $entry );
	}

	/**
	 * @param EventType|string|string[]|Closure $concrete Closure for instantiated class method binding.
	 * @return ($concrete is EventType ? EventBuilder : ContextBuilder)
	 * @throws LogicalError When unregistered Event Dispatcher provided to add event listener.
	 * @throws LogicalError When contextual $concrete is first-class callable created using a static method.
	 */
	public function when( EventType|string|array|Closure $concrete ): ContextBuilder|EventBuilder {
		return $concrete instanceof EventType
			? EventBuilder::create( $concrete, $this )
			: ContextBuilder::create( $concrete, $this, $this->contextManager );
	}

	/** @return iterable<int,object> */
	public function tagged( string $name ): iterable {
		return ! $this->tags->has( $name ) ? array() : new Generator(
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

	/** @param string $key */
	public function offsetUnset( $key ): void {
		unset( $this->bindings[ $key ], $this->resolved[ $key ], $this->resolvedInstances[ $key ] );
	}

	public function removeInstance( string $entry ): bool {
		return $this->isInstance( $entry ) && $this->bindings->remove( $entry );
	}

	public function removeResolved( string $entry ): bool {
		return $this->resolvedInstances->remove( $entry ) ?: $this->resolved->remove( $entry );
	}

	/**
	 * @param string|class-string<T>                               $id
	 * @param ArrayAccess<object|string,mixed>|array<string,mixed> $args
	 * @return ($id is class-string<T> ? T : mixed)
	 * @template T of object
	 */
	public function resolve(
		string $id,
		array|ArrayAccess $args,
		bool $dispatch,
		?ReflectionClass $reflector = null
	): mixed {
		$this->reflector = $reflector;
		$entry           = $this->getEntryFrom( $id );
		$bound           = $this->getBinding( $entry );

		if ( $bound instanceof SharedBinding ) {
			$object = $dispatch ? $this->afterBuildingInstance( $entry, $bound->material ) : $bound->material;

			unset( $this->reflector );

			return $object;
		}

		$concrete  = ! $bound ? $entry : $bound->material;
		$classname = $concrete instanceof Closure ? $entry : $concrete;

		$this->dependencies->push( $dispatch ? $this->beforeBuilding( $classname, $args ) : $args );

		$built = $this->build( $concrete, $dispatch );

		$this->dependencies->pull();

		if ( ! $this->isResolvedAsObject( $classname, $built ) ) {
			unset( $this->reflector );

			return $built;
		}

		$object = match ( $shared = $this->shouldBeShared( $bound ) ) {
			false => $dispatch ? $this->afterBuilding( $classname, $built ) : $built,
			true  => $dispatch
				? $this->afterBuildingInstance( $entry, $built, resolved: true )
				: $this->setInstance( $entry, $built )
		};

		if ( ! $shared ) {
			$this->resolved->set( key: $entry, value: $object::class );
		}

		unset( $this->reflector );

		return $object;
	}

	/**
	 * @param string|class-string          $id
	 * @param string|class-string|callable $concreteOrAlias
	 */
	protected function register( string $id, callable|string $concreteOrAlias, bool $shared ): void {
		$this->maybePurgeIfAliasOrInstance( $id );

		$material = is_callable( $concreteOrAlias ) ? $concreteOrAlias( ... ) : $this->getEntryFrom( $concreteOrAlias );

		$this->bindings->set( key: $id, value: new Binding( $material, $shared ) );

		if ( $this->hasResolved( $id ) ) {
			$this->rebound( $id );
		}
	}

	/** @param string $id The abstract or a concrete/alias. */
	protected function rebound( string $id ): void {
		$updated = $this->resolve( $id, args: array(), dispatch: false );

		foreach ( ( $this->rebounds->get( $id ) ?? array() ) as $listener ) {
			$listener( $updated, $this );
		}
	}

	/** @return iterable<string,mixed> */
	protected function getResettable(): iterable {
		return get_object_vars( $this );
	}

	/** @phpstan-assert-if-true =object $built */
	protected function isResolvedAsObject( string $concrete, mixed $built ): bool {
		return $built instanceof $concrete;
	}

	protected function shouldBeShared( ?Binding $binding ): bool {
		return $binding->isShared ?? false;
	}

	protected function fromContextual( string $constraint ): Closure|string|null {
		return ( $current = $this->artefact->latest() ) ? $this->getContextual( $current, $constraint ) : null;
	}

	protected function maybePurgeIfAliasOrInstance( string $id ): void {
		$this->removeInstance( $id );
		$this->aliases->remove( $id );
	}

	private function throwContainerException( string $id, Exception $thrown ): never {
		throw $this->has( $id ) || $thrown instanceof ContainerExceptionInterface
				? $thrown
				: EntryNotFound::for( $id, previous: $thrown );
	}

	/**
	 * @param class-string<T>|Closure $concrete
	 * @return ($concrete is class-string ? T : mixed)
	 * @throws ContainerExceptionInterface When cannot build `$concrete`.
	 * @throws ContainerError              When non-instantiable class-string `$concrete` given.
	 * @template T
	 */
	private function build( string|Closure $concrete, bool $dispatch = true ): mixed {
		$args = $this->dependencies->latest() ?? array();

		if ( $concrete instanceof Closure ) {
			return $concrete( $this, $args );
		}

		try {
			$reflector       = $this->reflector ?? Unwrap::classReflection( $concrete );
			$this->reflector = $reflector;
		} catch ( ReflectionException | LogicalError $exception ) {
			throw ContainerError::whenResolving( $concrete, $exception, $this->artefact );
		}

		if ( null === ( $constructor = $reflector->getConstructor() ) ) {
			return new $concrete();
		}

		$this->artefact->push( $concrete );

		try {
			$resolved = ( new ParamResolver( $this ) )
				->usingEventDispatcher( $dispatch ? $this->eventManager->getDispatcher( EventType::Building ) : null )
				->withParameter( $args, reflections: $constructor->getParameters() )
				->resolve();
		} catch ( ContainerExceptionInterface $e ) {
			throw $e;
		}

		$this->artefact->pull();

		return $reflector->newInstanceArgs( $resolved );
	}

	/**
	 * @param array<string,mixed>|ArrayAccess<object|string,mixed> $params
	 * @return array<string,mixed>|ArrayAccess<object|string,mixed>
	 */
	private function beforeBuilding( string $entry, array|ArrayAccess $params ): array|ArrayAccess {
		$event = $this->eventManager->getDispatcher( EventType::BeforeBuild )?->dispatch(
			new BeforeBuildEvent( $entry, $params )
		);

		return $event instanceof BeforeBuildEvent && ( $args = $event->getParams() ) ? $args : $params;
	}

	private function afterBuilding( string $eventId, object $resolved ): object {
		$dispatcher = $this->eventManager->getDispatcher( EventType::AfterBuild );
		$reflector  = $this->getResolvedObjectReflector( $eventId, $resolved );

		return AfterBuildHandler::handleWith( $this, $eventId, $resolved, $this->artefact, $reflector, $dispatcher );
	}

	private function afterBuildingInstance( string $entry, object $built, bool $resolved = false ): object {
		if ( $this->resolvedInstances->has( key: $entry ) ) {
			return $built;
		}

		$baseClass = $built::class;
		$eventId   = $resolved ? $baseClass : $entry;
		$fromEvent = $this->afterBuilding( $eventId, $built );

		$this->eventManager->getDispatcher( EventType::AfterBuild )?->reset( $eventId );
		$this->resolvedInstances->set( $entry, value: array( $baseClass => $fromEvent::class ) );

		return $this->setInstance( $entry, $fromEvent );
	}

	private function getResolvedObjectReflector( string $entry, object $resolved ): ?ReflectionClass {
		return ! $this->isListenerFetchedFrom( $entry, attributeName: DecorateWith::class )
			? $this->reflector ?? new ReflectionClass( $resolved )
			: null;
	}
}
