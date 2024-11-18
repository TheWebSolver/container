<?php
/**
 * Event Manager test.
 *
 * @package TheWebSolver\Codegarage\Test
 *
 * @phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
 * @phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Tests\Event;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use TheWebSolver\Codegarage\Lib\Container\Event\EventType;
use TheWebSolver\Codegarage\Lib\Container\Event\EventDispatcher;
use TheWebSolver\Codegarage\Lib\Container\Event\Manager\EventManager;

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

	public function testEventManagerSetterGetter(): void {
		$this->assertEventDispatchersAssignedStatus( expected: false );
		$this->assertTrue( $this->manager->setDispatcher( $beforeBuild = $this->createDispatcherMock(), EventType::BeforeBuild ) );
		$this->assertTrue( $this->manager->setDispatcher( $building = $this->createDispatcherMock(), EventType::Building ) );
		$this->assertFalse(
			condition: $this->manager->setDispatcher( $this->createDispatcherMock(), EventType::Building ),
			message: 'Already assigned Event Dispatcher shall not be able to be re-assigned for same event type.'
		);
		$this->assertSame( $beforeBuild, $this->manager->getDispatcher( EventType::BeforeBuild ) );
		$this->assertSame( $building, $this->manager->getDispatcher( EventType::Building ) );
		$this->assertNull( $this->manager->getDispatcher( EventType::AfterBuild ) );
		$this->assertTrue(
			condition: $this->manager->setDispatcher( $afterBuild = $this->createDispatcherMock(), EventType::AfterBuild ),
			message: 'Must be able to assign dispatcher if not previously set.'
		);
		$this->assertSame( $afterBuild, $this->manager->getDispatcher( EventType::AfterBuild ) );
		$this->assertEventDispatchersAssignedStatus( expected: true );

		foreach ( array( $beforeBuild, $building, $afterBuild ) as $dispatcher ) {
			$dispatcher->expects( $this->exactly( 3 ) )->method( 'reset' );
		}

		$this->manager->reset();
		$this->manager->reset( null );
	}

	private function assertEventDispatchersAssignedStatus( bool $expected ): void {
		foreach ( EventType::cases() as $eventType ) {
			$this->assertSame( $expected, actual: $this->manager->isDispatcherAssigned( $eventType ) );
		}
	}
}
