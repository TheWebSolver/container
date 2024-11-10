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
use Psr\EventDispatcher\EventDispatcherInterface;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Event\Manager\EventManager;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\ListenerRegistry;
use TheWebSolver\Codegarage\Lib\Container\Event\Provider\BuildingListenerProvider;
use TheWebSolver\Codegarage\Lib\Container\Event\Provider\AfterBuildListenerProvider;
use TheWebSolver\Codegarage\Lib\Container\Event\Provider\BeforeBuildListenerProvider;

enum EventType {
	case BeforeBuild;
	case Building;
	case AfterBuild;

	public function dispatcherId(): string {
		return $this->name . 'EventDispatcher';
	}

	public static function registerDispatchersTo( EventManager $manager ): EventManager {
		foreach ( self::cases() as $eventType ) {
			$manager->setDispatcher( $eventType->getDispatcher(), $eventType );
		}

		return $manager;
	}

	public function getDispatcher(): EventDispatcherInterface&ListenerRegistry {
		return match ( $this ) {
			self::BeforeBuild => new EventDispatcher( new BeforeBuildListenerProvider() ),
			self::Building    => new EventDispatcher( new BuildingListenerProvider() ),
			self::AfterBuild  => new EventDispatcher( new AfterBuildListenerProvider() )
		};
	}

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
		return $paramName ?: throw new LogicException(
			"Parameter name is required when adding event listener during build for entry {$entry}."
		);
	}

	private static function assertConcreteIsString( string|Closure $concrete, string $entry ): string {
		return ! $concrete instanceof Closure ? $concrete : throw new LogicException(
			"The concrete must be a string when adding event listener during build for entry {$entry}."
		);
	}
}
