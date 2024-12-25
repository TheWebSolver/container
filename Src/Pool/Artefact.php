<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Pool;

use Stringable;
use TheWebSolver\Codegarage\Container\Traits\PushPullStack;
use TheWebSolver\Codegarage\Container\Interfaces\Resettable;

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
