<?php
/**
 * The event listener provider for after build event.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Event\Provider;

use Psr\EventDispatcher\ListenerProviderInterface;
use TheWebSolver\Codegarage\Lib\Container\Event\AfterBuildEvent;
use TheWebSolver\Codegarage\Lib\Container\Traits\ListenerRegistrar;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\ListenerRegistry;

/** @template-implements ListenerRegistry<AfterBuildEvent> */
class AfterBuildListenerProvider implements ListenerProviderInterface, ListenerRegistry {
	/** @use ListenerRegistrar<AfterBuildEvent> */
	use ListenerRegistrar;

	protected function isValid( object $event ): bool {
		return $event instanceof AfterBuildEvent;
	}
}
