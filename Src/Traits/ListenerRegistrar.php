<?php
/**
 * Registers event listeners.
 *
 * @package TheWebSolver\Codegarage\Container
 *
 * @phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
 * @phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Traits;

use Closure;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\TaggableEvent;

trait ListenerRegistrar {
	protected array $listenersForEntry = array();
	protected array $listeners = array();

	/**
	 * Validates whether current event is valid for listeners to be registered.
	 *
	 * Usually, validation id done whether the provided event is actually an
	 * instanceof the desired event class for listeners to get registered.
	 */
	abstract protected function isEventValid( object $event ): bool;

	/**
	 * Validates whether event entry matches with the current entry in loop when event is dispatched.
	 *
	 * Usually, validation is done by comparing if the event entry and current entry is same.
	 * Also, check can be performed whether event entry is a subclass of the current entry.
	 */
	abstract protected function isEntryValid( TaggableEvent $event, string $currentEntry ): bool;

	/** @param Closure(object $event): void $listener */
	public function addListener( Closure $listener, ?string $forEntry = null ): void {
		if ( $forEntry ) {
			$this->listenersForEntry[ $forEntry ][] = $listener;

			return;
		}

		$this->listeners[] = $listener;
	}

	public function getListenersForEvent( object $event ): iterable {
		if ( ! $this->isEventValid( $event ) ) {
			return array();
		}

		yield from $this->getListeners();

		if ( $event instanceof TaggableEvent ) {
			yield from $this->getListenersFor( $event );
		}
	}

	/** @return array<Closure(BeforeBuildEvent $event): void> */
	protected function getListeners(): iterable {
		return $this->listeners;
	}

	protected function getListenersFor( TaggableEvent $event ): iterable {
		foreach ( $this->listenersForEntry as $entry => $listeners ) {
			if ( $this->isEntryValid( $event, currentEntry: $entry ) ) {
				yield $listeners;
			}
		}
	}
}
