<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Tests\Event;

use Generator;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\EventDispatcher\ListenerProviderInterface;
use TheWebSolver\Codegarage\Container\Event\BuildingEvent;
use TheWebSolver\Codegarage\Container\Event\AfterBuildEvent;
use TheWebSolver\Codegarage\Container\Interfaces\Compilable;
use TheWebSolver\Codegarage\Container\Event\BeforeBuildEvent;
use TheWebSolver\Codegarage\Container\Traits\ListenerCompiler;
use TheWebSolver\Codegarage\Container\Interfaces\TaggableEvent;
use TheWebSolver\Codegarage\Container\Traits\ListenerRegistrar;
use TheWebSolver\Codegarage\Container\Interfaces\ListenerRegistry;
use TheWebSolver\Codegarage\Container\Event\Provider\BuildingListenerProvider;
use TheWebSolver\Codegarage\Container\Event\Provider\AfterBuildListenerProvider;
use TheWebSolver\Codegarage\Container\Event\Provider\BeforeBuildListenerProvider;

class ProvidersTest extends TestCase {
	/** @param class-string<ListenerProviderInterface &ListenerRegistry> $className */
	#[ DataProvider( 'provideListenerProvidersAndValidEvent' ) ]
	public function testListenerProviderProvidesListenersOnlyIfGivenObjectIsValidEvent(
		string $className,
		string $eventClass
	): void {
		$listenerProvider = new $className();

		$listenerProvider->addListener( function ( $e ) {}, null, 2 );
		$listenerProvider->addListener( function ( $e ) {}, self::class, 7 );
		$listenerProvider->addListener( function ( $e ) {}, self::class, 3 );
		$listenerProvider->addListener( function ( $e ) {}, self::class, 3 );
		$listenerProvider->addListener( function ( $e ) {}, parent::class, 7 );

		// BeforeBuildListenerProvider can listen for event entry that is subclass of another entry.
		$event = $this->createMock( $eventClass );

		$event->method( 'getEntry' )->willReturn( self::class );

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

	#[ Test ]
	public function itValidatesEntryIfIsASubclass(): void {
		$listenerProvider = new BeforeBuildListenerProvider();
		$event            = new BeforeBuildEvent( self::class );

		$listenerProvider->addListener( function ( $e ) {}, null, 2 );
		$listenerProvider->addListener( function ( $e ) {}, self::class, 7 );
		$listenerProvider->addListener( function ( $e ) {}, self::class, 3 );
		$listenerProvider->addListener( function ( $e ) {}, self::class, 3 );
		$listenerProvider->addListener( function ( $e ) {}, parent::class, 7 );

		$generator = $listenerProvider->getListenersForEvent( $event );

		$this->assertSame( 0, $generator->key(), 'It should only be the non-entry listener.' );
		$generator->next();
		$this->assertSame( self::class, $generator->key(), 'It should be listeners with current class.' );
		$generator->next();
		$this->assertSame( parent::class, $generator->key(), 'It should be listeners with parent class.' );
		$this->assertTrue( $generator->valid() );
	}

	public static function provideListenerProvidersAndValidEvent(): array {
		return array(
			array( BeforeBuildListenerProvider::class, BeforeBuildEvent::class ),
			array( BuildingListenerProvider::class, BuildingEvent::class ),
			array( AfterBuildListenerProvider::class, AfterBuildEvent::class ),
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

		$this->assertTrue( $registrar->hasListeners( null ) );
		$this->assertTrue( $registrar->hasListeners( '' ) );

		foreach ( array( self::class, parent::class ) as $entry ) {
			$this->assertTrue( $registrar->hasListeners( $entry ) );
		}

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

		foreach ( $listeners as $yielded => $generators ) {
			foreach ( $generators as $sorted => $listeners ) {
				foreach ( $listeners as $listener ) {
					$listener( $event );
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

		$registrar->reset( parent::class );

		$this->assertFalse( $registrar->hasListeners( parent::class ) );
		$this->assertTrue( $registrar->hasListeners( self::class ) );
		$this->assertTrue( $registrar->hasListeners( '' ) );

		$registrar->reset( '' );

		$this->assertFalse(
			condition: $registrar->hasListeners( '' ),
			message: 'Listeners for entries must be reset when empty string passed.'
		);

		foreach ( array( self::class, parent::class ) as $entry ) {
			$this->assertFalse( $registrar->hasListeners( $entry ) );
		}

		$this->assertTrue(
			condition: $registrar->hasListeners(),
			message: 'Listeners without entries must not be reset when empty string passed.'
		);

		$registrar->reset();

		$this->assertFalse(
			condition: $registrar->hasListeners(),
			message: 'Listeners without entries must be reset when null (default value) passed.'
		);
	}

	public function testListenerProviderWithCompiledListenersAsArray(): void {
		$provider        = $this->getListenerProviderStub();
		$withNoListeners = $provider::fromCompiledArray( array() );

		$this->assertEmpty( $withNoListeners->getListeners() );
		$this->assertEmpty( $withNoListeners->getListeners( 'test' ) );

		$nonEntryListeners = array(
			'listeners' => array(
				10 => array( $this->any( ... ), array( self::class, 'assertTrue' ), self::class . '::assertNull' ),
				20 => array( self::assertContains( ... ) ),
			),
		);

		$withNonEntryListeners = $provider::fromCompiledArray( $nonEntryListeners );

		$this->assertEmpty( $withNonEntryListeners->getListeners( 'test' ) );
		$this->assertCount( 2, $withNonEntryListeners->getListeners() );
		$this->assertCount( 3, $listeners = $withNonEntryListeners->getListeners()[10] );

		foreach ( $listeners as $listener ) {
			$this->assertIsCallable( $listener );
		}

		$allListeners = array(
			...$nonEntryListeners,
			'listenersForEntry' => array(
				'test' => array(
					5  => array( $this->exactly( ... ), array( self::class, 'assertContains' ), self::class . '::assertTrue' ),
					15 => array( self::assertCount( ... ) ),
				),
			),
		);

		$withAllListeners = $provider::fromCompiledArray( $allListeners );

		$this->assertCount( 2, $testListeners = $withAllListeners->getListeners( 'test' ) );
		$this->assertCount( 1, $testListeners[15] );

		foreach ( $testListeners[5] as $listener ) {
			$this->assertIsCallable( $listener );
		}
	}

	public function testListenerProviderWithCompiledListenersFromFile(): void {
		$provider      = $this->getListenerProviderStub();
		$withListeners = $provider::fromCompiledFile( dirname( __DIR__ ) . '/File/compiledForListenerProvider.php' );

		$this->assertCount( 3, $withNoEntry = $withListeners->getListeners()[10] );
		$this->assertCount( 3, $withEntry = $withListeners->getListeners( 'test' )[5] );

		$listeners = array( ...$withNoEntry, ...$withEntry );

		array_walk( $listeners, fn( $listener ) => self::assertIsCallable( $listener ) );

		$this->expectException( RuntimeException::class );
		$provider::fromCompiledFile( 'non-existing-compiled-file.php' );
	}

	private function getListenerProviderStub(): ListenerProviderInterface&ListenerRegistry&Compilable {
		return new class() implements ListenerProviderInterface, ListenerRegistry, Compilable {
			use ListenerRegistrar, ListenerCompiler;

			protected function isValid( object $event ): bool {
				return true;
			}
		};
	}
}
