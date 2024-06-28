<?php
/**
 * Event test.
 *
 * @package TheWebSolver\Codegarage\Test
 *
 * @phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed
 * @phpcs:disable Universal.Classes.RequireAnonClassParentheses.Missing
 * @phpcs:disable Squiz.Functions.MultiLineFunctionDeclaration.ContentAfterBrace
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Helper\Event;

class EventTest extends TestCase {
	private ?Container $container;
	private ?Event $event;

	protected function setUp(): void {
		$this->container = $this->createMock( Container::class );
		$this->event     = new Event( $this->container );
	}

	protected function tearDown(): void {
		$this->container = null;
		$this->event     = null;
	}

	/**
	 * @param mixed[] $params
	 * @dataProvider provideSubscribeAndInvokeForBeforeBuild
	 */
	public function testEventBeforeBuild(
		array $params,
		?Closure $callback,
		Closure|string $id,
		string $fireId,
	): void {
		if ( ! $id instanceof Closure ) {
			$this->container->expects( $this->once() )
				->method( 'getEntryFrom' )
				->with( $id )
				->willReturn( $id );
		}

		$this->event->subscribeWith( $id, $callback, Event::FIRE_BEFORE_BUILD );
		$this->event->fireBeforeBuild( $fireId, $params );
	}

	/** @return mixed[] */
	public function provideSubscribeAndInvokeForBeforeBuild(): array {
		return array(
			array(
				'params'   => array( 'name' => 'test' ),
				'callback' => static function ( string $type, array $params, Container $container ) {
					self::assertSame( expected: 'idAsAliasAndScoped', actual: $type );
					self::assertSame( expected: 'test', actual: $params['name'] );
				},
				'id'       => 'idAsAliasAndScoped',
				'fireId'   => 'idAsAliasAndScoped', // Fire Id same as subscribe Id if alias used.
			),
			array(
				'params'   => array( 'name' => 'test' ),
				'callback' => static function ( string $type, array $params, Container $container ) {
					self::assertSame( expected: self::class, actual: $type );
					self::assertSame( expected: 'test', actual: $params['name'] );
				},
				'id'       => TestCase::class, // Scoped Id as fqcn.
				'fireId'   => self::class,     // Must be subscribeType subclass. Else subscriber wont fire.
			),
			array(
				'params'   => array( 'global' => 'Global scoped without ID' ),
				'callback' => null, // Subscribe Type is a closure. Providing subscriber wont register event.
				'id'       => static function ( string $type, array $params, Container $container ) {
					self::assertSame( expected: 'doesNotMatterAsItIsGlobalScoped', actual: $type );
					self::assertSame( array( 'global' => 'Global scoped without ID' ), $params );
				},
				'fireId'   => 'doesNotMatterAsItIsGlobalScoped',
			),
		);
	}

	/** @dataProvider provideEventDuringBuild */
	public function testDuringBuild(
		mixed $expected,
		string $id,
		string $depName,
		?Closure $callback
	): void {
		if ( null === $expected ) {
			$this->assertNull( $this->event->fireDuringBuild( $id, $depName ) );

			return;
		}

		$this->container->expects( $this->once() )
			->method( 'getEntryFrom' )
			->with( $id )
			->willReturn( $id );

		$this->event->subscribeDuringBuild( $id, $depName, $callback );

		$this->assertSame( $expected, $this->event->fireDuringBuild( $id, $depName )->concrete );
	}

	/** @return mixed[] */
	public function provideEventDuringBuild(): array {
		return array(
			array(
				'expected' => 'value1',
				'id'       => 'buildingOne',
				'depName'  => 'param1',
				'callback' => static function ( string $depName ): Binding {
					self::assertSame( 'param1', $depName );

					return new Binding( 'value1' );
				},
			),
			array(
				'expected' => null,
				'id'       => 'not subscribed',
				'depName'  => 'does not matter',
				'callback' => null,
			),
		);
	}

	/** @dataProvider provideSubscribeAndInvokeForAfterBuild */
	public function testEventAfterBuild(
		?Closure $callback,
		Closure|string $id,
		?string $fireId,
		object $resolved
	): void {
		if ( ! $id instanceof Closure ) {
			$this->container->expects( $this->once() )
				->method( 'getEntryFrom' )
				->with( $id )
				->willReturn( $id );
		}

		$this->event->subscribeWith( $id, $callback, Event::FIRE_BUILT );
		$this->event->fireAfterBuild( $fireId, $resolved );
	}

	/** @return mixed[] */
	public function provideSubscribeAndInvokeForAfterBuild(): array {
		$resolved = new _Test_Resolved__container_object__();

		return array(
			array(
				'callback' => static function ( object $type, Container $container ) {
					self::assertInstanceOf( _Test_Resolved__container_object__::class, $type );
					self::assertSame( expected: 'Resolved Object', actual: $type->data );
				},
				'id'       => 'idAsAliasAndScoped',
				'fireId'   => 'idAsAliasAndScoped',
				'resolved' => $resolved,
			),
			array(
				'callback' => static function ( object $type, Container $container ) {
					self::assertInstanceOf( _Test_Resolved__container_object__::class, $type );
					self::assertSame( expected: 'Resolved Object', actual: $type->data );
				},
				'id'       => _Test_Resolved__container_object__::class,
				'fireId'   => _Test_Resolved__container_object__::class,
				'resolved' => $resolved,
			),
			array(
				'callback' => null,
				'id'       => static function ( object $type, Container $container ) {
					self::assertSame( expected: 'closure instead if ID', actual: $type->do() );
				},
				'fireId'   => 'doesNotMatterAsItIsGlobalScoped',
				'resolved' => new class { public function do() { return 'closure instead if ID'; } },
			),
		);
	}
}

final class _Test_Resolved__container_object__ { // phpcs:ignore
	public function __construct( public string $data = 'Resolved Object' ) {}
}
