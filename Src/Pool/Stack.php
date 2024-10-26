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
use TheWebSolver\Codegarage\Lib\Container\Interfaces\Resettable;
/**
 * @template TValue
 * @template-implements ArrayAccess<string,TValue>
 */
class Stack implements ArrayAccess, Countable, Resettable {
	/** @use KeyStack<TValue> */
	use KeyStack;

	/** @param string $key */
	public function offsetExists( $key ): bool {
		return $this->has( $key );
	}

	/**
	 * @param string $key
	 * @return TValue
	 */
	public function offsetGet( $key ): mixed {
		return $this->get( $key );
	}

	/**
	 * @param string $key
	 * @param TValue $value
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
