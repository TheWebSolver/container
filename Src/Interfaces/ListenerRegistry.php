<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Interfaces;

use Closure;

/** @template TEvent of object */
interface ListenerRegistry extends Resettable {
	public const DEFAULT_PRIORITY = 10;

	/**
	 * Adds event listener to the registry.
	 *
	 * @param Closure(TEvent): void $listener
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function addListener( Closure $listener, ?string $forEntry, int $priority ): void;

	/**
	 * Verifies if current registry has listeners attached.
	 *
	 * Listeners existence must be checked in all of these scenarios:
	 * - `null`             - Check for listeners registered without entries.
	 * - `empty-string`     - Check for listeners registered only with entries.
	 * - `non-empty-string` - Check for listeners registered only with the particular entry.
	 */
	public function hasListeners( ?string $forEntry = null ): bool;

	/**
	 * Gets all event listeners registered to the registry.
	 *
	 * This should not return event listeners sorted by their priority and
	 * should return event listeners in order they were registered.
	 *
	 * @return array<int,array<int,callable(TEvent): void>>
	 */
	public function getListeners( ?string $forEntry = null ): array;

	/**
	 * Gets the lowest and highest priorities set by registered Event Listeners.
	 *
	 * @return array{low:int,high:int}
	 */
	public function getPriorities(): array;
}
