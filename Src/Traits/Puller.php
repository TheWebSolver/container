<?php
/**
 * Pull item from the stack.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Traits;

trait Puller {
	/** @var mixed[] */
	private array $stack = array();

	/** @return mixed Empty array if no items or all items from stack has been pulled. */
	public function latest(): mixed {
		return count( $this->stack ) ? end( $this->stack ) : array();
	}

	public function pull(): mixed {
		return array_pop( $this->stack );
	}
}
