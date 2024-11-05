<?php
/**
 * The event type.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Event;

use Closure;
use LogicException;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;

enum EventType {
	case BeforeBuild;
	case Building;
	case AfterBuild;

	/**
	 * @param Container $app
	 * @param string    $entry      Entry can be one of the following based on `EventType`:
	 * - `EventType::BeforeBuild`
	 * - `EventType::AfterBuild` -> The `$id` value of `Container::get()` method if no container binding.
	 *                              Entry/alias of `Container::set()` method if has container binding.
	 * - `EventType::Building`   -> The parameter type-hint (string, EventBuilder::class, etc).
	 * @param string    $paramName  The type-hinted parameter name to auto-wire the param value
	 *                              when `EventType::Building` the given {@param $entry} type.
	 * @throws LogicException       When param name not passed for `EventType::Building`, or
	 *                              when concrete is a Closure for `EventType::AfterBuild`.
	 */
	public function getKeyFrom( Container $app, string $entry, ?string $paramName ): string {
		$entry = $app->getEntryFrom( $entry );

		return match ( $this ) {
			EventType::BeforeBuild => $entry,
			EventType::Building    => self::assertParamNameProvided( $entry, $paramName ),
			EventType::AfterBuild  => self::assertConcreteIsString( $app->getConcrete( $entry ), $entry )
		};
	}

	private static function assertParamNameProvided( string $entry, ?string $paramName ): string {
		return ! ! $paramName ? Stack::keyFrom( id: $entry, name: $paramName ) : throw new LogicException(
			"Parameter name is required when adding event listener during build for entry {$entry}."
		);
	}

	private static function assertConcreteIsString( string|Closure $concrete, string $entry ): string {
		return ! $concrete instanceof Closure ? $concrete : throw new LogicException(
			"The concrete must be a string when adding event listener during build for entry {$entry}."
		);
	}
}
