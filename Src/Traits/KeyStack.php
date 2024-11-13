<?php
/**
 * Stack of items indexed by string key.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Traits;

/** @template TValue */
trait KeyStack {
	/** @use Stack<string,TValue> */
	use Stack;

	/** @phpstan-assert-if-true !null $this->get() */
	public function has( string $key ): bool {
		return null !== $this->get( $key );
	}

	public function set( string $key, mixed $value ): void {
		$this->stack[ $key ] = $value;
	}

	/** @return ?TValue */
	public function get( string $id ): mixed {
		return $this->stack[ $id ] ?? null;
	}

	public function remove( string $key ): bool {
		if ( ! isset( $this->stack[ $key ] ) ) {
			return false;
		}

		unset( $this->stack[ $key ] );

		return true;
	}
}
