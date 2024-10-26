<?php
/**
 * Parameters provided when resolving a container entry.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Pool;

use TheWebSolver\Codegarage\Lib\Container\Traits\PushPullStack;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\Resettable;

class Param implements Resettable {
	/** @use PushPullStack<array<string,mixed>|\ArrayAccess<object|string,mixed>> */
	use PushPullStack;

	public function has( string $paramName ): bool {
		return isset( $this->latest()[ $paramName ] );
	}

	public function getFrom( string $paramName ): mixed {
		return $this->latest()[ $paramName ];
	}
}
