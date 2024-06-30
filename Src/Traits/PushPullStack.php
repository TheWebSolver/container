<?php
/**
 * Stack to be used for pushing and pulling data.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Traits;

trait PushPullStack {
	use Stack, Puller;

	public function push( mixed $value ): void {
		$this->stack[] = $value;
	}
}
