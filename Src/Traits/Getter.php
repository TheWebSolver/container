<?php
/**
 * Stacked data getter using key.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Traits;

trait Getter {
	/** @var mixed[] */
	private array $stack = array();

	public function get( string $item ): mixed {
		return $this->stack[ $item ];
	}
}
