<?php
/**
 * The event listener provider before app resolves the entry.
 *
 * @package TheWebSolver\Codegarage\Container
 *
 * @phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
 * @phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Event;

use Psr\EventDispatcher\ListenerProviderInterface;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\TaggableEvent;
use TheWebSolver\Codegarage\Lib\Container\Traits\ListenerRegistrar;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\ListenerRegistry;

class BeforeBuildProvider implements ListenerProviderInterface, ListenerRegistry {
	use ListenerRegistrar;

	protected function isEventValid( object $event ): bool {
		return $event instanceof BeforeBuildEvent;
	}

	protected function isEntryValid( TaggableEvent $event, string $currentEntry ): bool {
		$entry = $event->getEntry();

		return $entry === $currentEntry || is_subclass_of( $entry, $currentEntry, allow_string: true );
	}
}
