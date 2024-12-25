<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Event\Provider;

use Psr\EventDispatcher\ListenerProviderInterface;
use TheWebSolver\Codegarage\Container\Event\AfterBuildEvent;
use TheWebSolver\Codegarage\Container\Interfaces\Compilable;
use TheWebSolver\Codegarage\Container\Traits\ListenerCompiler;
use TheWebSolver\Codegarage\Container\Traits\ListenerRegistrar;
use TheWebSolver\Codegarage\Container\Interfaces\ListenerRegistry;

/** @template-implements ListenerRegistry<AfterBuildEvent> */
class AfterBuildListenerProvider implements ListenerProviderInterface, ListenerRegistry, Compilable {
	/**
	 * @use ListenerRegistrar<AfterBuildEvent>
	 * @use ListenerCompiler<AfterBuildEvent>
	*/
	use ListenerRegistrar, ListenerCompiler;

	protected function isValid( object $event ): bool {
		return $event instanceof AfterBuildEvent;
	}
}
