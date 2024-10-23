<?php
/**
 * The event dispatched to all event listeners during app resolving the current parameter of the entry.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Event;

use Psr\EventDispatcher\StoppableEventInterface;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Traits\StopPropagation;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\TaggableEvent;

class BuildingEvent implements StoppableEventInterface, TaggableEvent {
	use StopPropagation;

	private ?Binding $binding = null;

	public function __construct( private readonly Container $app, private readonly string $paramTypeWithName ) {}

	public function app(): Container {
		return $this->app;
	}

	public function getEntry(): string {
		return $this->paramTypeWithName;
	}

	public function setBinding( Binding $binding ): static {
		$this->binding = $binding;

		return $this;
	}

	public function getBinding(): ?Binding {
		return $this->binding;
	}
}
