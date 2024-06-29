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
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Pool\Eventual;
use TheWebSolver\Codegarage\Lib\Container\Pool\IndexStack;

readonly class Event {
	public const FIRE_BEFORE_BUILD = 'beforeBuild';
	public const FIRE_BUILT        = 'built';

	public function __construct(
		private Container $container,
		private Stack $bindings,
		private IndexStack $beforeBuild = new IndexStack(),
		private Stack $beforeBuildForEntry = new Stack(),
		private IndexStack $built = new IndexStack(),
		private Stack $builtForEntry = new Stack(),
		private Eventual $building = new Eventual()
	) {
		$this->beforeBuildForEntry->asCollection();
		$this->builtForEntry->asCollection();
	}

	/**
	 * @param Closure|string $id       The entry ID to make the given `$callback` scoped to it.
	 * @param ?Closure       $callback The callback to be assigned to the given entry `$id`.
	 * @param string         $when     When the resolver should resolve the callback. Possible values
	 *                                 are `CallbackResolver::FIRE_*` constants.
	 */
	public function subscribeWith( Closure|string $id, ?Closure $callback, string $when ): void {
		$entry = $id instanceof Closure ? $id : $this->container->getEntryFrom( alias: $id );

		match ( true ) {
			default                   => $this->addTo( $when, $callback, $entry ),
			$entry instanceof Closure => null === $callback && $this->addTo( $when, $entry, entry: null )
		};
	}

	public function subscribeDuringBuild(
		string $id,
		string $dependencyName,
		Binding|Closure $implementation
	): void {
		$this->building->set(
			artefact: $this->container->getEntryFrom( $id ),
			dependency: $dependencyName,
			implementation: $implementation
		);
	}

	/**
	 * @param string              $id     The entry ID.
	 * @param mixed[]|ArrayAccess $params The dependency parameter values.
	 */
	public function fireBeforeBuild( string $id, array|ArrayAccess|null $params = array() ): void {
		$this->resolve( $id, $params, callbacks: $this->beforeBuild->getItems() );

		foreach ( $this->beforeBuildForEntry->getItems() as $entry => $callbacks ) {
			if ( $entry === $id || is_subclass_of( $id, class: $entry, allow_string: true ) ) {
				$this->resolve( $id, $params, $callbacks );
			}
		}
	}

	public function fireDuringBuild( string $id, string $paramName ): ?Binding {
		if ( $this->bindings->has( $paramName ) ) {
			return $this->bindings->get( $paramName );
		}

		if ( ! $this->building->has( $id, $paramName ) ) {
			return null;
		}

		$given   = $this->building->get( $id, $paramName );
		$binding = $given instanceof Binding ? $given : $given( $paramName, $this->container );

		if ( $binding?->isInstance() ) {
			$this->bindings->set( key: $paramName, value: $binding );
		}

		$this->building->remove( $id, $paramName );

		return $binding;
	}

	/**
	 * @param string $id       The entry ID.
	 * @param object $resolved The resolved instance.
	 */
	public function fireAfterBuild( string $id, object $resolved ): void {
		$this->fireBuilt( $id, $resolved );
	}

	private function fireBuilt( string $id, object $resolved ): void {
		$this->resolve( type: $resolved, params: null, callbacks: $this->built->getItems() );

		$scopedCallbacks = $this->merge( $this->builtForEntry->getItems(), $id, $resolved );

		$this->resolve( type: $resolved, params: null, callbacks: $scopedCallbacks );
	}

	private function addTo( string $when, ?Closure $callback, Closure|string|null $entry ): void {
		if ( $entry ) {
			$prop = "{$when}ForEntry";

			$this->{$prop}->set( $entry, $callback );

			return;
		}

		$this->{$when}->set( $callback );
	}

	/**
	 * @param object|string            $type      The entry ID, or a callback. If built, an object instance.
	 * @param ArrayAccess|mixed[]|null $params    The use provided params (only when building).
	 * @param array<?Closure>          $callbacks Registered callbacks to be fired.
	 */
	private function resolve(
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
}
