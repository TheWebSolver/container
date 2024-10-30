<?php
/**
 * The event dispatched to all event listeners after app has resolved the entry.
 *
 * @package TheWebSolver\Codegarage\Container
 *
 * @phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
 * @phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Event;

use Closure;
use Psr\EventDispatcher\StoppableEventInterface;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;
use TheWebSolver\Codegarage\Lib\Container\Traits\StopPropagation;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\TaggableEvent;

/** @template TEvent */
class AfterBuildEvent implements StoppableEventInterface, TaggableEvent {
	use StopPropagation;

	/**
	 * @param TEvent                          $resolved
	 * @param Stack<(class-string|Closure)[]> $decorators
	 * @param Stack<Closure[]>                $updaters
	 */
	public function __construct(
		private readonly mixed $resolved,
		private readonly string $entry,
		private readonly Stack $decorators = new Stack(),
		private readonly Stack $updaters = new Stack()
	) {
		$decorators->asCollection();
		$updaters->asCollection();
	}

	/** @return Stack<(class-string|Closure)[]> */
	public function getDecorators(): Stack {
		return $this->decorators;
	}

	/** @return Stack<Closure[]> */
	public function getUpdaters(): Stack {
		return $this->updaters;
	}

	/**
	 * Decorates resolved value with decorator currently being registered.
	 *
	 * @param class-string|Closure(TEvent $resolved, Container $app): TEvent $decorator The decorator can be:
	 * - a Closure that accepts the resolved value as first argument and container as second, or
	 * - a classname that accepts the resolved value as first argument.
	 */
	public function decorateWith( string|Closure $decorator ): self {
		$this->decorators->set( key: $this->entry, value: $decorator );

		return $this;
	}

	/**
	 * Updates the resolved value with the given callback currently being registered.
	 *
	 * @param Closure(TEvent $resolved, Container $app): void $with Recommended to type-hint `$resolved` value for the
	 *                                                         listener instead of nothing for IDE and better DX.
	 */
	public function update( Closure $with ): void {
		$this->updaters->set( key: $this->entry, value: $with );
	}

	public function getEntry(): string {
		return $this->entry;
	}

	/** @return TEvent */
	public function getResolved(): mixed {
		return $this->resolved;
	}
}
