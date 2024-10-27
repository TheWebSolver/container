<?php
/**
 * The event dispatcher.
 *
 * @package TheWebSolver\Codegarage\Container
 *
 * @phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
 * @Phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Event;

use Closure;
use Psr\EventDispatcher\StoppableEventInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\ListenerRegistry;

/** @template T of object */
class EventDispatcher implements EventDispatcherInterface, ListenerRegistry {
	// phpcs:ignore Squiz.Commenting.FunctionComment.SpacingAfterParamType
	/** @param ListenerProviderInterface&ListenerRegistry<T> $provider */
	public function __construct( private readonly ListenerProviderInterface&ListenerRegistry $provider ) {}

	/** @param Closure(T $event): void $listener */
	public function addListener( Closure $listener, ?string $forEntry ): void {
		$this->provider->addListener( $listener, $forEntry );
	}

	/** @return array<Closure(T $event): void> */
	public function getListeners( ?string $forEntry = null ): array {
		return $this->provider->getListeners( $forEntry );
	}

	public function reset( ?string $collectionId = null ): void {
		$this->provider->reset( $collectionId );
	}

	/** @param T $event */
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
