<?php
/**
 * Stack of items.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Pool;

use Countable;
use ArrayAccess;
use TheWebSolver\Codegarage\Lib\Container\Traits\KeyStack;
use TheWebSolver\Codegarage\Lib\Container\Traits\StackCompiler;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\Compilable;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\Resettable;

/**
 * @template TValue
 * @template-implements ArrayAccess<string,TValue>
 * @template-implements Compilable<string,TValue>
 */
class Stack implements ArrayAccess, Countable, Resettable, Compilable {
	/**
	 * @use KeyStack<TValue>
	 * @use StackCompiler<string,TValue>
	*/
	use KeyStack, StackCompiler;

	/** @param string $key */
	public function offsetExists( $key ): bool {
		return $this->has( $key );
	}

	/**
	 * @param string $key
	 * @return ?TValue
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

	public function count(): int {
		return count( $this->stack );
	}
}
