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
		[ $for, $key ] = $this->getKeys( $key );

		return $key ? isset( $this->stack[ $for ][ $key ] ) : isset( $this->stack[ $for ] );
	}

	public function set( string $key, mixed $value ): void {
		[ $for, $key ] = $this->getKeys( $key );

		match ( true ) {
			null !== $key       => $this->stack[ $for ][ $key ] = $value,
			$this->asCollection => $this->stack[ $for ][]       = $value,
			default             => $this->stack[ $for ]         = $value,
		};
	}

	public function get( string $item ): mixed {
		[ $for, $key ] = $this->getKeys( $item );

		return $key ? $this->stack[ $for ][ $key ] ?? null : $this->stack[ $for ] ?? null;
	}

	public function remove( string $key ): bool {
		[ $for, $key ] = $this->getKeys( $key );

		if ( ! $key ) {
			if ( ! isset( $this->stack[ $for ] ) ) {
				return false;
			}

			unset( $this->stack[ $for ] );

			return true;
		}

		if ( ! isset( $this->stack[ $for ][ $key ] ) ) {
			return false;
		}

		unset( $this->stack[ $for ][ $key ] );

		return true;
	}

	/** @return array{0:string,1:?string} */
	private function getKeys( string $from ): array {
		$keys = explode( separator: ':', string: $from, limit: 2 );

		return array( $keys[0], $keys[1] ?? null );
	}
}
