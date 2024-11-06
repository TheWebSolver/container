<?php
/**
 * The event listener provider for building event.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Event\Provider;

use Psr\EventDispatcher\ListenerProviderInterface;
use TheWebSolver\Codegarage\Lib\Container\Event\BuildingEvent;
use TheWebSolver\Codegarage\Lib\Container\Traits\ListenerRegistrar;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\ListenerRegistry;

/** @template-implements ListenerRegistry<BuildingEvent> */
class BuildingListenerProvider implements ListenerProviderInterface, ListenerRegistry {
	/** @use ListenerRegistrar<BuildingEvent> */
	use ListenerRegistrar;

	protected function isValid( object $event ): bool {
		return $event instanceof BuildingEvent;
	}
}
