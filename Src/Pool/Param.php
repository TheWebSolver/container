<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Pool;

use TheWebSolver\Codegarage\Container\Traits\PushPullStack;
use TheWebSolver\Codegarage\Container\Interfaces\Resettable;

class Param implements Resettable {
	/** @use PushPullStack<array<string,mixed>|\ArrayAccess<object|string,mixed>> */
	use PushPullStack;

	public function has( string $paramName ): bool {
		return isset( $this->latest()[ $paramName ] );
	}

	public function get( string $paramName ): mixed {
		return $this->latest()[ $paramName ] ?? null;
	}
}
