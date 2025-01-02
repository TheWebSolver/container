<?php
// phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint     -- Generics typ-hint OK.
// phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch      -- Generics typ-hint OK.
// phpcs:disable Squiz.Commenting.FunctionComment.SpacingAfterParamType -- Param name of Closure OK.

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Event;

use Closure;
use Psr\EventDispatcher\StoppableEventInterface;
use TheWebSolver\Codegarage\Container\Container;
use TheWebSolver\Codegarage\Container\Pool\CollectionStack;
use TheWebSolver\Codegarage\Container\Traits\StopPropagation;
use TheWebSolver\Codegarage\Container\Interfaces\TaggableEvent;

/** @template TResolved */
class AfterBuildEvent implements StoppableEventInterface, TaggableEvent {
	use StopPropagation;

	/**
	 * @param CollectionStack<string,class-string<TResolved>|Closure(TResolved $resolved, Container $app): TResolved> $decorators
	 * @param CollectionStack<string,Closure(TResolved $resolved, Container $app): void>                              $updaters
	 */
	public function __construct(
		private readonly Container $app,
		private readonly string $entry,
		private readonly CollectionStack $decorators = new CollectionStack(),
		private readonly CollectionStack $updaters = new CollectionStack()
	) {}

	public function app(): Container {
		return $this->app;
	}

	/** @return CollectionStack<string,class-string<TResolved>|(Closure(TResolved $resolved, Container $app): TResolved)> */
	public function getDecorators(): CollectionStack {
		return $this->decorators;
	}

	/** @return CollectionStack<string,Closure(TResolved $resolved, Container $app): void> */
	public function getUpdaters(): CollectionStack {
		return $this->updaters;
	}

	/**
	 * Decorates resolved value with decorator currently being registered.
	 *
	 * @param class-string<TResolved>|(Closure(TResolved $resolved, Container $app): TResolved) $decorator The decorator can be:
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
	 * @param Closure(TResolved, Container): void $with Recommended to type-hint first parameter's value to
	 *                                              resolved type instead of `mixed` for IDE support.
	 */
	public function update( Closure $with ): void {
		$this->updaters->set( key: $this->entry, value: $with );
	}

	public function getEntry(): string {
		return $this->entry;
	}
}
