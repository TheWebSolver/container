<?php
/**
 * Stack that stores data in a key/value pair.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Traits;

use Countable;

trait KeyStack {
	use Stack;

	private bool $asCollection = false;
	private string $useKey;

	public function asCollection(): void {
		$this->asCollection = true;
	}

	public function has( string $key ): bool {
		return null !== $this->get( $key );
	}

	public function set( string $key, mixed $value ): void {
		[ $for, $key ] = $this->getKeys( $key );

		match ( true ) {
			null === $key && $this->asCollection => $this->stack[ $for ][]       = $value,
			null === $key                        => $this->stack[ $for ]         = $value,
			default                              => $this->stack[ $for ][ $key ] = $value,
		};
	}

	/** @return mixed `null` if cannot get data. */
	public function get( string $item ): mixed {
		[ $for, $key ] = $this->getKeys( $item );

		return null !== $key ? $this->stack[ $for ][ $key ] ?? null : $this->stack[ $for ] ?? null;
	}

	public function remove( string $key ): bool {
		[ $for, $key ] = $this->getKeys( $key );

		if ( null === $key ) {
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

	public function withKey( string $key ): static {
		$this->useKey = $key;

		return $this;
	}

	public function count(): int {
		if ( ! isset( $this->useKey ) ) {
			return count( $this->stack );
		}

		$data = $this->get( item: $this->useKey );

		return is_array( $data ) || $data instanceof Countable ? count( $data ) : 0;
	}

	/** @return array{0:string,1:?string} */
	private function getKeys( string $from ): array {
		$keys = explode( separator: '||', string: $from, limit: 2 );

		return array( $keys[0], $keys[1] ?? null );
	}

	public function reset( ?string $collectionId = null ): void {
		if ( ! $collectionId ) {
			$this->stack = array();

			return;
		}

		[ $for ] = $this->getKeys( from: $collectionId );

		if ( isset( $this->stack[ $for ] ) ) {
			$this->stack[ $for ] = array();
		}
	}
}
