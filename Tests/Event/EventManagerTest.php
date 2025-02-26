<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Tests\Event;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use TheWebSolver\Codegarage\Container\Event\EventType;
use TheWebSolver\Codegarage\Container\Event\EventDispatcher;
use TheWebSolver\Codegarage\Container\Event\Manager\EventManager;
use TheWebSolver\Codegarage\Container\Event\Provider\BuildingListenerProvider;
use TheWebSolver\Codegarage\Container\Event\Provider\AfterBuildListenerProvider;
use TheWebSolver\Codegarage\Container\Event\Provider\BeforeBuildListenerProvider;

class EventManagerTest extends TestCase {
	private EventManager $manager;

	protected function setUp(): void {
		$this->manager = new EventManager();
	}

	protected function tearDown(): void {
		$this->manager = new EventManager();
	}

	private function createDispatcherMock(): EventDispatcher&MockObject {
		/** @var EventDispatcher&MockObject */
		$dispatcher = $this->createMock( EventDispatcher::class );

		return $dispatcher;
	}

	public function testSuppressedEventDispatchersShallNotBeReAssigned(): void {
		$this->manager->setDispatcher( false, EventType::BeforeBuild );

		$this->assertNull( $this->manager->getDispatcher( EventType::BeforeBuild ) );
		$this->assertTrue( $this->manager->isDispatcherDisabled( EventType::BeforeBuild ) );
		$this->assertTrue( $this->manager->isDispatcherAssigned( EventType::BeforeBuild ) );
		$this->assertFalse(
			condition: $this->manager->setDispatcher( $this->createDispatcherMock(), EventType::BeforeBuild ),
			message: 'Suppressed Event Dispatcher shall not be able to be re-assigned for same event type.'
		);
	}

	public function testEventManagerSetterGetterAndResetterIntegration(): void {
		$this->assertEventDispatchersAssignedStatus( expected: false );

		$dispatchers = array(
			$beforeBuild = new EventDispatcher( new BeforeBuildListenerProvider() ),
			$building    = new EventDispatcher( new BuildingListenerProvider() ),
			$afterBuild  = new EventDispatcher( new AfterBuildListenerProvider() ),
		);

		$this->assertTrue( $this->manager->setDispatcher( $beforeBuild, EventType::BeforeBuild ) );
		$this->assertTrue( $this->manager->setDispatcher( $building, EventType::Building ) );
		$this->assertFalse(
			condition: $this->manager->setDispatcher( $this->createDispatcherMock(), EventType::Building ),
			message: 'Already assigned Event Dispatcher shall not be able to be re-assigned for same event type.'
		);
		$this->assertSame( $beforeBuild, $this->manager->getDispatcher( EventType::BeforeBuild ) );
		$this->assertSame( $building, $this->manager->getDispatcher( EventType::Building ) );
		$this->assertNull( $this->manager->getDispatcher( EventType::AfterBuild ) );
		$this->assertTrue(
			condition: $this->manager->setDispatcher( $afterBuild, EventType::AfterBuild ),
			message: 'Must be able to assign dispatcher if not previously set.'
		);
		$this->assertSame( $afterBuild, $this->manager->getDispatcher( EventType::AfterBuild ) );
		$this->assertEventDispatchersAssignedStatus( expected: true );

		array_walk( $dispatchers, $this->assertListenersAddedWithAndWithoutEntries( ... ) );

		$this->manager->reset( null );

		array_walk( $dispatchers, $this->assertResetListenersWithoutEntriesAndAddAnotherTestListener( ... ) );

		$this->manager->reset( 'anotherTest' );

		array_walk( $dispatchers, $this->assertResetListenersOnlyForAnotherTest( ... ) );

		$this->manager->reset( '' );

		array_walk( $dispatchers, $this->assertResetAllListenersRegisteredWithEntries( ... ) );

		array_walk( $dispatchers, $this->assertListenersAddedWithAndWithoutEntries( ... ) );

		$this->manager->reset();

		array_walk( $dispatchers, $this->assertResetAllListenersIfArgNotPassedWhenResetting( ... ) );
	}

	private function assertEventDispatchersAssignedStatus( bool $expected ): void {
		foreach ( EventType::cases() as $eventType ) {
			$this->assertSame( $expected, actual: $this->manager->isDispatcherAssigned( $eventType ) );
		}
	}

	private function assertListenersAddedWithAndWithoutEntries( EventDispatcher $dispatcher ): void {
		$dispatcher->addListener( $this->any( ... ), null );
		$dispatcher->addListener( $this->any( ... ), 'test' );

		$this->assertCount( 1, $dispatcher->getListeners() );
		$this->assertCount( 1, $dispatcher->getListeners( '' ) );
	}

	private function assertResetListenersWithoutEntriesAndAddAnotherTestListener( EventDispatcher $dispatcher ): void {
		$this->assertCount( 0, $dispatcher->getListeners( null ) );

		$dispatcher->addListener( $this->exactly( ... ), 'anotherTest' );

		$this->assertCount( 2, $dispatcher->getListeners( '' ) );
	}

	private function assertResetListenersOnlyForAnotherTest( EventDispatcher $dispatcher ): void {
			$this->assertArrayHasKey( 'anotherTest', $dispatcher->getListeners( '' ) );
			$this->assertEmpty( $dispatcher->getListeners( 'anotherTest' ) );
			$this->assertNotEmpty( $dispatcher->getListeners( 'test' ) );
	}

	private function assertResetAllListenersRegisteredWithEntries( EventDispatcher $dispatcher ): void {
		$this->assertEmpty( $dispatcher->getListeners( '' ) );
		$this->assertFalse( $dispatcher->hasListeners( '' ) );
	}

	private function assertResetAllListenersIfArgNotPassedWhenResetting( EventDispatcher $dispatcher ): void {
		$this->assertEmpty( $dispatcher->getListeners( null ) );
		$this->assertEmpty( $dispatcher->getListeners( '' ) );
		$this->assertFalse( $dispatcher->hasListeners( null ) );
		$this->assertFalse( $dispatcher->hasListeners( '' ) );
	}
}
