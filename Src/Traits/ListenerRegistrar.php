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

/** @template T of object */
trait ListenerRegistrar {
	/** @var array<string,array<Closure(T $event): void>> */
	protected array $listenersForEntry = array();
	/** @var array<Closure(T $event): void> */
	protected array $listeners = array();

	/**
	 * Validates whether current event is valid for listeners to be registered.
	 *
	 * Usually, validation id done whether the provided event is actually an
	 * instanceof the desired event class for listeners to get registered.
	 *
	 * @param T $object
	 * @phpstan-assert-if-true T $object
	 */
	abstract protected function isValid( object $event ): bool;

	/**
	 * Validates whether event listener should be invoked or not.
	 *
	 * Usually, validation is done by comparing if the event entry and current entry is same.
	 * Also, check can be performed whether event entry is a subclass of the current entry.
	 */
	abstract protected function shouldFire( TaggableEvent $event, string $currentEntry ): bool;

	public function addListener( Closure $listener, ?string $forEntry = null ): void {
		if ( $forEntry ) {
			$this->listenersForEntry[ $forEntry ][] = $listener;

			return;
		}

		$this->listeners[] = $listener;
	}

	public function getListeners( ?string $forEntry = null ): array {
		return ! $forEntry ? $this->getAllListeners() : $this->listenersForEntry[ $forEntry ] ?? array();
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

	/** @return ($event is T ? \Generator : array{}) */
	public function getListenersForEvent( object $event ): iterable {
		if ( ! $this->isValid( $event ) ) {
			return array();
		}

		yield from $this->getAllListeners();

		if ( $event instanceof TaggableEvent ) {
			yield from $this->getListenersFor( $event );
		}
	}

	/** @return array<Closure(T $event): void> */
	protected function getAllListeners(): array {
		return $this->listeners;
	}

	/** @return \Generator */
	protected function getListenersFor( TaggableEvent $event ): iterable {
		foreach ( $this->listenersForEntry as $entry => $listeners ) {
			if ( $this->shouldFire( $event, currentEntry: $entry ) ) {
				yield $listeners;
			}
		}
	}
}
