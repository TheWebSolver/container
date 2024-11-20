<?php
/**
 * The event type.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Event;

use Closure;
use Psr\EventDispatcher\EventDispatcherInterface;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Data\SharedBinding;
use TheWebSolver\Codegarage\Lib\Container\Error\LogicalError;
use TheWebSolver\Codegarage\Lib\Container\Event\Manager\EventManager;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\ListenerRegistry;
use TheWebSolver\Codegarage\Lib\Container\Event\Provider\BuildingListenerProvider;
use TheWebSolver\Codegarage\Lib\Container\Event\Provider\AfterBuildListenerProvider;
use TheWebSolver\Codegarage\Lib\Container\Event\Provider\BeforeBuildListenerProvider;

enum EventType {
	case BeforeBuild;
	case Building;
	case AfterBuild;

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
	 * @param string    $id         The id can be one of the following based on `EventType`:
	 * - `EventType::BeforeBuild`
	 * - `EventType::AfterBuild` -> The `$id` value of `Container::get()` method if no container binding.
	 *                              Entry/alias of `Container::set()` method if has container binding.
	 * - `EventType::Building`   -> The parameter type-hint (string, EventBuilder, etc).
	 * @param string    $paramName  The type-hinted parameter name to auto-wire the param value
	 *                              when `EventType::Building` the given {@param $id} type.
	 * @throws LogicalError         When param name not passed for `EventType::Building`.
	 *                              When concrete is a Closure for `EventType::AfterBuild`.
	 */
	public function getKeyFrom( Container $app, string $id, ?string $paramName ): string {
		$entry = $app->getEntryFrom( $id );

		return match ( $this ) {
			EventType::BeforeBuild => $entry,
			EventType::Building    => self::assertParamNameProvided( $entry, $paramName ),
			EventType::AfterBuild  => self::assertConcreteIsString( $app, $entry )
		};
	}

	private static function assertParamNameProvided( string $entry, ?string $paramName ): string {
		return $paramName ? "$entry:$paramName" : throw LogicalError::duringBuildEventNeedsParamName( $entry );
	}

	private static function assertConcreteIsString( Container $app, string $entry ): string {
		if ( ! ( $bound = $app->getBinding( $entry ) ) || $bound instanceof SharedBinding ) {
			return $entry;
		}

		return ! ( $classname = $bound->material ) instanceof Closure
			? $classname
			: throw LogicalError::afterBuildEventEntryMustBeClassname( $entry );
	}
}
