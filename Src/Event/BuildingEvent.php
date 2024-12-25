<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Event;

use Psr\EventDispatcher\StoppableEventInterface;
use TheWebSolver\Codegarage\Container\Container;
use TheWebSolver\Codegarage\Container\Data\Binding;
use TheWebSolver\Codegarage\Container\Data\SharedBinding;
use TheWebSolver\Codegarage\Container\Traits\StopPropagation;
use TheWebSolver\Codegarage\Container\Interfaces\TaggableEvent;

class BuildingEvent implements StoppableEventInterface, TaggableEvent {
	use StopPropagation;

	private Binding|SharedBinding|null $binding = null;

	public function __construct( private readonly Container $app, private readonly string $paramTypeWithName ) {}

	public function app(): Container {
		return $this->app;
	}

	public function getEntry(): string {
		return $this->paramTypeWithName;
	}

	public function setBinding( Binding|SharedBinding $binding ): static {
		$this->binding = $binding;

		return $this;
	}

	public function getBinding(): Binding|SharedBinding|null {
		return $this->binding;
	}
}
