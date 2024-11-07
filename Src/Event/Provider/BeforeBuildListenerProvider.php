<?php
/**
 * The event listener provider for before build event.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Event\Provider;

use Psr\EventDispatcher\ListenerProviderInterface;
use TheWebSolver\Codegarage\Lib\Container\Event\BeforeBuildEvent;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\TaggableEvent;
use TheWebSolver\Codegarage\Lib\Container\Traits\ListenerRegistrar;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\ListenerRegistry;

/** @template-implements ListenerRegistry<BeforeBuildEvent> */
class BeforeBuildListenerProvider implements ListenerProviderInterface, ListenerRegistry {
	/** @use ListenerRegistrar<BeforeBuildEvent> */
	use ListenerRegistrar;

	public function isValid( object $event ): bool {
		return $event instanceof BeforeBuildEvent;
	}

	protected function shouldListenTo( TaggableEvent $event, string $currentEntry ): bool {
		$entry = $event->getEntry();

		return $entry === $currentEntry || is_subclass_of( $entry, $currentEntry, allow_string: true );
	}
}