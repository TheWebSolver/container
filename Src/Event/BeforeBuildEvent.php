<?php
/**
 * The event dispatched to all event listeners before app resolves the given entry.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Event;

use ArrayAccess;
use Psr\EventDispatcher\StoppableEventInterface;
use TheWebSolver\Codegarage\Lib\Container\Traits\StopPropagation;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\TaggableEvent;

class BeforeBuildEvent implements StoppableEventInterface, TaggableEvent {
	use StopPropagation;

	/**
	 * @param string                                                    $entry
	 * @param ArrayAccess<object|string,mixed>|array<string,mixed>|null $params
	 */
	public function __construct( private readonly string $entry, private array|ArrayAccess|null $params = null ) {}

	public function getEntry(): string {
		return $this->entry;
	}

	public function setParam( string $name, mixed $value ): void {
		$this->params[ $name ] = $value;
	}

	/** @return ArrayAccess<object|string,mixed>|array<string,mixed>|null */
	public function getParams(): array|ArrayAccess|null {
		return $this->params;
	}
}
