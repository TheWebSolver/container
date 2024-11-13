<?php
/**
 * Initializes listener properties.
 *
 * @package TheWebSolver\Codegarage\Container
 *
 * @phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Traits;

/** @template TEvent of object */
trait EventListeners {
	/**
	 * @param array<string,array<int,array<int,callable(TEvent $event): void>>> $listenersForEntry
	 * @param array<int,array<int,callable(TEvent              $event): void>>  $listeners
	 */
	final public function __construct(
		protected array $listenersForEntry = array(),
		protected array $listeners = array()
	) {}
}
