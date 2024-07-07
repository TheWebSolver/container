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

class Stack implements ArrayAccess, Countable {
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

	public static function keyFrom( string $id, string $name ): string {
		return implode( separator: '||', array: func_get_args() );
	}
}
