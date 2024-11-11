<?php
/**
 * The event manager.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Event\Manager;

use WeakMap;
use Psr\EventDispatcher\EventDispatcherInterface;
use TheWebSolver\Codegarage\Lib\Container\Event\EventType;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\ListenerRegistry;

class EventManager {
	/** @var WeakMap<EventType,(EventDispatcherInterface &ListenerRegistry)|false|null> */
	private WeakMap $eventDispatchers;

	/** @var array<string,bool> */
	private array $assignedEventTypes;

	public function __construct() {
		$this->eventDispatchers = new WeakMap();
	}

	// phpcs:ignore Squiz.Commenting.FunctionComment.ParamNameNoMatch
	/** @param (EventDispatcherInterface &ListenerRegistry)|false $dispatcher False to suppress event dispatcher being assigned altogether when this method is invoked again. */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function setDispatcher(
		(EventDispatcherInterface&ListenerRegistry)|false $dispatcher,
		EventType $eventType
	): bool {
		if ( $this->isDispatcherDisabled( $eventType ) || $this->isDispatcherAssigned( $eventType ) ) {
			return false;
		}

		$this->eventDispatchers[ $eventType ]         = $dispatcher;
		$this->assignedEventTypes[ $eventType->name ] = true;

		return true;
	}

	public function getDispatcher( EventType $eventType ): (EventDispatcherInterface&ListenerRegistry)|null {
		return ( $this->eventDispatchers[ $eventType ] ?? null ) ?: null;
	}

	/** @return WeakMap<EventType,(EventDispatcherInterface&ListenerRegistry)|false|null> */
	public function getDispatchers(): WeakMap {
		return $this->eventDispatchers;
	}

	public function isDispatcherDisabled( EventType $eventType ): bool {
		return false === ( $this->eventDispatchers[ $eventType ] ?? null );
	}

	public function isDispatcherAssigned( EventType $eventType ): bool {
		return true === ( $this->assignedEventTypes[ $eventType->name ] ?? false );
	}
}
