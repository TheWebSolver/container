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
use TheWebSolver\Codegarage\Lib\Container\Helper\Event;

class EventTest extends TestCase {
	/**
	 * @param mixed[] $params
	 * @dataProvider provideSubscribeAndInvokeForBeforeBuild
	 */
	public function testEventBeforeBuild(
		array $params,
		?Closure $callback,
		Closure|string $id,
		Closure|string $fireId,
	): void {
		$container = $this->createMock( Container::class );
		$event     = new Event( $container );

		$container->expects( $this->once() )
			->method( 'resolveEntryFrom' )
			->with( $id )
			->willReturn( $id );

		$event->subscribeWith( $id, $callback, Event::FIRE_BEFORE_BUILD );
		$event->fireBeforeBuild( $fireId, $params );
	}

	/** @return mixed[] */
	public function provideSubscribeAndInvokeForBeforeBuild(): array {
		return array(
			array(
				'params'   => array( 'name' => 'test' ),
				'callback' => static function ( string $type, array $params, Container $container ) {
					self::assertSame( expected: 'idAsAliasAndScoped', actual: $type );
					self::assertSame( expected: 'test', actual: $params['name'] );
					self::assertInstanceOf( MockObject::class, $container );
				},
				'id'       => 'idAsAliasAndScoped',
				'fireId'   => 'idAsAliasAndScoped', // Fire Id same as subscribe Id if alias used.
			),
			array(
				'params'   => array( 'name' => 'test' ),
				'callback' => static function ( string $type, array $params, Container $container ) {
					self::assertSame( expected: self::class, actual: $type );
					self::assertSame( expected: 'test', actual: $params['name'] );
					self::assertInstanceOf( MockObject::class, $container );
				},
				'id'       => TestCase::class, // Scoped Id as fqcn.
				'fireId'   => self::class,     // Must be subscribeType subclass. Else subscriber wont fire.
			),
			array(
				'params'   => array( 'global' => 'closure as ID' ),
				'callback' => null, // Subscribe Type is a closure. Providing subscriber wont register event.
				'id'       => static function ( Closure $type, array $params, Container $container ) {
					self::assertSame( expected: 'Global Scoped Hook', actual: $type( $params ) );
					self::assertInstanceOf( MockObject::class, $container );
				},
				'fireId'   => static function ( array $params ) {
					self::assertSame( expected: 'closure as ID', actual: $params['global'] );

					return 'Global Scoped Hook';
				},
			),
		);
	}

	/** @dataProvider provideSubscribeAndInvokeForAfterBuild */
	public function testEventAfterBuild(
		?Closure $callback,
		Closure|string $id,
		string $fireId,
		object $resolved
	): void {
		$container = $this->createMock( Container::class );
		$event     = new Event( $container );

		$container->expects( $this->exactly( 2 ) )
			->method( 'resolveEntryFrom' )
			->with( $id )
			->willReturn( $id );

		$event->subscribeWith( $id, $callback, Event::FIRE_BUILT );
		$event->subscribeWith( $id, $callback, Event::FIRE_AFTER_BUILT );
		$event->fireAfterBuild( $fireId, $resolved );
	}

	public function testEventAfterBuildSameIdMultipleTimes(): void {
		$container = $this->createMock( Container::class );
		$event     = new Event( $container );
		$args      = array_filter(
			array: $this->provideSubscribeAndInvokeForAfterBuild(),
			callback: fn ( array $data ) => _Test_Resolved__container_object__::class === $data['id']
		);

		$this->assertCount( 2, $args );

		foreach ( $args as $data ) {
			$event->subscribeWith( $data['id'], $data['callback'], Event::FIRE_BUILT );
			$event->subscribeWith( $data['id'], $data['callback'], Event::FIRE_AFTER_BUILT );
		}

		$event->fireAfterBuild( _Test_Resolved__container_object__::class, $data['resolved'] );
	}

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
					self::assertInstanceOf( MockObject::class, $container );
				},
				'fireId'   => 'idAsAliasAndScoped',
				'resolved' => new class { public function do() { return 'closure instead if ID'; } },
			),
		);
	}
}

final class _Test_Resolved__container_object__ { // phpcs:ignore
	public function __construct( public string $data = 'Resolved Object' ) {}
}
