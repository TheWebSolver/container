<?php
/**
 * Interface for registering listeners.
 *
 * @package TheWebSolver\Codegarage\Container
 *
 * @phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint -- Generics & Closure typ-hint OK.
 * @phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch -- Generics & Closure typ-hint OK.
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Interfaces;

use Closure;

/** @template TEvent of object */
interface ListenerRegistry extends Resettable {
	public const DEFAULT_PRIORITY = 10;

	/**
	 * Adds listener to the listener provider.
	 *
	 * @param Closure(TEvent $event): void $listener
	 */
	public function addListener( Closure $listener, ?string $forEntry, int $priority ): void;

	/**
	 * Gets all listeners registered to the listener provider.
	 *
	 * @return array<int,array<int,Closure(TEvent $event): void>>
	 */
	public function getListeners( ?string $forEntry = null ): array;

	/**
	 * Gets the lowest and highest priorities set by registered Event Listeners.
	 *
	 * @return array{0:int,1:int}
	 */
	public function getPriorities(): array;
}
