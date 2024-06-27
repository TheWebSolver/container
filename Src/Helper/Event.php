<?php
/**
 * The Event handler for the container.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Helper;

use Closure;
use ArrayAccess;
use TheWebSolver\Codegarage\Lib\Container\Container;

class Event {
	public const FIRE_BEFORE_BUILD = 'beforeBuild';
	public const FIRE_BUILT        = 'built';
	public const FIRE_AFTER_BUILT  = 'afterBuilt';

	/** @var Closure[] */
	private array $beforeBuild = array();

	/** @var array<string,(Closure|null)[]> */
	private array $beforeBuildForEntry = array();

	/** @var Closure[] */
	private array $built = array();

	/** @var array<string,(Closure|null)[]> */
	private array $builtForEntry = array();

	/** @var Closure[] */
	private array $afterBuilt = array();

	/** @var array<string,(Closure|null)[]> */
	private array $afterBuiltForEntry = array();

	public function __construct( private readonly Container $container ) {}

	/**
	 * @param Closure|string $id       The entry ID to make the given `$callback` scoped to it.
	 * @param ?Closure       $callback The callback to be assigned to the given entry `$id`.
	 * @param string         $when     When the resolver should resolve the callback. Possible values
	 *                                 are `CallbackResolver::FIRE_*` constants.
	 */
	public function subscribeWith( Closure|string $id, ?Closure $callback, string $when ): void {
		$entry = $this->container->resolveEntryFrom( abstract: $id );

		match ( true ) {
			default                   => $this->addTo( $when, $callback, $entry ),
			$entry instanceof Closure => null === $callback && $this->addTo(
				when: $when,
				callback: $entry,
				entry: null
			)
		};
	}

	/**
	 * @param Closure|string     $type   Generally expected to be a string. Closure is meant to be used
	 *                                   for building entry. User can technically invoke the closure
	 *                                   before build also (_NOT RECOMMENDED_).
	 * @param mixed[|ArrayAccess $params The dependency parameter values.
	 */
	public function fireBeforeBuild(
		Closure|string $type,
		array|ArrayAccess|null $params = array()
	): void {
		$this->resolve( $type, $params, callbacks: $this->beforeBuild );

		foreach ( ( $this->beforeBuildForEntry ) as $id => $callbacks ) {
			if ( $id === $type || is_subclass_of( $type, class: $id, allow_string: true ) ) {
				$this->resolve( $type, $params, $callbacks );
			}
		}
	}

	/**
	 * @param Closure|string $type     Generally expected to be a string. Closure was meant to be used
	 *                                 for building entry. User can technically invoke the closure
	 *                                 before build also (_NOT RECOMMENDED_).
	 * @param object         $resolved The resolved instance.
	 */
	public function fireAfterBuild( Closure|string $type, object $resolved ): void {
		foreach ( array( false, true ) as $after ) {
			$this->fireBuilt( $type, $resolved, $after );
		}
	}

	private function fireBuilt( Closure|string $type, object $resolved, bool $after = false ): void {
		$global = $after ? $this->afterBuilt : $this->built;
		$scoped = $after ? $this->afterBuiltForEntry : $this->builtForEntry;

		$this->resolve( type: $resolved, params: null, callbacks: $global );

		$scopedCallbacks = $this->merge( $scoped, $type, $resolved );

		$this->resolve( type: $resolved, params: null, callbacks: $scopedCallbacks );
	}

	private function addTo( string $when, ?Closure $callback, Closure|string|null $entry ): void {
		if ( $entry ) {
			$prop                      = "{$when}ForEntry";
			$this->{$prop}[ $entry ][] = $callback;

			return;
		}

		$this->{$when}[] = $callback;
	}

	/**
	 * @param object|string            $type      The entry ID, or a callback. If built, an object instance.
	 * @param ArrayAccess|mixed[]|null $params    The use provided params (only when building).
	 * @param array<?Closure>          $callbacks Registered callbacks to be fired.
	 */
	public function resolve(
		object|string $type,
		ArrayAccess|array|null $params,
		array $callbacks
	): void {
		$args = null === $params ? array( $type ) : array( $type, $params );

		foreach ( $callbacks as $callback ) {
			if ( null !== $callback ) {
				$callback( ...array( ...$args, $this->container ) );
			}
		}
	}

	/**
	 * @param array<string,array<?Closure>> $types
	 * @return array<?Closure>
	 */
	private function merge( array $types, Closure|string $given, object $resolved ): array {
		$finalCallbacks = array();

		foreach ( $types as $type => $callbacks ) {
			if ( $given === $type || $resolved instanceof $type ) {
				$finalCallbacks = array( ...$finalCallbacks, ...$callbacks );
			}
		}

		return $finalCallbacks;
	}

	private function isValidScopeBeforeBuild( string $key, string $given ): bool {
		return $given === $key || is_subclass_of( object_or_class: $given, class: $key );
	}
}
