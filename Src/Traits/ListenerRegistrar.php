<?php
/**
 * Registers event listeners.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Traits;

use Closure;
use Generator;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\TaggableEvent;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\ListenerRegistry;

/** @template TEvent of object */
trait ListenerRegistrar {
	/** @use EventListeners<TEvent> */
	use EventListeners;

	protected bool $needsSorting = false;
	/** @var array{low:int,high:int} */
	protected array $priorities = array(
		'low'  => ListenerRegistry::DEFAULT_PRIORITY,
		'high' => ListenerRegistry::DEFAULT_PRIORITY,
	);

	/**
	 * Validates whether current event is valid for listeners to be registered.
	 *
	 * Usually, validation id done whether the provided event is actually an
	 * instanceof the desired event class for listeners to get registered.
	 *
	 * @param TEvent $event
	 * @phpstan-assert-if-true TEvent $event
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
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

	public function hasListeners( ?string $forEntry = null ): bool {
		return ! empty( $this->getListenersRegistered( $forEntry ) );
	}

	public function getListeners( ?string $forEntry = null ): array {
		return ! $forEntry ? $this->listeners : $this->listenersForEntry[ $forEntry ] ?? array();
	}

	public function getPriorities(): array {
		return $this->priorities;
	}

	public function reset( ?string $collectionId = null ): void {
		$this->resetListenersRegistered( $collectionId );
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
				yield $needsSorting ? $this->getSorted( $listeners ) : $listeners;
			}
		}
	}

	// phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch
	/**
	 * @param array<int,array<int,callable(TEvent $event): void>> $listeners
	 * @return array<int,array<int,callable(TEvent $event): void>>
	 */
	// phpcs:enable
	protected function getSorted( array $listeners ): array {
		ksort( $listeners, flags: SORT_NUMERIC );

		return $listeners;
	}

	protected function resetProperties( int $priority ): void {
		$this->needsSorting = true;

		if ( $this->currentPrioritySet( forType: 'low' ) > $priority ) {
			$this->priorities['low'] = $priority;
		}

		if ( $this->currentPrioritySet( forType: 'high' ) < $priority ) {
			$this->priorities['high'] = $priority;
		}
	}

	private function currentPrioritySet( string $forType ): int {
		return $this->priorities[ $forType ];
	}

	/** @return array<int|string,array<int,array<int,callable>|callable>> */
	private function getListenersRegistered( ?string $id ): array {
		return match ( $id ) {
			null    => $this->listeners,
			''      => $this->listenersForEntry,
			default => $this->listenersForEntry[ $id ] ?? array()
		};
	}

	private function resetListenersRegistered( ?string $id ): void {
		match ( $id ) {
			null    => ( $this->listeners = array() ),
			''      => ( $this->listenersForEntry = array() ),
			default => isset( $this->listenersForEntry[ $id ] ) && ( $this->listenersForEntry[ $id ] = array() )
		};
	}
}
