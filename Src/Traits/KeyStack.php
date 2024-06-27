<?php
/**
 * Stack that stores data in a key/value pair.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Traits;

trait KeyStack {
	use Stack;

	private bool $asCollection = false;

	public function asCollection(): void {
		$this->asCollection = true;
	}

	public function has( string $key ): bool {
		return isset( $this->stack[ $key ] );
	}

	public function set( string $key, mixed $value ): void {
		match ( true ) {
			$this->asCollection => $this->stack[ $key ][] = $value,
			default             => $this->stack[ $key ]   = $value,
		};
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
