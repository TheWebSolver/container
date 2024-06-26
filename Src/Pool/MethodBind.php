<?php
/**
 * The binding pool.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Pool;

use TheWebSolver\Codegarage\Lib\Container\Traits\KeyStack;
use TheWebSolver\Codegarage\Lib\Container\Data\MethodBinding;

class MethodBind {
	use KeyStack;

	public function set( string $key, MethodBinding $value ): void {
		$this->stack[ $key ] = $value;
	}

	public function get( string $key ): MethodBinding {
		return $this->stack[ $key ];
	}

	/** @return array<string,MethodBinding> */
	public function getItems(): array {
		return $this->stack;
	}
}
