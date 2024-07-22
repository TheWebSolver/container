<?php
/**
 * Event handler for the container.
 *
 * @package TheWebSolver\Codegarage\Container
 *
 * @phpcs:disable Squiz.Commenting.FunctionComment.SpacingAfterParamType
 * @phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
 * @phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Helper;

use Closure;
use ArrayAccess;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Pool\IndexStack;

class Event {
	public const FIRE_BEFORE_BUILD = 'beforeBuild';
	public const FIRE_BUILT        = 'built';

	/** @param Stack&ArrayAccess<string,array<string,Binding|Closure(string $param, Container $app): Binding> $building */
	public function __construct(
		private readonly Container $app,
		private readonly Stack $bindings,
		private readonly IndexStack $beforeBuild = new IndexStack(),
		private readonly Stack $beforeBuildForEntry = new Stack(),
		private readonly IndexStack $built = new IndexStack(),
		private readonly Stack $builtForEntry = new Stack(),
		private readonly Stack $building = new Stack()
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
		$entry = $id instanceof Closure ? $id : $this->app->getEntryFrom( alias: $id );

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
			key: Stack::keyFrom( id: $this->app->getEntryFrom( alias: $id ), name: $dependencyName ),
			value: $implementation
		);
	}

	/**
	 * @param string              $id     The entry ID.
	 * @param mixed[]|ArrayAccess $params The dependency parameter values.
	 */
	public function fireBeforeBuild( string $id, array|ArrayAccess|null $params = array() ): void {
		$this->resolve( $id, $params, cbs: $this->beforeBuild->getItems() );

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

		$key = Stack::keyFrom( id: $this->app->getEntryFrom( $id ), name: $paramName );

		if ( ! $this->building->has( $key ) ) {
			return null;
		}

		$binding = Unwrap::andInvoke( $this->building[ $id ][ $paramName ], $paramName, $this->app );

		if ( $binding?->isInstance() ) {
			$this->bindings->set( key: $paramName, value: $binding );
		}

		$this->building->remove( $key );

		return $binding;
	}

	public function fireAfterBuild( string $id, object $resolved ): void {
		$this->fireBuilt( $id, $resolved );
	}

	private function fireBuilt( string $id, object $resolved ): void {
		$this->resolve( type: $resolved, params: null, cbs: $this->built->getItems() );

		$scopedCallbacks = $this->merge( $this->builtForEntry->getItems(), $id, $resolved );

		$this->resolve( type: $resolved, params: null, cbs: $scopedCallbacks );
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
	 * @param object|string            $type   The entry ID, or a callback. If built, an object instance.
	 * @param ArrayAccess|mixed[]|null $params The use provided params (only when building).
	 * @param array<?Closure>          $cbs    Registered callbacks to be fired.
	 */
	private function resolve( object|string $type, ArrayAccess|array|null $params, array $cbs ): void {
		$args = null === $params ? array( $type, $this->app ) : array( $type, $params, $this->app );

		array_walk( $cbs, static fn( ?Closure $cb ) => Unwrap::andInvoke( $cb, ...$args ) );
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
