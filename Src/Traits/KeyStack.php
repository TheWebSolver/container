<?php
/**
 * The builder data.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Traits;

trait KeyStack {
	use Stack;

	public function has( string $key ): bool {
		return isset( $this->stack[ $key ] );
	}

	public function set( string $key, mixed $value ): void {
		$this->stack[ $key ] = $value;
	}

	public function get( string $item ): mixed {
		return $this->stack[ $item ];
	}

	public function remove( string $key ): bool {
		if ( ! isset( $this->stack[ $key ] ) ) {
			return false;
		}

		unset( $this->stack[ $key ] );

		return true;
	}
}
