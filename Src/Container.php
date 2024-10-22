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
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container;

use Closure;
use Exception;
use ArrayAccess;
use LogicException;
use ReflectionClass;
use ReflectionException;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use TheWebSolver\Codegarage\Lib\Container\Pool\Param;
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;
use TheWebSolver\Codegarage\Lib\Container\Data\Aliases;
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Helper\Event;
use TheWebSolver\Codegarage\Lib\Container\Helper\Unwrap;
use TheWebSolver\Codegarage\Lib\Container\Pool\Artefact;
use TheWebSolver\Codegarage\Lib\Container\Event\EventType;
use TheWebSolver\Codegarage\Lib\Container\Helper\Generator;
use TheWebSolver\Codegarage\Lib\Container\Error\EntryNotFound;
use TheWebSolver\Codegarage\Lib\Container\Helper\EventBuilder;
use TheWebSolver\Codegarage\Lib\Container\Error\ContainerError;
use TheWebSolver\Codegarage\Lib\Container\Helper\ParamResolver;
use TheWebSolver\Codegarage\Lib\Container\Event\EventDispatcher;
use TheWebSolver\Codegarage\Lib\Container\Helper\ContextBuilder;
use TheWebSolver\Codegarage\Lib\Container\Helper\MethodResolver;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\Resettable;
use TheWebSolver\Codegarage\Lib\Container\Event\BeforeBuildEvent;
use TheWebSolver\Codegarage\Lib\Container\Event\BuildingProvider;
use TheWebSolver\Codegarage\Lib\Container\Error\BadResolverArgument;
use TheWebSolver\Codegarage\Lib\Container\Event\BeforeBuildProvider;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\ListenerRegistry;

class Container implements ArrayAccess, ContainerInterface, Resettable {
	/** @var ?static */
	protected static $instance;
	protected readonly Event $event;
	protected readonly MethodResolver $methodResolver;
	private EventDispatcherInterface&ListenerRegistry $buildingDispatcher;
	protected EventDispatcherInterface&ListenerRegistry $beforeBuildDispatcher;

	// phpcs:disable Squiz.Commenting.FunctionComment.SpacingAfterParamType
	/**
	 * @param Stack&ArrayAccess<string,Binding>                           $bindings
	 * @param Stack&ArrayAccess<string,array<string,Closure|string|null>> $contextual
	 * @param Stack&ArrayAccess<string,array<int,string>>                 $tags
	 * @param Stack&ArrayAccess<string,Closure[]>                         $rebounds
	 * @param Stack&ArrayAccess<string,Closure[]>                         $extenders
	 */
	// phpcs:enable Squiz.Commenting.FunctionComment.SpacingAfterParamType
	final public function __construct(
		EventDispatcherInterface&ListenerRegistry $beforeBuildEventDispatcher = null,
		EventDispatcherInterface&ListenerRegistry $buildingEventDispatcher = null,
		protected readonly Stack $bindings = new Stack(),
		protected readonly Param $paramPool = new Param(),
		protected readonly Artefact $artefact = new Artefact(),
		protected readonly Aliases $aliases = new Aliases(),
		protected readonly Stack $resolved = new Stack(),
		protected readonly Stack $contextual = new Stack(),
		protected readonly Stack $extenders = new Stack(),
		protected readonly Stack $tags = new Stack(),
		protected readonly Stack $rebounds = new Stack(),
	) {
		$this->beforeBuildDispatcher = $beforeBuildEventDispatcher ?? new EventDispatcher( new BeforeBuildProvider() );
		$this->buildingDispatcher    = $buildingEventDispatcher ?? new EventDispatcher( new BuildingProvider() );
		$this->event                 = new Event( $this, $bindings );
		$this->methodResolver        = new MethodResolver( $this, $this->buildingDispatcher, $artefact );

		$this->extenders->asCollection();
		$this->rebounds->asCollection();
		$this->tags->asCollection();
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
		return $this->methodResolver->resolve( $callback, $defaultMethod, $params );
	}

	/**
	 * @param ArrayAccess|array<string,mixed> $with The injected parameters.
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

	/*
	 |================================================================================================
	 | SETTER METHODS
	 |================================================================================================
	 */

	/**
	 * @param string $key
	 * @param mixed  $value
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

		$this->bindings[ $id ] = new Binding( concrete: $instance, instance: true );

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

	/** @param string|string[]|Closure $concrete */
	public function when( string|array|Closure $concrete ): ContextBuilder {
		$concrete = $concrete instanceof Closure ? Unwrap::callback( cb: $concrete ) : $concrete;
		$ids      = array_map( $this->getEntryFrom( ... ), array: Unwrap::asArray( $concrete ) );

		return new ContextBuilder( for: $ids, app: $this, contextual: $this->contextual );
	}

	public function whenEvent( EventType $type ): EventBuilder {
		$registry = match ( $type ) {
			EventType::BeforeBuild => $this->beforeBuildDispatcher,
			EventType::Building    => $this->buildingDispatcher,
		};

		return new EventBuilder( $registry, $type, app: $this );
	}

	public function build( Closure|string $with ): mixed {
		if ( $with instanceof Closure ) {
			return $with( $this, $this->paramPool->latest() );
		}

		try {
			$reflector = new ReflectionClass( $with );
		} catch ( ReflectionException $error ) {
			throw ContainerError::unResolvableEntry( id: $with, previous: $error );
		}

		if ( ! $reflector->isInstantiable() ) {
			throw ContainerError::unInstantiableEntry( id: $with, artefact: $this->artefact );
		}

		if ( null === ( $constructor = $reflector->getConstructor() ) ) {
			return new $with();
		}

		$this->artefact->push( value: $with );

		try {
			$resolver = new ParamResolver( $this, $this->paramPool, $this->buildingDispatcher );
			$resolved = $resolver->resolve( dependencies: $constructor->getParameters() );
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
			count: $this->tags->withKey( $name ),
			generator: function () use ( $name ) {
				foreach ( $this->tags[ $name ] as $id ) {
					yield $this->get( $id );
				}
			},
		);
	}

	public function extend( string $id, Closure $closure ): void {
		$id = $this->getEntryFrom( alias: $id );

		if ( $this->isInstance( $id ) ) {
			$this->bindings[ $id ] = new Binding(
				concrete: $closure( $this->bindings[ $id ]->concrete, $this ),
				instance: true
			);

			$this->rebound( $id );

			return;
		}

		$this->extenders[ $id ] = $closure;

		if ( $this->resolved( $id ) ) {
			$this->rebound( $id );
		}
	}

	/**
	 * Invokes `$with` callback when bound `$id` is updated again using any of the binding methods
	 * such as `Container::set()`, `Container::singleton()` & `Container::instance()`.
	 *
	 * @param Closure(object $obj, Container $app): ?obj $with
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

	public function reset(): void {
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

	protected function register( string $id, Closure|string|null $concrete, bool $singleton ): void {
		$this->maybePurgeIfAliasOrInstance( $id );

		$concrete              = Generator::generateClosure( $id, concrete: $concrete ?? $id );
		$this->bindings[ $id ] = new Binding( $concrete, $singleton );

		if ( $this->resolved( $id ) ) {
			$this->rebound( $id );
		}
	}

	/** @param ArrayAccess|array<string,mixed> $with */
	protected function resolve( string $id, array|ArrayAccess $with, bool $dispatch ): mixed {
		$id = $this->getEntryFrom( alias: $id );

		if ( $dispatch ) {
			/** @var BeforeBuildEvent */
			$event = $this->beforeBuildDispatcher->dispatch( new BeforeBuildEvent( $id, params: $with ) );
			$with  = $event->getParams();
		}

		$contextual = $this->getContextualFor( context: $id );
		$needsBuild = ! empty( $with ) || null !== $contextual;

		if ( $this->isInstance( $id ) && ! $needsBuild ) {
			return $this->bindings[ $id ]->concrete;
		}

		$this->paramPool->push( value: $with );

		$resolved = $this->build( with: $contextual ?? $this->getConcrete( $id ) );

		foreach ( ( $this->extenders[ $id ] ?? array() ) as $extender ) {
			$resolved = $extender( $resolved, $this );
		}

		if ( true === $this->getBinding( $id )?->isSingleton() && ! $needsBuild ) {
			$this->bindings[ $id ] = new Binding( concrete: $resolved, instance: true );
		}

		if ( $dispatch && is_object( value: $resolved ) ) {
			$this->event->fireAfterBuild( $id, $resolved );
		}

		$this->resolved->set( key: $id, value: true );

		$this->paramPool->pull();

		return $resolved;
	}

	protected function getConcrete( string $id ): Closure|string {
		return ! $this->hasBinding( $id )
			? $id
			: ( ( $closure = $this->bindings[ $id ]->concrete ) instanceof Closure ? $closure : $id );
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
