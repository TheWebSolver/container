<?php
/**
 * Event Dispatcher Builder Pattern API.
 *
 * @package TheWebSolver\Codegarage\Container
 *
 * @phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint -- Closure type-hint OK.
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
	 * @param string $entry     Entry can be one of the following based on `EventType`:
	 * - `EventType::BeforeBuild`
	 * - `EventType::AfterBuild` -> The `$id` value of `Container::get()` method if no container binding.
	 *                              Any value of `Container::set()` method if has container binding.
	 * - `EventType::Building`   -> The parameter type-hint (string, EventBuilder::class, etc).
	 * @param string $paramName The dependency parameter name to provide the parameter
	 *                          value when app is resolving the given {@param $entry}.
	 * @throws LogicException When param name not passed for `EventType::Building`.
	 */
	public function for( string $entry, ?string $paramName = null ): self {
		$this->assertParamNameProvidedWhenResolving( $entry, $paramName );

		$entry = $this->app->getEntryFrom( alias: $entry );
		$entry = match ( $this->type ) {
			EventType::BeforeBuild => $entry,
			EventType::Building    => $this->assertParamNameProvidedWhenResolving( $entry, $paramName ),
			EventType::AfterBuild  => $this->app->getConcrete( $entry )
		};

		assert(
			assertion: ! $entry instanceof Closure,
			description: 'Concrete is always a string if we reach to After Build event.'
		);

		$this->key = $paramName ? Stack::keyFrom( id: $entry, name: $paramName ) : $entry;

		return $this;
	}

	/**
	 * @param Closure(object): void $with
	 * @throws LogicException When this method is invoked before `EventBuilder::needsListenerFor()` method.
	 */
	public function listen( Closure $with, int $priority = ListenerRegistry::DEFAULT_PRIORITY ): void {
		$this->registry->addListener(
			listener: $with,
			priority: $priority,
			forEntry: $this->key ?? throw new LogicException(
				sprintf( 'Entry not registered. Register using method: %s.', self::class . '::for()' )
			)
		);
	}

	private function assertParamNameProvidedWhenResolving( string $entry, ?string $paramName ): string {
		if ( EventType::Building !== $this->type || null !== $paramName ) {
			return $entry;
		}

		throw new LogicException(
			"Parameter name is required when adding event listener during build for entry {$entry}."
		);
	}
}
