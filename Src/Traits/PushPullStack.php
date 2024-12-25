<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Traits;

/** @template TValue */
trait PushPullStack {
	/** @use Stack<int,TValue> */
	use Stack;

	/** @param TValue $value */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function push( mixed $value ): void {
		$this->stack[] = $value;
	}

	/** @return ?TValue */
	public function pull(): mixed {
		return array_pop( $this->stack );
	}

	/** @return ?TValue */
	public function latest(): mixed {
		return end( $this->stack ) ?: null;
	}
}
