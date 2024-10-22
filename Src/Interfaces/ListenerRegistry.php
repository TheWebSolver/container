<?php
/**
 * Interface for registering listeners.
 *
 * @package TheWebSolver\Codegarage\Container
 *
 * @phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
 * @phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Interfaces;

use Closure;

interface ListenerRegistry {
	/**
	 * Adds listener to the event provider.
	 *
	 * @param Closure(object $event): void $listener
	 */
	public function addListener( Closure $listener, ?string $forEntry ): void;
}
