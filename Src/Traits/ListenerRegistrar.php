<?php
/**
 * Registers event listeners.
 *
 * @package TheWebSolver\Codegarage\Container
 *
 * @phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint -- Generics type-hint OK.
 * @phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch -- Generics & Closure type-hint OK.
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Traits;

use Closure;
use Generator;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\TaggableEvent;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\ListenerRegistry;

/** @template TEvent of object */
trait ListenerRegistrar {
	/** @var array<string,array<int,array<int,Closure(TEvent $event): void>>> */
	protected array $listenersForEntry = array();
	/** @var array<int,array<int,Closure(TEvent $event): void>> */
	protected array $listeners = array();
	/** @var array{0:int,1:int} */
	protected array $priorities;
	protected bool $needsSorting = false;


	/**
	 * Validates whether current event is valid for listeners to be registered.
	 *
	 * Usually, validation id done whether the provided event is actually an
	 * instanceof the desired event class for listeners to get registered.
	 *
	 * @param TEvent $event
	 * @phpstan-assert-if-true TEvent $event
	 */
	abstract protected function isValid( object $event ): bool;

	/**
	 * Validates whether event listener should be invoked or not.
	 *
	 * Usually, validation is done by comparing if the event entry and current entry is same.
	 * Also, check can be performed whether event entry is a subclass of the current entry.
	 */
	protected function shouldListenTo( TaggableEvent $event, string $currentEntry ): bool {
		return $event->getEntry() === $currentEntry;
	}

	public function addListener(
		Closure $listener,
		?string $forEntry = null,
		int $priority = ListenerRegistry::DEFAULT_PRIORITY
	): void {
		$this->resetProperties( $priority );

		if ( $forEntry ) {
			$this->listenersForEntry[ $forEntry ][ $priority ][] = $listener;

			return;
		}

		$this->listeners[ $priority ][] = $listener;
	}

	public function getListeners( ?string $forEntry = null ): array {
		return ! $forEntry ? $this->listeners : $this->listenersForEntry[ $forEntry ] ?? array();
	}

	public function getPriorities(): array {
		return $this->priorities ?? array( ListenerRegistry::DEFAULT_PRIORITY, ListenerRegistry::DEFAULT_PRIORITY );
	}

	public function reset( ?string $collectionId = null ): void {
		if ( ! $collectionId ) {
			$this->listeners = array();

			return;
		}

		if ( isset( $this->listenersForEntry[ $collectionId ] ) ) {
			$this->listenersForEntry[ $collectionId ] = array();
		}
	}

	/** @return Generator */
	public function getListenersForEvent( object $event ): iterable {
		if ( ! $this->isValid( $event ) ) {
			return yield array();
		}

		$needsSorting       = $this->needsSorting;
		$this->needsSorting = false;

		yield from $this->getAllListeners( $needsSorting );

		if ( $event instanceof TaggableEvent ) {
			yield from $this->getListenersFor( $event, $needsSorting );
		}
	}

	protected function getAllListeners( bool $needsSorting ): Generator {
		yield $needsSorting ? $this->getSorted( $this->listeners ) : $this->listeners;
	}

	protected function getListenersFor( TaggableEvent $event, bool $needsSorting ): Generator {
		foreach ( $this->listenersForEntry as $currentEntry => $listeners ) {
			if ( $this->shouldListenTo( $event, $currentEntry ) ) {
				return yield $needsSorting ? $this->getSorted( $listeners ) : $listeners;
			}
		}
	}

	/**
	 * @param array<int,array<int,Closure(TEvent $event): void>> $listeners
	 * @return array<int,array<int,Closure(TEvent $event): void>>
	 */
	protected function getSorted( array $listeners ): array {
		ksort( $listeners, flags: SORT_NUMERIC );

		return $listeners;
	}

	protected function resetProperties( int $priority ): void {
		$this->needsSorting = true;

		if ( ! ( $low = ( $this->priorities[0] ?? null ) ) || $priority < $low ) {
			$this->priorities[0] = $priority;
		}

		if ( ! ( $high = ( $this->priorities[1] ?? null ) ) || $priority > $high ) {
			$this->priorities[1] = $priority;
		}
	}
}
