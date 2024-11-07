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

namespace TheWebSolver\Codegarage\Lib\Container\Tests\Event;

use PHPUnit\Framework\TestCase;
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

	private function createDispatcherStub(): EventDispatcher {
		/** @var EventDispatcher */
		$dispatcher = $this->createStub( EventDispatcher::class );

		return $dispatcher;
	}

	public function testSuppressedEventDispatchersShallNotBeReAssigned(): void {
		$this->manager->setDispatcher( false, EventType::BeforeBuild );

		$this->assertNull( $this->manager->getDispatcher( EventType::BeforeBuild ) );
		$this->assertTrue( $this->manager->isDispatcherDisabled( EventType::BeforeBuild ) );
		$this->assertTrue( $this->manager->isDispatcherAssigned( EventType::BeforeBuild ) );
		$this->assertFalse(
			condition: $this->manager->setDispatcher( $this->createDispatcherStub(), EventType::BeforeBuild ),
			message: 'Suppressed Event Dispatcher shall not be able to be re-assigned for same event type.'
		);
	}

	public function testEventManagerSetterGetter(): void {
		$this->assertEventDispatchersAssignedStatus( expected: false );
		$this->assertTrue( $this->manager->setDispatcher( $beforeBuild = $this->createDispatcherStub(), EventType::BeforeBuild ) );
		$this->assertTrue( $this->manager->setDispatcher( $building = $this->createDispatcherStub(), EventType::Building ) );
		$this->assertFalse(
			condition: $this->manager->setDispatcher( $this->createDispatcherStub(), EventType::Building ),
			message: 'Already assigned Event Dispatcher shall not be able to be re-assigned for same event type.'
		);
		$this->assertSame( $beforeBuild, $this->manager->getDispatcher( EventType::BeforeBuild ) );
		$this->assertSame( $building, $this->manager->getDispatcher( EventType::Building ) );
		$this->assertNull( $this->manager->getDispatcher( EventType::AfterBuild ) );
		$this->assertTrue(
			condition: $this->manager->setDispatcher( $afterBuild = $this->createDispatcherStub(), EventType::AfterBuild ),
			message: 'Must be able to assign dispatcher if not previously set.'
		);
		$this->assertSame( $afterBuild, $this->manager->getDispatcher( EventType::AfterBuild ) );
		$this->assertEventDispatchersAssignedStatus( expected: true );
	}

	private function assertEventDispatchersAssignedStatus( bool $expected ): void {
		foreach ( EventType::cases() as $eventType ) {
			$this->assertSame( $expected, actual: $this->manager->isDispatcherAssigned( $eventType ) );
		}
	}
}
