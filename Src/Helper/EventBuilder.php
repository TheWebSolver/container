<?php
/**
 * Event Dispatcher Builder Pattern API.
 *
 * @package TheWebSolver\Codegarage\Container
 *
 * @phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch -- Closure type-hint OK.
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Helper;

use Closure;
use LogicException;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;
use TheWebSolver\Codegarage\Lib\Container\Event\EventType;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\ListenerRegistry;

final readonly class EventBuilder {
	private string $key;

	public function __construct(
		private Container $app,
		private EventType $type,
		private ListenerRegistry $registry
	) {}

	/**
	 * @param string $paramName Parameter name when event is being registered to provide the parameter
	 *                          value when app is resolving the given {@param $entry}.
	 * @throws LogicException When param name not passed for `EventType::Building`.
	 */
	public function needsListenerFor( string $entry, ?string $paramName = null ): self {
		$this->assertParamNameProvidedForBuildingEventType( $entry, $paramName );

		$entry     = $this->app->getEntryFrom( alias: $entry );
		$this->key = $paramName ? Stack::keyFrom( id: $entry, name: $paramName ) : $entry;

		return $this;
	}

	/**
	 * @param Closure(object $event): void $listener
	 * @throws LogicException When this method is invoked before `EventBuilder::needsListenerFor()` method.
	 */
	public function give( Closure $listener ): void { // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
		$this->registry->addListener(
			listener: $listener,
			forEntry: $this->key ?? throw new LogicException(
				sprintf( 'Entry not registered. Register using method: %s.', self::class . '::needsListenerFor()' )
			)
		);
	}

	private function assertParamNameProvidedForBuildingEventType( string $entry, ?string $paramName ): void {
		if ( EventType::Building !== $this->type || null !== $paramName ) {
			return;
		}

		throw new LogicException(
			"Parameter name is required when adding event listener during build for entry {$entry}."
		);
	}
}
