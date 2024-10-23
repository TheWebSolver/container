<?php
/**
 * Ensures event should propagate or not.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Traits;

trait StopPropagation {
	protected bool $shouldStopPropagation = false;

	/**
	 * Ensures current Event Listener halts the Event to be propagated to the next Event Listener in queue.
	 *
	 * @listener
	 */
	public function stopPropagation(): static {
		$this->shouldStopPropagation = true;

		return $this;
	}

	/**
	 * @dispatcher
	 */
	public function isPropagationStopped(): bool {
		return $this->shouldStopPropagation;
	}
}
