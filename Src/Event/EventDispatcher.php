<?php
/**
 * The event dispatcher.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Event;

use Closure;
use Psr\EventDispatcher\StoppableEventInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\ListenerRegistry;

class EventDispatcher implements EventDispatcherInterface, ListenerRegistry {
	public function __construct( private readonly ListenerProviderInterface&ListenerRegistry $provider ) {}

	public function addListener( Closure $listener, ?string $forEntry ): void {
		$this->provider->addListener( $listener, $forEntry );
	}

	public function getListeners( ?string $forEntry = null ): array {
		return $this->provider->getListeners( $forEntry );
	}

	public function reset( ?string $collectionId = null ): void {
		$this->provider->reset( $collectionId );
	}

	public function dispatch( object $event ) {
		foreach ( $this->provider->getListenersForEvent( $event ) as $listener ) {
			$callbacks = $listener instanceof Closure ? array( $listener ) : $listener;

			foreach ( $callbacks as $callback ) {
				if ( $event instanceof StoppableEventInterface && $event->isPropagationStopped() ) {
					break 2;
				}

				$callback( $event );
			}
		}

		return $event;
	}
}
