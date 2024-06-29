<?php
/**
 * Stack of non-keyed stored items.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Pool;

use TheWebSolver\Codegarage\Lib\Container\Traits\Stack;

class IndexStack {
	use Stack;

	public function set( mixed $value ): void {
		$this->stack[] = $value;
	}
}
