<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Traits;

use Closure;
use Generator;
use TheWebSolver\Codegarage\Container\Interfaces\TaggableEvent;
use TheWebSolver\Codegarage\Container\Interfaces\ListenerRegistry;

/** @template TEvent of object */
trait ListenerRegistrar {
	/** @use EventListeners<TEvent> */
	use EventListeners;

	private const DEFAULT_PRIORITIES = array(
		'low'  => ListenerRegistry::DEFAULT_PRIORITY,
		'high' => ListenerRegistry::DEFAULT_PRIORITY,
	);

	protected bool $needsSorting = false;

	/**
	 * Validates registered event listeners are for the given event.
	 *
	 * @param TEvent $event
	 * @phpstan-assert-if-true =TEvent $event
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	abstract protected function isValid( object $event ): bool;

	/** Ensures whether event listener should be listened. */
	protected function shouldListenTo( TaggableEvent $event, string $currentEntry ): bool {
		return $event->getEntry() === $currentEntry;
	}

	public function addListener(
		Closure $listener,
		?string $forEntry = null,
		int $priority = ListenerRegistry::DEFAULT_PRIORITY
	): void {
		$this->needsSorting = true;

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
		return $this->getListenersRegistered( $forEntry );
	}

	public function getPriorities( ?string $forEntry = null ): array {
		$priorities = array_keys( $forEntry ? $this->listenersForEntry[ $forEntry ] ?? array() : $this->listeners );

		return empty( $priorities ) ? self::DEFAULT_PRIORITIES : array(
			'low'  => min( $priorities ),
			'high' => max( $priorities ),
		);
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
				yield $currentEntry => $needsSorting ? $this->getSorted( $listeners ) : $listeners;
			}
		}
	}

	/**
	 * @param array<int,array<int,callable(TEvent): void>> $listeners
	 * @return array<int,array<int,callable(TEvent): void>>
	 */
	protected function getSorted( array $listeners ): array {
		ksort( $listeners, flags: SORT_NUMERIC );

		return $listeners;
	}

	/** @return ($forEntry is null|non-empty-string ? array<int,array<int,callable(TEvent): void>> : array<string,array<int,array<int,callable(TEvent): void>>>) */
	private function getListenersRegistered( ?string $forEntry ): array {
		return match ( $forEntry ) {
			null    => $this->listeners,
			''      => $this->listenersForEntry,
			default => $this->listenersForEntry[ $forEntry ] ?? array()
		};
	}

	private function resetListenersRegistered( ?string $forEntry ): void {
		match ( $forEntry ) {
			null    => ( $this->listeners = array() ),
			''      => ( $this->listenersForEntry = array() ),
			default => isset( $this->listenersForEntry[ $forEntry ] ) && ( $this->listenersForEntry[ $forEntry ] = array() )
		};
	}
}
