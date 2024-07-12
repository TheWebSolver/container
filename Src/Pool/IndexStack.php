<?php
/**
 * Stack of non-keyed stored items.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Pool;

use TheWebSolver\Codegarage\Lib\Container\Helper\Unwrap;
use TheWebSolver\Codegarage\Lib\Container\Traits\Stack;

class IndexStack {
	use Stack;

	public function set( mixed $value ): void {
		$this->stack[] = $value;
	}

	public function restackWith( mixed $newValue, bool $mergeArray = true, bool $shift = false ): void {
		$previous = $this->getItems();

		$this->flush();

		$this->stack = match ( $mergeArray ) {
			false => $shift ? array( $newValue, ...$previous ) : array( ...$previous, $newValue ),
			true  => $shift
				? array( ...Unwrap::asArray( $newValue ), ...$previous )
				: array( ...$previous, ...Unwrap::asArray( $newValue ) )
		};
	}
}
