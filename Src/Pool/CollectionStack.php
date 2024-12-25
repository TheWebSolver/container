<?php
// phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint -- Generics & Closure type-hint OK.
// phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch  -- Generics & Closure type-hint OK.

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Pool;

use Countable;
use TheWebSolver\Codegarage\Container\Traits\Stack;
use TheWebSolver\Codegarage\Container\Traits\StackCompiler;
use TheWebSolver\Codegarage\Container\Interfaces\Compilable;
use TheWebSolver\Codegarage\Container\Interfaces\Resettable;

/**
 * @template TKey of array-key
 * @template TValue
 * @template-implements Compilable<string,array<TKey,TValue>>
 */
class CollectionStack implements Countable, Resettable, Compilable {
	/**
	 * @use Stack<string,array<TKey,TValue>>
	 * @use StackCompiler<string,array<TKey,TValue>>
	*/
	use Stack, StackCompiler;

	private int $currentIndex = 0;
	private ?string $countKey = null;

	/** @param ?TKey $index */
	public function set( string $key, mixed $value, string|int|null $index = null ): void {
		$this->stack[ $key ][ $index ?? $this->currentIndex++ ] = $value;
	}

	/** @return ($index is null ? ?array<TKey,TValue> : ?TValue) */
	public function get( string $key, string|int|null $index = null ): mixed {
		return null !== $index ? $this->stack[ $key ][ $index ] ?? null : $this->stack[ $key ] ?? null;
	}

	/** @phpstan-assert-if-true !null $this->get() */
	public function has( string $key, string|int|null $index = null ): bool {
		return null !== $this->get( $key, $index );
	}

	public function countFor( string $key ): Countable {
		$this->countKey = $key;

		return $this;
	}

	public function count(): int {
		$key            = $this->countKey;
		$this->countKey = null;

		return $key ? count( $this->stack[ $key ] ?? array() ) : count( $this->stack );
	}

	public function reset( ?string $collectionId = null ): void {
		if ( ! $collectionId ) {
			$this->stack = array();

			return;
		}

		if ( isset( $this->stack[ $collectionId ] ) ) {
			$this->stack[ $collectionId ] = array();
		}
	}

	/** @param ?TKey $index */
	public function remove( string $key, string|int|null $index = null ): bool {
		if ( ! isset( $this->stack[ $key ] ) ) {
			return false;
		}

		if ( null === $index ) {
			unset( $this->stack[ $key ] );

			return true;
		}

		if ( isset( $this->stack[ $key ][ $index ] ) ) {
			unset( $this->stack[ $key ][ $index ] );

			return true;
		}

		return false;
	}
}
