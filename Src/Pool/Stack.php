<?php
/**
 * Stack of keyed stored items.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Pool;

use Countable;
use ArrayAccess;
use TheWebSolver\Codegarage\Lib\Container\Traits\KeyStack;

class Stack implements ArrayAccess {
	use KeyStack;

	public function offsetExists( $key ): bool {
		return $this->has( $key );
	}

	/** @param string $key The key. */
	#[\ReturnTypeWillChange]
	public function offsetGet( $key ): mixed {
		return $this->get( $key );
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 */
	public function offsetSet( $key, $value ): void {
		$this->set( $key, $value );
	}

	/** @param string $key */
	public function offsetUnset( $key ): void {
		$this->remove( $key );
	}

	public function count( ?string $id = null ): int {
		if ( ! $id ) {
			return count( $this->stack );
		}

		[ $for, $key ] = $this->getKeys( from: $id );

		return count(
			match ( true ) {
			null !== $key       => $this->getNestedData( $for, $key ),
			$this->asCollection => $this->stack[ $for ] ?? array(),
			default             => $this->stack,
			}
		);
	}

	/** @return mixed[] */
	private function getNestedData( string $for, string $key ): array {
		if ( ! isset( $this->stack[ $for ][ $key ] ) ) {
			return array();
		}

		$data = $this->stack[ $for ][ $key ];

		return is_array( $data ) || $data instanceof Countable ? $data : array();
	}

	public static function keyFrom( string $id, string $name ): string {
		return implode( separator: ':', array: func_get_args() );
	}
}
