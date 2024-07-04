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

	public function restackWith( mixed $newValue, bool $mergeArray = true ): void {
		$previous = $this->getItems();

		$this->flush();

		if ( $mergeArray ) {
			$this->stack = array( ...$previous, ...Unwrap::asArray( $newValue ) );
		} else {
			$this->stack = array( ...$previous, $newValue );
		}
	}
}
