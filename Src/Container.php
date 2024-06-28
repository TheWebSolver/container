<?php
/**
 * The container.
 *
 * @package TheWebSolver\Codegarage\Container
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
	 * All of the registered tags.
	 *
	 * @var array<array-key,array<int,string>>
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

	// phpcs:disable Squiz.Commenting.FunctionComment.SpacingAfterParamType, Squiz.Commenting.FunctionComment.ParamNameNoMatch
	/**
	 * @param Stack&ArrayAccess<string,Binding> $bindings
	 */
	// phpcs:enable Squiz.Commenting.FunctionComment.SpacingAfterParamType, Squiz.Commenting.FunctionComment.ParamNameNoMatch
	final public function __construct(
		protected readonly Stack $bindings = new Stack(),
		protected readonly Param $paramPool = new Param(),
		protected readonly Artefact $artefact = new Artefact(),
		protected readonly Aliases $aliases = new Aliases(),
		protected readonly Stack $resolved = new Stack(),
		protected readonly Contextual $contextual = new Contextual(),
		protected readonly MethodResolver $methodResolver = new MethodResolver(),
		protected readonly Stack $extenders = new Stack()
	) {
		$this->event = new Event( $this );

		$this->extenders->asCollection();
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
		return $this->bindings->has( key: $id );
	}

	public function isAlias( string $name ): bool {
		return $this->aliases->has( $name, asEntry: false );
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
		$entry = $this->isAlias( name: $id ) ? $this->getEntryFrom( alias: $id ) : $id;

		return $this->resolved->has( key: $entry ) || $this->isInstance( id: $entry );
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
		return $this->hasBinding( $id ) ? $this->bindings[ $id ] : null;
	}

	/** @return array<string,Binding>*/
	public function getBindings(): array {
		return $this->bindings->getItems();
	}

	/**
	 * Resolves the given type from the container.
	 *
	 * @param  string              $id   The entry ID or its alias.
	 * @param  mixed[]|ArrayAccess $with The callback parameters.
	 * @throws ContainerExceptionInterface When building class and cannot find using the given ID.
	 * @throws ContainerExceptionInterface When building class and cannot instantiate concrete class.
	 * @throws NotFoundExceptionInterface  When building class and primitive cannot get resolved.
	 * @since  1.0
	 */
	public function make( string $id, array|ArrayAccess $with = array() ): mixed {
		return $this->resolve( $id, $with, dispatch: true );
	}

	public function withoutEvents( string $id, array|ArrayAccess $params = array() ): mixed {
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

		if ( $this->hasContextualBinding( concrete: $value = Unwrap::callback( $callback ) ) ) {
			$this->artefact->push( $value );

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
	 *           contextual data stored to the pool to be orphaned & non-retrievable.
	 *           (unless same contextual data is used again to resolve an entry).
	 */
	public function getContextual( string $id ): Closure|string|null {
		if ( null !== ( $binding = $this->fromContextual( $id ) ) ) {
			return $binding;
		}

		if ( ! $this->aliases->has( $id, asEntry: true ) ) {
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
		$this->aliases->set( $entry, $alias );
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
	public function bind( string $id, Closure|string|null $concrete = null ): void {
		$this->register( $id, $concrete, singleton: false );
	}

	public function singleton( string $id, null|Closure|string $concrete = null ): void {
		$this->register( $id, $concrete, singleton: true );
	}

	public function instance( string $id, object $instance ): object {
		$hasEntry = $this->has( $id );

		$this->aliases->remove( $id );

		$this->bindings->set(
			key: $id,
			value: new Binding( concrete: $instance, instance: true )
		);

		if ( $hasEntry ) {
			$this->rebound( $id );
		}

		return $instance;
	}

	public function addContext( Closure|string $with, string $concrete, string $id ): void {
		$this->contextual->set( $concrete, $this->getEntryFrom( alias: $id ), $with );
	}

	// phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch, Squiz.Commenting.FunctionComment.IncorrectTypeHint -- Closure type-hint OK.
	/**
	 * @param string|(Closure(Closure|string $id, mixed[] $params, Container $container): void) $id
	 * @param ?Closure(Closure|string        $id, mixed[] $params, Container $container): void $callback
	 */
	public function subscribeBeforeBuild( Closure|string $id, ?Closure $callback = null ): void {
		$this->event->subscribeWith( $id, $callback, when: Event::FIRE_BEFORE_BUILD );
	}

	/**
	 * @param string         $id
	 * @param string         $paramName
	 * @param Closure(string $paramName): Binding $callback
	 */
	public function subscribeDuringBuild( string $id, string $paramName, Closure $callback ): void {
		$this->event->subscribeDuringBuild( $id, $paramName, $callback );
	}

	/**
	 * @param string|(Closure(string  $id, Container $container): void) $id
	 * @param ?Closure(Closure|string $id, Container $container): void $callback
	 */
	public function subscribeAfterBuild( Closure|string $id, ?Closure $callback = null ): void {
		$this->event->subscribeWith( $id, $callback, when: Event::FIRE_BUILT );
	}
	// phpcs:enable Squiz.Commenting.FunctionComment.ParamNameNoMatch, Squiz.Commenting.FunctionComment.IncorrectTypeHint

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

			throw new class( $msg ) implements ContainerExceptionInterface {};
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
			$autoWired = ( new ParamResolver( $this, $this->paramPool, $this->event ) )
				->resolve( $dependencies );
		} catch ( ContainerExceptionInterface $e ) {
			$this->artefact->pull();

			throw $e;
		}

		$this->artefact->pull();

		return $reflector->newInstanceArgs( $autoWired );
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
		$id = $this->getEntryFrom( alias: $id );

		if ( $this->isInstance( $id ) ) {
			$newInstance = new Binding(
				concrete: $closure( $this->bindings[ $id ]->concrete, $this ),
				instance: true
			);

			$this->bindings->set( key: $id, value: $newInstance );

			$this->rebound( $id );

			return;
		}

		$this->extenders->set( key: $id, value: $closure );

		if ( $this->resolved( $id ) ) {
			$this->rebound( $id );
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
		$this->bindings->remove( $key );
		$this->removeInstance( id: $key );
		$this->resolved->remove( $key );
	}

	public function removeExtenders( string $id ): void {
		$this->extenders->remove( key: $this->getEntryFrom( $id ) );
	}

	public function removeInstance( string $id ): bool {
		return $this->isInstance( $id ) && $this->bindings->remove( key: $id );
	}

	public function flush(): void {
		$this->aliases->flush();
		$this->bindings->flush();
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

	protected function register( string $id, Closure|string|null $concrete, bool $singleton ): void {
		$this->maybePurgeIfAliasOrInstance( $id );

		$concrete = ! $concrete instanceof Closure
			? AppGenerator::generateClosure( $id, concrete: $concrete ?? $id )
			: $concrete;

		$this->bindings->set( key: $id, value: new Binding( $concrete, $singleton ) );

		if ( $this->resolved( $id ) ) {
			$this->rebound( $id );
		}
	}

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
	 * @param string  $id     The entry ID.
	 * @param mixed[] $params The parameters to be auto-wired when entry is being resolved.
	 */
	protected function resolve( string $id, array $params, bool $dispatch ): mixed {
		$id = $this->getEntryFrom( alias: $id );

		if ( $dispatch ) {
			$this->event->fireBeforeBuild( $id, params: $params );
		}

		$concrete   = $this->getContextual( $id );
		$hasContext = ! empty( $params ) || null !== $concrete;

		if ( $this->isInstance( $id ) && ! $hasContext ) {
			return $this->bindings[ $id ]->concrete;
		}

		$this->paramPool->push( value: $params );

		$resolved = $this->build( $concrete ?? $this->getConcrete( $id ) );

		foreach ( $this->getExtenders( $id ) as $extender ) {
			$resolved = $extender( $resolved, $this );
		}

		if ( $this->isSingleton( $id ) && ! $hasContext ) {
			$this->bindings->set(
				key: $id,
				value: new Binding( concrete: $resolved, instance: true )
			);
		}

		if ( $dispatch && is_object( $resolved ) ) {
			$this->event->fireAfterBuild( $id, $resolved );
		}

		$this->resolved->set( key: $id, value: true );

		$this->paramPool->pull();

		return $resolved;
	}

	protected function getConcrete( string $id ): Closure|string {
		if ( ! $this->hasBinding( $id ) ) {
			return $id;
		}

		$concrete = $this->bindings[ $id ]->concrete;

		return $concrete instanceof Closure ? $concrete : $id;
	}

	protected function fromContextual( string $id ): Closure|string|null {
		return ! empty( $artefact = $this->artefact->latest() )
			? $this->contextual->get( $artefact, key: $id )
			: null;
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
		if ( $id instanceof Closure ) {
			return array();
		}

		$entry = $this->getEntryFrom( $id );

		if ( ! $this->extenders->has( key: $entry ) ) {
			return array();
		}

		return $this->extenders->get( item: $entry );
	}

	protected function maybePurgeIfAliasOrInstance( string $id ): void {
		$this->removeInstance( $id );
		$this->aliases->remove( $id );
	}
}
