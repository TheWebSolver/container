<?php
/**
 * Stack that stores data in a key/value pair.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Traits;

use Countable;

/** @template TValue */
trait KeyStack {
	/** @use Stack<string,TValue> */
	use Stack;

	private bool $asCollection = false;
	private string $useKey;

	public function asCollection(): void {
		$this->asCollection = true;
	}

	/** @phpstan-assert-if-true !null $this->get() */
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

	/** @return TValue `null` if cannot get data. */
	public function get( string $id ): mixed {
		[ $for, $key ] = $this->getKeys( $id );

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

		// @phpstan-ignore-next-line -- Possible when each stack item is used as collection or key deliberately creates collection.
		if ( ! isset( $this->stack[ $for ][ $key ] ) ) {
			return false;
		}

		unset( $this->stack[ $for ][ $key ] ); // @phpstan-ignore-line -- Ditto

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

		$data = $this->get( id: $this->useKey );

		// @phpstan-ignore-next-line -- Possible when each stack item is used as collection.
		return is_array( $data ) || $data instanceof Countable ? count( $data ) : 0;
	}

	public function reset( ?string $collectionId = null ): void {
		if ( ! $collectionId ) {
			$this->stack = array();

			return;
		}

		[ $for ] = $this->getKeys( from: $collectionId );

		if ( isset( $this->stack[ $for ] ) ) {
			unset( $this->stack[ $for ] );
		}
	}

	/** @return array{0:string,1:?string} */
	private function getKeys( string $from ): array {
		$keys = explode( separator: '||', string: $from, limit: 2 );

		return array( $keys[0], $keys[1] ?? null );
	}
}
