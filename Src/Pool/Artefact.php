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

class Artefact implements Stringable {
	use PushPullStack;

	public function __toString(): string {
		return implode( ', ', $this->stack );
	}

	public function has( string $value ): bool {
		return in_array( needle: $value, haystack: $this->stack, strict: true );
	}
}
