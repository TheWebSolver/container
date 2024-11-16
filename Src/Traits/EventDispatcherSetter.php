<?php
/**
 * Sets Event Dispatcher.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Traits;

use Psr\EventDispatcher\EventDispatcherInterface;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\ListenerRegistry;

/** @template TEvent */
trait EventDispatcherSetter {
	/** @var (EventDispatcherInterface&ListenerRegistry<TEvent>)|null */
	private (EventDispatcherInterface&ListenerRegistry)|null $dispatcher = null;

	public function usingEventDispatcher( (EventDispatcherInterface&ListenerRegistry)|null $dispatcher ): static {
		$this->dispatcher = $dispatcher;

		return $this;
	}
}
