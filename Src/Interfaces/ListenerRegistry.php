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
	public function addListener( Closure $listener, ?string $forEntry, int $priority ): void;

	/**
	 * Verifies if current registry has event listeners attached.
	 *
	 * Event Listeners existence must be checked based on {@param $forEntry} value as:
	 * - `null`             - Check for event listeners registered without entries.
	 * - `empty-string`     - Check for event listeners registered with all entries.
	 * - `non-empty-string` - Check for event listeners registered only with the particular entry.
	 */
	public function hasListeners( ?string $forEntry = null ): bool;

	/**
	 * Gets all event listeners registered to the registry.
	 *
	 * This must not return event listeners sorted by their priority and
	 * must return event listeners in order they were registered.
	 *
	 * @return ($forEntry is null|non-empty-string ? array<int,array<int,callable(TEvent): void>> : array<string,array<int,array<int,callable(TEvent): void>>>)
	 */
	public function getListeners( ?string $forEntry = null ): array;

	/**
	 * Gets the lowest and highest priorities set by registered Event Listeners.
	 *
	 * This must return event listeners' priorities without entries if {@param $forEntry} not passed,
	 * or if {@param $forEntry} is passed, event listeners' priorities for that particular entry.
	 *
	 * @return array{low:int,high:int}
	 */
	public function getPriorities( ?string $forEntry = null ): array;
}
