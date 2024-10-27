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

final class EventBuilder {
	private string $key;

	public function __construct(
		private Container $app,
		private EventType $type,
		private ListenerRegistry $registry
	) {}

	/**
	 * @param string $entry     Either the Parameter Type name or its aliased string value.
	 * @param string $paramName The dependency parameter name to provide the parameter
	 *                          value when app is resolving the given {@param $entry}.
	 * @throws LogicException When param name not passed for `EventType::Building`.
	 */
	public function for( string $entry, ?string $paramName = null ): self {
		$this->assertParamNameProvidedWhenResolving( $entry, $paramName );

		$entry     = $this->app->getEntryFrom( alias: $entry );
		$this->key = $paramName ? Stack::keyFrom( id: $entry, name: $paramName ) : $entry;

		return $this;
	}

	/**
	 * @param Closure(object $event): void $with
	 * @throws LogicException When this method is invoked before `EventBuilder::needsListenerFor()` method.
	 */
	public function listen( Closure $with ): void { // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
		$this->registry->addListener(
			listener: $with,
			forEntry: $this->key ?? throw new LogicException(
				sprintf( 'Entry not registered. Register using method: %s.', self::class . '::for()' )
			)
		);
	}

	private function assertParamNameProvidedWhenResolving( string $entry, ?string $paramName ): void {
		if ( EventType::Building !== $this->type || null !== $paramName ) {
			return;
		}

		throw new LogicException(
			"Parameter name is required when adding event listener during build for entry {$entry}."
		);
	}
}
