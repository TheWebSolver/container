<?php
/**
 * Listener providers test.
 *
 * @package TheWebSolver\Codegarage\Test
 *
 * @phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
 * @phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Tests\Event;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\ListenerProviderInterface;
use TheWebSolver\Codegarage\Lib\Container\Event\BuildingEvent;
use TheWebSolver\Codegarage\Lib\Container\Event\AfterBuildEvent;
use TheWebSolver\Codegarage\Lib\Container\Event\BeforeBuildEvent;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\TaggableEvent;
use TheWebSolver\Codegarage\Lib\Container\Traits\ListenerRegistrar;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\ListenerRegistry;
use TheWebSolver\Codegarage\Lib\Container\Event\Provider\BuildingListenerProvider;
use TheWebSolver\Codegarage\Lib\Container\Event\Provider\AfterBuildListenerProvider;
use TheWebSolver\Codegarage\Lib\Container\Event\Provider\BeforeBuildListenerProvider;

class ProviderTest extends TestCase {
	/**
	 * @param class-string<ListenerProviderInterface &ListenerRegistry> $className
	 * @dataProvider provideListenerProvidersAndValidEvent
	 */
	public function testListenerProviderProvidesListenersOnlyIfGivenObjectIsValidEvent( string $className, object $event ): void {
		$provider = new $className();

		$provider->addListener( function ( $e ) {}, null, 2 );
		$provider->addListener( function ( $e ) {}, self::class, 3 );
		$provider->addListener( function ( $e ) {}, self::class, 3 );
		$provider->addListener( function ( $e ) {}, self::class, 7 );

		$totalYielded = 0;

		foreach ( $provider->getListenersForEvent( $this ) as $yielded ) {
			$this->assertEmpty( $yielded );
			++$totalYielded;
		}

		$this->assertSame( 1, $totalYielded, 'Only one empty array is yielded as for invalid event object.' );

		$arrays = array();

		// Flatten all yielded values from generators to an actual array for testing.
		foreach ( $provider->getListenersForEvent( $event ) as $yielded ) {
			foreach ( $yielded as $priority => $listeners ) {
				$arrays[ $priority ] = $listeners;
			}
		}

		$this->assertSame(
			expected: array( 2, 3, 7 ),
			actual: array_keys( $arrays ),
			message: 'Both global and entry scoped listeners should be listened indexed by respective priority.'
		);

		$this->assertCount( 1, $arrays[2], 'Global scoped with priority value of 2.' );
		$this->assertCount( 2, $arrays[3], 'Entry scoped with priority value of 3.' );
		$this->assertCount( 1, $arrays[7], 'Entry scoped with priority value of 7.' );
	}

	public function provideListenerProvidersAndValidEvent(): array {
		$beforeBuild = $this->createMock( BeforeBuildEvent::class );
		$beforeBuild->method( 'getEntry' )->willReturn( self::class );
		$building = $this->createMock( BuildingEvent::class );
		$building->method( 'getEntry' )->willReturn( self::class );
		$afterBuild = $this->createMock( AfterBuildEvent::class );
		$afterBuild->method( 'getEntry' )->willReturn( self::class );

		return array(
			array( BeforeBuildListenerProvider::class, $beforeBuild ),
			array( BuildingListenerProvider::class, $building ),
			array( AfterBuildListenerProvider::class, $afterBuild ),
		);
	}

	public function testListenerRegistrySetterAndGetter(): void {
		$event = new class() implements TaggableEvent {
			public function __construct( private string $name = '' ) {}

			public function setName( string $name ) {
				$this->name = $this->name . '::' . $name;
			}

			public function getName(): string {
				return $this->name;
			}

			public function getEntry(): string {
				return ProviderTest::class;
			}
		};

		$eventType = $event::class;

		$registrar = new class( $eventType ) implements ListenerRegistry {
			use ListenerRegistrar;

			public function __construct( private string $eventType ) {}

			protected function isValid( object $event ): bool {
				$type = $this->eventType;

				return $event instanceof $type;
			}
		};

		$global = fn ( $e ) => $e->setName( 'global' );
		$first  = fn ( $e ) => $e->setName( 'first' );
		$second = fn ( $e ) => $e->setName( 'second' );
		$third  = fn ( $e ) => $e->setName( 'third' );
		$parent = fn ( $e ) => $e->setName( 'parent' );

		$registrar->addListener( $global );
		$registrar->addListener( $first, self::class, 1 );
		$registrar->addListener( $second, self::class, 1 );
		$registrar->addListener( $third, self::class, -1 );
		$registrar->addListener( $parent, parent::class, 5 );

		$this->assertSame( array( 10 => array( $global ) ), $registrar->getListeners() );
		$this->assertSame( array( 5 => array( $parent ) ), $registrar->getListeners( forEntry: parent::class ) );
		$this->assertSame(
			message: 'Events should not be sorted when listeners are directly requested.',
			actual: $registrar->getListeners( forEntry: self::class ),
			expected: array(
				1  => array( $first, $second ),
				-1 => array( $third ),
			)
		);

		$listeners = $registrar->getListenersForEvent( $event );

		foreach ( $listeners as $sortedListeners ) {  // Both generators: global and/or scoped.
			foreach ( $sortedListeners as $listener ) { // Collection by sorting number.
				foreach ( $listener as $invoke ) {        // collection inside each sortable collection.
					$invoke( $event );
				}
			}
		}

		$this->assertSame(
			message: 'Listeners must be sorted by priority.',
			expected: '::global::third::first::second',
			actual: $event->getName()
		);
	}
}
