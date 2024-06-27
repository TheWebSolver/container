<?php
/**
 * Parameters provided when resolving a container entry.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Pool;

use ReflectionParameter;
use TheWebSolver\Codegarage\Lib\Container\Traits\PushPullStack;

class Param {
	use PushPullStack;

	/** @var array<mixed[]> */
	private array $stack = array();

	public function hasLatest( ReflectionParameter $dependency ): bool {
		return isset( $this->latest()[ $dependency->name ] );
	}

	/** @return mixed `null` if no items or all items from stack has been pulled. */
	public function getLatest( ReflectionParameter $dependency ): mixed {
		return $this->latest()[ $dependency->name ] ?? null;
	}
}
