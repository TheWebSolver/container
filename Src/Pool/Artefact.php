<?php
/**
 * The resolving stack data.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Pool;

use Stringable;
use TheWebSolver\Codegarage\Lib\Container\Traits\PushPullStack;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\Resettable;

class Artefact implements Stringable, Resettable {
	/** @use PushPullStack<string> */
	use PushPullStack;

	public function __toString(): string {
		return implode( ', ', array_unique( $this->stack ) );
	}

	public function has( string $value ): bool {
		return in_array( needle: $value, haystack: $this->stack, strict: true );
	}
}
