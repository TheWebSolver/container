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
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Traits\StopPropagation;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\TaggableEvent;

class AfterBuildEvent implements StoppableEventInterface, TaggableEvent {
	use StopPropagation;

	private ?Binding $binding = null;

	public function __construct(
		private readonly object $resolved,
		private readonly Container $app,
		private readonly string $entry,
	) {}

	/**
	 * @param Closure(object $resolved, Container $app): void $callback Recommended to type-hint `$resolved` object.
	 */
	public function call( Closure $callback ): void {
		$callback( $this->resolved, $this->app );
	}

	public function getEntry(): string {
		return $this->entry;
	}

	public function getResolved(): object {
		return $this->resolved;
	}
}
