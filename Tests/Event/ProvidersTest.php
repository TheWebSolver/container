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

use Generator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
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

class ProvidersTest extends TestCase {
	/**
	 * @param class-string<ListenerProviderInterface &ListenerRegistry> $className
	 * @dataProvider provideListenerProvidersAndValidEvent
	 */
	public function testListenerProviderProvidesListenersOnlyIfGivenObjectIsValidEvent(
		string $className,
		MockObject $event
	): void {
		$listenerProvider = new $className();

		$listenerProvider->addListener( function ( $e ) {}, null, 2 );
		$listenerProvider->addListener( function ( $e ) {}, self::class, 7 );
		$listenerProvider->addListener( function ( $e ) {}, self::class, 3 );
		$listenerProvider->addListener( function ( $e ) {}, self::class, 3 );
		$listenerProvider->addListener( function ( $e ) {}, parent::class, 7 );

		// BeforeBuildListenerProvider can listen for event entry that is subclass of another entry.
		$event->expects( $this->exactly( BeforeBuildListenerProvider::class === $className ? 3 : 2 ) )
			->method( 'getEntry' )
			->willReturn( self::class );

		/** @var Generator */
		$invalidEventGenerator = $listenerProvider->getListenersForEvent( $this );

		$this->assertEmpty(
			actual: $invalidEventGenerator->current(),
			message: 'Only one empty array is yielded as for invalid event object.'
		);

		$invalidEventGenerator->next();

		$this->assertFalse( $invalidEventGenerator->valid() );

		/** @var Generator */
		$validEventGenerator   = $listenerProvider->getListenersForEvent( $event );
		$listenersWithoutEntry = $validEventGenerator->current();

		$this->assertSame( array( 2 ), array_keys( $listenersWithoutEntry ) );
		$this->assertCount( 1, $listenersWithoutEntry[2] );

		$validEventGenerator->next();

		$listenersWithEntry = $validEventGenerator->current();

		$this->assertSame( array( 3, 7 ), array_keys( $listenersWithEntry ) );
		$this->assertCount( 2, $listenersWithEntry[3] );
		$this->assertCount( 1, $listenersWithEntry[7] );

		$validEventGenerator->next();

		// BeforeBuildListenerProvider retrieves event listeners for entry with "parent::class" also.
		if ( BeforeBuildListenerProvider::class === $className ) {
			$parentEventGenerator = $validEventGenerator->current();

			$this->assertSame( array( 7 ), array_keys( $parentEventGenerator ) );
			$this->assertCount( 1, $parentEventGenerator[7] );

			$validEventGenerator->next();
		}

		$this->assertFalse(
			condition: $validEventGenerator->valid(),
			message: 'Must not be valid after global scoped and event entry scoped listeners are retrieved.'
		);
	}

	public function provideListenerProvidersAndValidEvent(): array {
		return array(
			array( BeforeBuildListenerProvider::class, $this->createMock( BeforeBuildEvent::class ) ),
			array( BuildingListenerProvider::class, $this->createMock( BuildingEvent::class ) ),
			array( AfterBuildListenerProvider::class, $this->createMock( AfterBuildEvent::class ) ),
		);
	}

	public function testListenerRegistryTraitGetterAndSetter(): void {
		$event = new class() implements TaggableEvent {
			public function __construct( private string $name = '' ) {}

			public function setName( string $name ) {
				$this->name = $this->name . '::' . $name;
			}

			public function getName(): string {
				return $this->name;
			}

			public function getEntry(): string {
				return ProvidersTest::class;
			}
		};

		$eventType = $event::class;

		$registrar = new class( $eventType ) implements ListenerRegistry {
			use ListenerRegistrar;

			public function __construct( private string $eventType ) {}

			protected function isValid( object $event ): bool {
				return is_a( $event, $this->eventType );
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

		foreach ( $listeners as $sortedListeners ) {  // Collection of Both generators: global and scoped.
			foreach ( $sortedListeners as $listener ) { // Collection by sorting number.
				foreach ( $listener as $invoke ) {        // Collection inside each sortable collection.
					$invoke( $event );
				}
			}
		}

		$this->assertSame(
			message: 'Listeners must be sorted by priority.',
			expected: '::global::third::first::second',
			actual: $event->getName()
		);

		$invalidListeners = $registrar->getListenersForEvent( $this );

		$this->assertEmpty( $invalidListeners->current() );
	}
}
