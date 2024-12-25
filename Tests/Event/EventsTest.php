<?php
// phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
// phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Tests\Event;

use Closure;
use WeakMap;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\StoppableEventInterface;
use TheWebSolver\Codegarage\Container\Container;
use TheWebSolver\Codegarage\Container\Data\Binding;
use TheWebSolver\Codegarage\Container\Event\EventType;
use TheWebSolver\Codegarage\Container\Event\BuildingEvent;
use TheWebSolver\Codegarage\Container\Pool\CollectionStack;
use TheWebSolver\Codegarage\Container\Event\AfterBuildEvent;
use TheWebSolver\Codegarage\Container\Event\BeforeBuildEvent;
use TheWebSolver\Codegarage\Container\Traits\StopPropagation;

class EventsTest extends TestCase {
	/** @return array{0:MockObject&CollectionStack,1:MockObject&CollectionStack} */
	private function getDecoratorsAndUpdatersMock(): array {
		return array( $this->createMock( CollectionStack::class ), $this->createMock( CollectionStack::class ) );
	}

	public function testBeforeBuildEvent(): void {
		$eventWithArray = new BeforeBuildEvent( 'test' );

		$this->assertSame( 'test', $eventWithArray->getEntry() );
		$this->assertNull( $eventWithArray->getParams() );

		$eventWithArray->setParam( 'key', 'value' );

		$this->assertSame( array( 'key' => 'value' ), $eventWithArray->getParams() );

		$eventWithWeakMap = new BeforeBuildEvent( 'withWeakMap', params: new WeakMap() );

		$this->assertSame( 'withWeakMap', $eventWithWeakMap->getEntry() );
		$this->assertInstanceOf( WeakMap::class, $eventWithWeakMap->getParams() );
		$this->assertEmpty( $eventWithWeakMap->getParams() );

		$eventWithWeakMap->setParam( EventType::BeforeBuild, EventType::BeforeBuild->name );

		$this->assertSame( 'BeforeBuild', $eventWithWeakMap->getParams()[ EventType::BeforeBuild ] );
	}

	public function testBuildingEvent(): void {
		/** @var Container */
		$app   = $this->createMock( Container::class );
		$event = new BuildingEvent( $app, 'string $id' );

		$this->assertSame( 'string $id', $event->getEntry() );
		$this->assertSame( $app, $event->app() );
		$this->assertNull( $event->getBinding() );
		$this->assertSame( 'test', $event->setBinding( new Binding( 'test' ) )->getBinding()->material );
	}

	public function testAfterBuildEvent(): void {
		$mocks = $this->getDecoratorsAndUpdatersMock();
		$event = new AfterBuildEvent( 'entry', ...$mocks );

		$this->assertSame( 'entry', $event->getEntry() );
		$this->assertSame( $mocks[0], $event->getDecorators() );
		$this->assertSame( $mocks[1], $event->getUpdaters() );
	}

	/** @dataProvider provideDecoratorsAndUpdatersToAfterBuildEvent */
	public function testAfterBuildEventAcceptsEitherStringOrCallableAsDecorator(
		string|Closure $decorator,
		Closure $updater
	): void {
		[ $decorators, $updaters ] = $this->getDecoratorsAndUpdatersMock();
		$event                     = new AfterBuildEvent( 'testDecorators', $decorators, $updaters );

		$decorators->expects( $this->once() )
			->method( 'set' )
			->with( 'testDecorators', $decorator );

		$updaters->expects( $this->once() )
			->method( 'set' )
			->with( 'testDecorators', $updater );

		$event->decorateWith( $decorator )->update( with: $updater );
	}

	public function provideDecoratorsAndUpdatersToAfterBuildEvent(): array {
		return array(
			array( self::class, function () {} ),
			array( function () {}, $this->provideDecoratorsAndUpdatersToAfterBuildEvent( ... ) ),
		);
	}

	public function testPropagationStoppableInterfaceWithTraitUsedByAllEvents(): void {
		$event = new class() implements StoppableEventInterface {
			use StopPropagation;
		};

		$this->assertFalse( $event->isPropagationStopped() );
		$this->assertTrue( $event->stopPropagation()->isPropagationStopped() );
	}
}
