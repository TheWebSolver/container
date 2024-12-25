<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Event\Manager;

use Psr\EventDispatcher\EventDispatcherInterface;
use TheWebSolver\Codegarage\Container\Event\EventType;
use TheWebSolver\Codegarage\Container\Traits\Resetter;
use TheWebSolver\Codegarage\Container\Interfaces\Resettable;
use TheWebSolver\Codegarage\Container\Interfaces\ListenerRegistry;

class EventManager implements Resettable {
	use Resetter;

	/** @var array<string,(EventDispatcherInterface &ListenerRegistry)|false|null> */
	private array $eventDispatchers;

	/** @var array<string,bool> */
	private array $assignedEventTypes;

	// phpcs:ignore Squiz.Commenting.FunctionComment.ParamNameNoMatch
	/** @param (EventDispatcherInterface &ListenerRegistry)|false $dispatcher `false` to prevent event dispatcher from being assigned on subsequent invocation of this method. */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function setDispatcher(
		(EventDispatcherInterface&ListenerRegistry)|false $dispatcher,
		EventType $eventType
	): bool {
		if ( $this->skipEventDispatcherFor( $eventType ) ) {
			return false;
		}

		$this->eventDispatchers[ $eventType->name ]   = $dispatcher;
		$this->assignedEventTypes[ $eventType->name ] = true;

		return true;
	}

	public function getDispatcher( EventType $eventType ): (EventDispatcherInterface&ListenerRegistry)|null {
		return ( $this->eventDispatchers[ $eventType->name ] ?? null ) ?: null;
	}

	/** @return array<string,(EventDispatcherInterface&ListenerRegistry)|false|null> */
	public function getDispatchers(): array {
		return $this->eventDispatchers;
	}

	public function isDispatcherDisabled( EventType $eventType ): bool {
		return false === ( $this->eventDispatchers[ $eventType->name ] ?? null );
	}

	public function isDispatcherAssigned( EventType $eventType ): bool {
		return true === ( $this->assignedEventTypes[ $eventType->name ] ?? null );
	}

	/** @return iterable<string,Resettable|false|null> */
	protected function getResettable(): iterable {
		return $this->eventDispatchers;
	}

	protected function resetWhenCollectionIdNotProvided( Resettable $resetter ): void {
		$resetter->reset( collectionId: null );
		$resetter->reset( collectionId: '' );
	}

	private function skipEventDispatcherFor( EventType $eventType ): bool {
		return $this->isDispatcherDisabled( $eventType ) || $this->isDispatcherAssigned( $eventType );
	}
}
