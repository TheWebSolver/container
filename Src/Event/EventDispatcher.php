<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Event;

use Closure;
use Psr\EventDispatcher\StoppableEventInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use TheWebSolver\Codegarage\Container\Helper\Unwrap;
use TheWebSolver\Codegarage\Container\Interfaces\ListenerRegistry;

/**
 * @template TEvent of object
 * @template-implements ListenerRegistry<TEvent>
 */
class EventDispatcher implements EventDispatcherInterface, ListenerRegistry {
	/** @param ListenerProviderInterface&ListenerRegistry<TEvent> $provider */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function __construct( private readonly ListenerProviderInterface&ListenerRegistry $provider ) {}

	public function addListener( Closure $listener, ?string $forEntry, int $priority = self::DEFAULT_PRIORITY ): void {
		$this->provider->addListener( $listener, $forEntry, $priority );
	}

	public function getListeners( ?string $forEntry = null ): array {
		return $this->provider->getListeners( $forEntry );
	}

	public function getPriorities( ?string $forEntry = null ): array {
		return $this->provider->getPriorities( $forEntry );
	}

	public function hasListeners( ?string $forEntry = null ): bool {
		return $this->provider->hasListeners( $forEntry );
	}

	public function reset( ?string $collectionId = null ): void {
		$this->provider->reset( $collectionId );
	}

	/** @param TEvent $event */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function dispatch( object $event ) {
		foreach ( $this->provider->getListenersForEvent( $event ) as $yielded => $iterables ) {
			foreach ( $iterables as $sorted => $listeners ) {
				foreach ( Unwrap::asArray( $listeners ) as $registered => $listener ) {
					if ( $event instanceof StoppableEventInterface && $event->isPropagationStopped() ) {
						break 3;
					}

					$listener( $event );
				}
			}
		}

		return $event;
	}
}
