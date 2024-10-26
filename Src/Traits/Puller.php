<?php
/**
 * Data extractor from the end of a stack.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Traits;

/** @template TValue */
trait Puller {
	/** @var array<int,TValue> */
	private array $stack = array();

	/** @return TValue|array{} Empty array if no items or all items from stack has been pulled. */
	public function latest(): mixed {
		return count( $this->stack ) ? end( $this->stack ) : array();
	}

	public function pull(): mixed {
		return array_pop( $this->stack );
	}
}
