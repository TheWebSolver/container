<?php
/**
 * Parameters provided when resolving a container entry.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Pool;

use TheWebSolver\Codegarage\Lib\Container\Traits\PushPullStack;

class Param {
	use PushPullStack;

	/** @var array<mixed[]> */
	private array $stack = array();

	public function has( string $paramName ): bool {
		return isset( $this->latest()[ $paramName ] );
	}

	public function getFrom( string $paramName ): mixed {
		return $this->latest()[ $paramName ];
	}
}
