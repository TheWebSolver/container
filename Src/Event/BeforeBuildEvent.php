<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Event;

use ArrayAccess;
use Psr\EventDispatcher\StoppableEventInterface;
use TheWebSolver\Codegarage\Container\Traits\StopPropagation;
use TheWebSolver\Codegarage\Container\Interfaces\TaggableEvent;

class BeforeBuildEvent implements StoppableEventInterface, TaggableEvent {
	use StopPropagation;

	/**
	 * @param string                                               $entry
	 * @param ArrayAccess<object|string,mixed>|array<string,mixed> $params
	 */
	public function __construct( private readonly string $entry, private array|ArrayAccess $params = array() ) {}

	public function getEntry(): string {
		return $this->entry;
	}

	public function setParam( string|object $name, mixed $value ): void {
		$this->params[ $name ] = $value;
	}

	/** @return ArrayAccess<object|string,mixed>|array<string,mixed>|null */
	public function getParams(): array|ArrayAccess|null {
		return $this->params ?: null;
	}
}
