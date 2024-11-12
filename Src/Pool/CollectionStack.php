<?php
/**
 * Stack of keyed stored items.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Pool;

use Countable;
use RuntimeException;
use TheWebSolver\Codegarage\Lib\Container\Traits\Stack;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\Compilable;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\Resettable;

/**
 * @template TValue
 * @template-implements Compilable<string,array<string|int,TValue>>
 */
class CollectionStack implements Countable, Resettable, Compilable {
	/** @use Stack<string,array<string|int,TValue>> */
	use Stack;

	private int $currentIndex = 0;
	private ?string $countKey = null;

	final public function __construct() {}

	/** @param array<string,array<string|int,TValue>> $data */
	public static function fromCompiledArray( array $data ): static {
		$self        = new static();
		$self->stack = $data;

		return $self;
	}

	public static function fromCompiledFile( string $path ): static {
		return ( $realpath = realpath( $path ) )
			? static::fromCompiledArray( require $realpath )
			: throw new RuntimeException( "Could not find compiled data from filepath {$path}." );
	}

	public function set( string $key, mixed $value, ?string $index = null ): void {
		$this->stack[ $key ][ $index ?? $this->currentIndex++ ] = $value;
	}

	/** @return ($index is null ? ?array<string|int,TValue> : ?TValue) */
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
