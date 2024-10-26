<?php
/**
 * Stack to be used for pushing and pulling data.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Traits;

/** @template TValue */
trait PushPullStack {
	/**
	 * @use Stack<int,TValue>
	 * @use Puller<TValue>
	 */
	use Stack, Puller;

	/** @param TValue $value */
	public function push( mixed $value ): void { // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
		$this->stack[] = $value;
	}
}
