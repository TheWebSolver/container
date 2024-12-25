<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Event\Provider;

use Psr\EventDispatcher\ListenerProviderInterface;
use TheWebSolver\Codegarage\Container\Interfaces\Compilable;
use TheWebSolver\Codegarage\Container\Event\BeforeBuildEvent;
use TheWebSolver\Codegarage\Container\Traits\ListenerCompiler;
use TheWebSolver\Codegarage\Container\Interfaces\TaggableEvent;
use TheWebSolver\Codegarage\Container\Traits\ListenerRegistrar;
use TheWebSolver\Codegarage\Container\Interfaces\ListenerRegistry;

/** @template-implements ListenerRegistry<BeforeBuildEvent> */
class BeforeBuildListenerProvider implements ListenerProviderInterface, ListenerRegistry, Compilable {
	/**
	 * @use ListenerRegistrar<BeforeBuildEvent>
	 * @use ListenerCompiler<BeforeBuildEvent>
	*/
	use ListenerRegistrar, ListenerCompiler;

	protected function isValid( object $event ): bool {
		return $event instanceof BeforeBuildEvent;
	}

	protected function shouldListenTo( TaggableEvent $event, string $currentEntry ): bool {
		$entry = $event->getEntry();

		return $entry === $currentEntry || is_subclass_of( $entry, $currentEntry, allow_string: true );
	}
}
