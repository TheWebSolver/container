<?php
/**
 * The container.
 *
 * @package TheWebSolver\Codegarage\App
 *
 * @phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing
 * @phpcs:disable Squiz.Commenting.FunctionComment.WrongStyle
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container;

use Closure;
use WeakMap;
use Exception;
use Generator;
use ArrayAccess;
use LogicException;
use ReflectionClass;
use ReflectionException;
use InvalidArgumentException;
use Container_Entry_Exception;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use TheWebSolver\Codegarage\Lib\Container\Pool\Bind;
use TheWebSolver\Codegarage\Lib\Container\Pool\Param;
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;
use TheWebSolver\Codegarage\Lib\Container\Data\Aliases;
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Helper\Event;
use TheWebSolver\Codegarage\Lib\Container\Helper\Unwrap;
use TheWebSolver\Codegarage\Lib\Container\Pool\Artefact;
use TheWebSolver\Codegarage\Lib\Container\Pool\Contextual;
use TheWebSolver\Codegarage\Exceptions\Container_Exception;
use TheWebSolver\Codegarage\Lib\Container\Helper\ParamResolver;
use TheWebSolver\Codegarage\Lib\Container\Helper\ContextBuilder;
use TheWebSolver\Codegarage\Lib\Container\Helper\MethodResolver;
use TheWebSolver\Codegarage\Lib\Container\Helper\Generator as AppGenerator;

class Container implements ArrayAccess, ContainerInterface {
	protected static Container $instance;

	/**
	 * The container's scoped instances.
	 *
	 * TODO: Check if it is needed.
	 *
	 * @var string[]
	 */
	protected array $scoped_instances = array();

	/**
	 * The extension closures for services.
	 *
	 * @var array<string, Closure[]>
	 */
	protected array $extenders = array();

	/**
	 * All of the registered tags.
	 *
	 * @var array<array-key, array<int, string>>
	 */
	protected array $tags = array();

	/**
	 * All of the registered rebound callbacks.
	 *
	 * @var array<string,Closure[]>
	 */
	protected array $rebound_callbacks = array();

	/** @var array<string,array<string,Closure(string $paramName)>> */
	private array $during_resolving;

	protected readonly Event $event;

	final public function __construct(
		protected readonly Param $paramPool = new Param(),
		protected readonly Artefact $artefact = new Artefact(),
		public readonly Aliases $aliases = new Aliases(),
		protected readonly Bind $bindPool = new Bind(),
		protected readonly Stack $resolved = new Stack(),
		protected readonly Contextual $contextual = new Contextual(),
		protected readonly MethodResolver $methodResolver = new MethodResolver()
	) {
		$this->event = new Event( $this );
	}

	public static function boot(): static {
		return static::$instance ??= new static();
	}

	/*
	 |================================================================================================
	 |
	 | ASSERTION METHODS
	 |
	 |================================================================================================
	 */

	/** @param string $key The key. */
	public function offsetExists( $key ): bool {
		return $this->has( $key );
	}

	public function hasBinding( string $id ): bool {
		return $this->bindPool->has( key: $id );
	}

	public function isAlias( string $name ): bool {
		return $this->aliases->exists( $name, asEntry: false );
	}

	public function isSingleton( string $id ): bool {
		return ( ! $binding = $this->getBinding( $id ) ) ? false : $binding->isSingleton();
	}

	public function isInstance( string $id ): bool {
		return ( ! $binding = $this->getBinding( $id ) ) ? false : $binding->isInstance();
	}

	/** @return bool `true` if has binding or given ID is an alias, `false` otherwise. */
	public function has( string $entryOrAlias ): bool {
		return $this->hasBinding( $entryOrAlias ) || $this->isAlias( $entryOrAlias );
	}

	public function resolved( string $id ): bool {
		if ( $this->isAlias( name: $id ) ) {
			$id = $this->getEntryFrom( alias: $id );
		}

		return $this->resolved->has( key: $id ) || $this->isInstance( $id );
	}

	public function isShared( string $id ): bool {
		return $this->isInstance( $id ) || $this->isSingleton( $id );
	}

	public function hasContextualBinding( string $concrete ): bool {
		return $this->contextual->has( artefact: $concrete );
	}

	/*
	 |================================================================================================
	 |
	 | GETTER METHODS
	 |
	 |================================================================================================
	 */

	/** @param string $key The key. */
	#[\ReturnTypeWillChange]
	public function offsetGet( $key ): mixed {
		return $this->make( $key );
	}

	/** @return string Returns alias itself if cannot find any entry related to the given alias. */
	public function getEntryFrom( string $alias ): string {
		return $this->aliases->get( id: $alias, asEntry: false );
	}

	public function getBinding( string $id ): ?Binding {
		return $this->hasBinding( $id ) ? $this->bindPool->get( key: $id ) : null;
	}

	/** @return array<string,Binding>*/
	public function getBindings(): array {
		return $this->bindPool->getItems();
	}

	public function resolveEntryFrom( Closure|string $abstract ): Closure|string {
		return $abstract instanceof Closure ? $abstract : $this->getEntryFrom( alias: $abstract );
	}

	/**
	 * Resolves the given type from the container.
	 *
	 * @param  Closure|string      $id   The entry ID or a callback.
	 * @param  mixed[]|ArrayAccess $with The callback parameters.
	 * @throws ContainerExceptionInterface When building class and cannot find using the given ID.
	 * @throws ContainerExceptionInterface When building class and cannot instantiate concrete class.
	 * @throws NotFoundExceptionInterface  When building class and primitive cannot get resolved.
	 * @since  1.0
	 */
	public function make( Closure|string $id, array|ArrayAccess $with = array() ): mixed {
		return $this->resolve( $id, $with, dispatch: true );
	}

	public function withoutEvents( Closure|string $id, array|ArrayAccess $params = array() ): mixed {
		return $this->resolve( $id, $params, dispatch: false );
	}

	/**
	 * @param  callable|callable-string $callback The callback.
	 * @param  mixed[]                  $params   The callback parameters.
	 * @throws InvalidArgumentException If invalid argument passed.
	 */
	public function call(
		callable|string $callback,
		array $params = array(),
		?string $defaultMethod = null
	): mixed {
		$isBuilding = false;

		if ( $this->hasContextualBinding( $id = Unwrap::callback( $callback ) ) ) {
			$this->artefact->push( value: $id );

			$result = $this->methodResolver->resolveContextual( $this, $callback );

			$this->artefact->pull();

			return $result;
		} elseif ( is_array( $callback ) ) {
			$class = $callback[0];
			$name  = is_string( $class ) ? $class : $class::class;

			if ( ! $this->artefact->has( value: $name ) ) {
				$this->artefact->push( value: $name );
			}

			$isBuilding = true;
		}//end if

		$result = $this->methodResolver->resolve( $this, $callback, $params, $defaultMethod );

		if ( $isBuilding ) {
			$this->artefact->pull();
		}

		return $result;
	}

	/**
	 * @throws ContainerExceptionInterface When $id can't resolve anything.
	 * @throws NotFoundExceptionInterface  When entry identifier not found.
	 * @since  1.0
	 */
	public function get( string $id ): mixed {
		try {
			return $this->make( $id );
		} catch ( Exception $e ) {
			if ( $this->has( $id ) || $e instanceof ContainerExceptionInterface ) {
				throw $e;
			}

			throw new Container_Entry_Exception( $id, Container_Entry_Exception::code( $e->getCode() ), $e );
		}
	}

	/**
	 * @access private
	 * @internal This should never be used as an API to get the contextual data. Contextual
	 *           data becomes invalidated as soon as entry is resolved coz the respective
	 *           entry (artefact) is pulled immediately from the stack which makes the
	 *           contextual data stored to the pool to be orphaned and unretrievable.
	 *           (unless same contextual data is used again to resolve an entry).
	 */
	public function getContextual( Closure|string $id ): Closure|string|null {
		if ( null !== ( $binding = $this->fromContextual( $id ) ) ) {
			return $binding;
		}

		if ( $id instanceof Closure || $this->aliases->exists( $id, asEntry: true ) ) {
			return null;
		}

		foreach ( $this->aliases->get( $id, asEntry: true ) as $alias ) {
			if ( null !== ( $binding = $this->fromContextual( $alias ) ) ) {
				return $binding;
			}
		}

		return null;
	}

	/*
	 |================================================================================================
	 |
	 | SETTER METHODS
	 |
	 |================================================================================================
	 */

	/**
	 * @param string $key
	 * @param mixed  $value
	 */
	public function offsetSet( $key, $value ): void {
		$this->bind( $key, $value );
	}

	/** @throws LogicException When entry ID and alias is same. */
	public function alias( string $entry, string $alias ): void {
		$this->aliases->add( $entry, $alias );
	}

	/** @param string|string[] $ids */
	public function tag( string|array $ids, string $tag, string ...$tags ): void {
		foreach ( array( $tag, ...$tags ) as $tag ) {
			// TODO: add tag stack.
			$this->tags[ $tag ] ??= array();

			foreach ( (array) $ids as $id ) {
				$this->tags[ $tag ][] = $id;
			}
		}
	}

	/**
	 * @param string $id If classname was previously aliased, it is recommended to use the classname
	 *                   instead of an alias as ID to prevent that alias from being purged.
	 */
	public function bind(
		string $id,
		null|Closure|string $concrete = null,
		bool $singleton = false
	): void {
		$this->maybePurgeIfAliasOrInstance( $id );

		$concrete = ! $concrete instanceof Closure
			? AppGenerator::generateClosure( $id, concrete: $concrete ?? $id )
			: $concrete;

		$this->bindPool->set( key: $id, value: new Binding( $concrete, $singleton ) );

		if ( $this->resolved( $id ) ) {
			$this->rebound( $id );
		}
	}

	public function singleton( string $id, null|Closure|string $concrete = null ): void {
		$this->bind( $id, $concrete, singleton: true );
	}

	public function instance( string $id, object $instance ): object {
		$hasEntry = $this->has( $id );

		$this->aliases->remove( $id );

		$this->bindPool->set(
			key: $id,
			value: new Binding( concrete: $instance, singleton: false, instance: true )
		);

		if ( $hasEntry ) {
			$this->rebound( $id );
		}

		return $instance;
	}

	public function scoped( string $id, null|Closure|string $concrete = null ): void {
		$this->scoped_instances[] = $id;

		$this->singleton( $id, $concrete );
	}

	public function addContext(
		string $concrete,
		string $id,
		Closure|string $implementation
	): void {
		$entry = '$' . ltrim( string: $this->getEntryFrom( alias: $id ), characters: '$' );

		$this->contextual->set( $concrete, $entry, $implementation );
	}

	// phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch -- Closure type-hint OK.
	/**
	 * @param string|(Closure(Closure|string $idOrClosure, mixed[] $params, Container $container): void) $id
	 *                       The entry ID to fire event for the particular entry, or closure to fire
	 *                       event for every resolving entry. When closure is passed, `$callback`
	 *                       parameter value must not be passed (must be null).
	 * @param ?Closure(Closure|string        $idOrClosure, mixed[] $params, Container $container): void $callback
	 *                              The closure to use when {@param `$id`} is an entry ID making
	 *                              closure scoped to the given entry ID.
	 */
	public function beforeResolving( Closure|string $id, ?Closure $callback = null ): void {
		$this->event->subscribeWith( $id, $callback, when: Event::FIRE_BEFORE_BUILD );
	}

	/**
	 * @param string|(Closure(string  $id, Container $container): void) $id
	 *                        The entry ID to fire event for the particular entry, or closure to fire
	 *                        event for every resolved entry. When closure is passed, `$callback`
	 *                        parameter value must not be passed (must be null).
	 * @param ?Closure(Closure|string $id, Container $container): void $callback
	 *                       The closure to use when {@param `$id`} is an entry ID making
	 *                       closure scoped to the given entry ID.
	 */
	public function resolving( Closure|string $id, ?Closure $callback = null ): void {
		$this->event->subscribeWith( $id, $callback, when: Event::FIRE_BUILT );
	}

	/**
	 * @param string|(Closure(string  $id, Container $container): void) $id
	 *                        The entry ID to fire event for the particular entry, or closure to fire
	 *                        event for every resolved entry. When closure is passed, `$callback`
	 *                        parameter value must not be passed (must be null).
	 * @param ?Closure(Closure|string $id, Container $container): void $callback
	 *                       The closure to use when {@param `$id`} is an entry ID making
	 *                       closure scoped to the given entry ID.
	 */
	public function afterResolving( Closure|string $id, ?Closure $callback = null ): void {
		$this->event->subscribeWith( $id, $callback, when: Event::FIRE_AFTER_BUILT );
	}
	// phpcs:enable Squiz.Commenting.FunctionComment.ParamNameNoMatch

	/*
	 |================================================================================================
	 |
	 | CREATOR METHODS
	 |
	 |================================================================================================
	 */

	/** @param string|string[] $concrete */
	public function when( string|array $concrete ): ContextBuilder {
		return new ContextBuilder(
			for: array_map( callback: $this->getEntryFrom( ... ), array: Unwrap::asArray( $concrete ) ),
			container: $this
		);
	}

	public function build( Closure|string $concrete ): mixed {
		if ( $concrete instanceof Closure ) {
			return $concrete( $this, $this->paramPool->latest() );
		}

		try {
			$reflector = new ReflectionClass( $concrete );
		} catch ( ReflectionException $e ) {
			// TODO: Add appropriate exception handler.
			$msg = sprintf( 'Target class: "%s" does not exist', $concrete );

			throw new class( $msg, 0, $e ) implements ContainerExceptionInterface {};
		}

		if ( ! $reflector->isInstantiable() ) {
			$this->notInstantiable( $concrete );
		}

		if ( null === ( $constructor = $reflector->getConstructor() ) ) {
			return new $concrete();
		}

		$this->artefact->push( value: $concrete );

		$dependencies = $constructor->getParameters();

		try {
			$resolved = ( new ParamResolver( $this, $this->paramPool ) )->resolve( $dependencies );
		} catch ( ContainerExceptionInterface $e ) {
			$this->artefact->pull();

			throw $e;
		}

		$this->artefact->pull();

		return $reflector->newInstanceArgs( $resolved );
	}

	public function setDuringResolving( string $type, string $paramName, Closure $resolver ): void {
		$this->during_resolving[ $type ][ $paramName ] = $resolver;
	}

	/**
	 * Resolves the given concretes without registering to the container.
	 *
	 * @param  array<string|object> $concretes The concretes to resolve or already resolved?.
	 * @return array<object> The resolved objects.
	 */
	public function resolveOnce( array $concretes ): array {
		return iterator_to_array(
			new AppGenerator(
				generator: fn(): Generator => AppGenerator::generate( $concretes, $this ),
				count: count( $concretes )
			)
		);
	}

	/** @return iterable<int,object> */
	public function tagged( string $name ) {
		if ( ! isset( $this->tags[ $name ] ) ) {
			return array();
		}

		return new AppGenerator(
			count: count( $this->tags[ $name ] ),
			generator: function () use ( $name ) {
				foreach ( $this->tags[ $name ] as $id ) {
					yield $this->make( $id );
				}
			},
		);
	}

	/** @throws InvalidArgumentException When invalid arg passed. */
	public function extend( string $id, Closure $closure ): void {
		$entry = $this->getEntryFrom( $id );

		if ( $this->isInstance( $entry ) ) {
			$newInstance = new Binding(
				concrete: $closure( $this->bindPool->get( key: $entry )->concrete, $this ),
				singleton: false,
				instance: true
			);

			$this->bindPool->set( key: $entry, value: $newInstance );

			$this->rebound( $entry );

			return;
		}

		// TODO: add extenders pool.
		$this->extenders[ $entry ][] = $closure;

		if ( $this->resolved( $entry ) ) {
			$this->rebound( $entry );
		}
	}

	public function rebinding( string $id, Closure $callback ) {
		$this->rebound_callbacks[ $id = $this->getEntryFrom( $id ) ][] = $callback;

		// TODO: check why nothing is returned if not bound.
		if ( $this->has( $id ) ) {
			return $this->make( $id );
		}
	}

	public function refresh( string $id, mixed $target, string $method ) {
		return $this->rebinding(
			id: $id,
			callback: static fn ( $app, $instance ) => $target->{$method}( $instance )
		);
	}

	/*
	 |================================================================================================
	 |
	 | DESTRUCTOR METHODS
	 |
	 |================================================================================================
	 */

	/** @param string $key */
	public function offsetUnset( $key ): void {
		$this->bindPool->remove( $key );
		$this->removeInstance( id: $key );
		$this->resolved->remove( $key );
	}

	public function removeExtenders( string $id ): void {
		unset( $this->extenders[ $this->getEntryFrom( $id ) ] );
	}

	public function removeInstance( string $id ): void {
		$this->isInstance( $id ) && $this->bindPool->remove( key: $id );
	}

	public function removeScoped(): void {
		foreach ( $this->scoped_instances as $scoped ) {
			$this->removeInstance( id: $scoped );
		}
	}

	public function flush(): void {
		$this->scoped_instances = array();

		$this->aliases->flush();
		$this->bindPool->flush();
		$this->paramPool->flush();
		$this->artefact->flush();
		$this->contextual->flush();
		$this->resolved->flush();
	}

	/*
	 |================================================================================================
	 |
	 | HELPER METHODS
	 |
	 |================================================================================================
	 */

	protected function rebound( string $id ): void {
		$instance = $this->make( $id );

		foreach ( $this->getRebounds( $id ) as $callback ) {
			$callback( $this, $instance );
		}
	}

	/** @return Closure[] */
	protected function getRebounds( string $id ): array {
		return $this->rebound_callbacks[ $id ] ?? array();
	}

	/**
	 * @param Closure|string $id     The entry ID or a callback to resolve the given ID.
	 * @param mixed[]        $params The parameters to be auto-wired when entry is being resolved.
	 */
	protected function resolve(
		Closure|string $id,
		array $params = array(),
		bool $dispatch = false
	): mixed {
		if ( ! $id instanceof Closure ) {
			$id = $this->getEntryFrom( alias: $id );
		}

		if ( $dispatch ) {
			$this->event->fireBeforeBuild( type: $id, params: $params );
		}

		$concrete   = $this->getContextual( $id );
		$hasContext = ! empty( $params ) || null !== $concrete;

		if ( ! $id instanceof Closure && $this->isInstance( $id ) && ! $hasContext ) {
			return $this->bindPool->get( key: $id )->concrete;
		}

		$this->paramPool->push( value: $params );

		$concrete ??= $this->getConcrete( $id );
		$resolved   = $this->isBuildable( $concrete, $id )
			? $this->build( $concrete )
			: $this->make( $concrete );

		foreach ( $this->getExtenders( $id ) as $extender ) {
			$resolved = $extender( $resolved, $this );
		}

		if ( ! $id instanceof Closure && $this->isShared( $id ) && ! $hasContext ) {
			$this->bindPool->set(
				key: $id,
				value: new Binding( concrete: $resolved, singleton: false, instance: true )
			);
		}

		if ( $dispatch && is_object( $resolved ) ) {
			$this->event->fireAfterBuild( $id, $resolved );
		}

		$this->resolved->set( key: $id, value: true );

		$this->paramPool->pull();

		return $resolved;
	}

	protected function getConcrete( Closure|string $id ): object|string {
		return is_string( $id ) && $this->hasBinding( $id )
			? $this->bindPool->get( key: $id )->concrete
			: $id;
	}

	protected function fromContextual( Closure|string $id ): Closure|string|null {
		if ( $id instanceof Closure ) {
			return $id;
		}

		$artefact = $this->artefact->latest();

		if ( empty( $artefact ) ) {
			return null;
		}

		return $this->contextual->get( artefact: $artefact, key: $id );
	}

	protected function isBuildable( Closure|string $concrete, Closure|string $id ): bool {
		return $concrete === $id || $concrete instanceof Closure;
	}

	protected function notInstantiable( string $concrete ): void {
		$msg = $this->artefact->hasItems() ? "while building \"[%s]\" {$this->artefact}" : '';

		throw new Container_Exception(
			sprintf( Container_Exception::NON_INSTANTIABLE, '"' . $concrete . '" ' . $msg )
		);
	}

	// TODO: check if it returns array or a Closure.
	/** @return array<int,Closure> */
	protected function getExtenders( Closure|string $id ): array {
		return $id instanceof Closure ? array() : (
			$this->extenders[ $this->getEntryFrom( $id ) ] ?? array()
		);
	}

	protected function maybePurgeIfAliasOrInstance( string $id ): void {
		$this->removeInstance( $id );
		$this->aliases->remove( $id );
	}
}
