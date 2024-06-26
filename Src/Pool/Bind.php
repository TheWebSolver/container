<?php
/**
 * The binding pool.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Pool;

use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Traits\KeyStack;

class Bind {
	use KeyStack;

	public function set( string $key, Binding $value ): void {
		$this->stack[ $key ] = $value;
	}

	public function get( string $key ): Binding {
		return $this->stack[ $key ];
	}

	/** @return array<string,Binding> */
	public function getItems(): array {
		return $this->stack;
	}
}
