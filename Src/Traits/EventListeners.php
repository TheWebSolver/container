<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Traits;

/** @template TEvent of object */
trait EventListeners {
	/**
	 * @param array<string,array<int,array<int,callable(TEvent): void>>> $listenersForEntry
	 * @param array<int,array<int,callable(TEvent): void>>               $listeners
	 */
	final public function __construct(
		protected array $listenersForEntry = array(),
		protected array $listeners = array()
	) {}
}
