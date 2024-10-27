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

/** @template T of object */
interface ListenerRegistry extends Resettable {
	/**
	 * Adds listener to the listener provider.
	 *
	 * @param Closure(T $event): void $listener
	 */
	public function addListener( Closure $listener, ?string $forEntry ): void;

	/**
	 * Gets all listeners registered to the listener provider.
	 *
	 * @return array<Closure(T $event): void>
	 */
	public function getListeners( ?string $forEntry = null ): array;
}
