<?php
/**
 * Container test.
 *
 * @package TheWebSolver\Codegarage\Test
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Lib\Container\Container;

class ContainerTest extends TestCase {
	private ?Container $container;

	protected function setUp(): void {
		$this->container = new Container();
	}

	protected function tearDown(): void {
		$this->container = null;
	}

	public function testSingleton(): void {
		$this->assertSame( Container::boot(), Container::boot() );
	}

	public function testArrayAccessible(): void {
		$this->container[ self::class ] = self::class;

		$this->assertTrue( isset( $this->container[ self::class ] ) );
		$this->assertInstanceOf( self::class, $this->container[ self::class ] );

		unset( $this->container[ self::class ] );

		$this->assertFalse( isset( $this->container[ self::class ] ) );
	}

	public function testResolvingEntry(): void {
		$this->assertSame( 'test', $this->container->resolveEntryFrom( 'test' ) );
		$this->assertSame( $c = static function () {}, $this->container->resolveEntryFrom( $c ) );

		$this->container->alias( entry: self::class, alias: 'test' );
		$this->assertSame( self::class, $this->container->resolveEntryFrom( 'test' ) );

		$this->container->bind( id: 'test', concrete: self::class );
		$this->assertSame( 'test', $this->container->resolveEntryFrom( 'test' ) );

		// This works but is wrong. Always alias concrete as entry and not the other way around.
		$this->container->alias( entry: 'test', alias: self::class );
		$this->assertSame( 'test', $this->container->resolveEntryFrom( self::class ) );
	}

	public function testBasicSetterGetterAndAssertionIntegration(): void {
		$this->assertFalse( $this->container->has( entryOrAlias: 'testClass' ) );
		$this->assertFalse( $this->container->has( entryOrAlias: self::class ) );
		$this->assertFalse( $this->container->hasBinding( 'testClass' ) );
		$this->assertFalse( $this->container->isAlias( 'testClass' ) );
		$this->assertFalse( $this->container->resolved( id: 'testClass' ) );

		$this->container->alias( entry: self::class, alias: 'testClass' );

		$this->assertTrue( $this->container->isAlias( name: 'testClass' ) );
		$this->assertSame( self::class, $this->container->getEntryFrom( alias: 'testClass' ) );
		$this->assertInstanceOf( self::class, $this->container->get( 'testClass' ) );

		$this->container->bind( id: 'testClass', concrete: self::class );

		// Bind will purge alias from the alias pool coz no need for storing same alias
		// multiple places (like in alias pool as well as in binding pool).
		$this->assertFalse( $this->container->isAlias( name: 'testClass' ) );
		$this->assertTrue( $this->container->has( entryOrAlias: 'testClass' ) );
		$this->assertFalse( $this->container->has( entryOrAlias: self::class ), 'Bound with alias.' );
		$this->assertTrue( $this->container->hasBinding( id: 'testClass' ) );
		$this->assertFalse( $this->container->hasBinding( id: self::class ), 'Bound using alias.' );
		$this->assertSame( 'testClass', $this->container->getEntryFrom( alias: 'testClass' ) );

		$this->assertInstanceOf( self::class, $this->container->get( id: 'testClass' ) );
		$this->assertTrue( $this->container->resolved( id: 'testClass' ) );
		$this->assertTrue( $this->container->resolved( id: self::class ) );

		$this->assertFalse( $this->container->isSingleton( id: stdClass::class ) );

		$this->container->singleton( id: stdClass::class, concrete: null );

		$this->assertTrue( $this->container->isSingleton( id: stdClass::class ) );
		$this->assertTrue( $this->container->isShared( id: stdClass::class ) );

		$this->assertSame(
			expected: $this->container->get( id: stdClass::class ),
			actual: $this->container->get( id: stdClass::class )
		);

		$this->assertFalse( $this->container->isInstance( id: 'instance' ) );
		$this->assertFalse( $this->container->resolved( id: 'instance' ) );

		$newClass = $this->container->instance( id: 'instance', instance: new class() {} );

		$this->assertTrue( $this->container->isInstance( id: 'instance' ) );
		$this->assertTrue( $this->container->isShared( id: 'instance' ) );
		$this->assertSame( $newClass, $this->container->get( id: 'instance' ) );
		$this->assertTrue( $this->container->resolved( id: 'instance' ) );
	}

	public function testAliasAndGetWithoutBinding(): void {
		$this->container->alias( entry: self::class, alias: 'testClass' );

		$this->assertInstanceOf( self::class, $this->container->get( id: 'testClass' ) );
	}

	public function testKeepingBothAliasAndBinding(): void {
		$this->container->alias( entry: self::class, alias: 'test' );
		$this->container->bind( id: self::class, concrete: null );

		$this->assertTrue( $this->container->isAlias( name: 'test' ) );
		$this->assertTrue( $this->container->hasBinding( id: self::class ) );
		$this->assertSame( self::class, $this->container->getEntryFrom( alias: 'test' ) );
		$this->assertSame( self::class, $this->container->getEntryFrom( alias: self::class ) );
	}

	public function testContextualBinding(): void {
		$class = _Test_Resolved__container_object__::class;
		$this->assertFalse(
			$this->container->hasContextualBinding( concrete: $class )
		);

		$this->container->addContext( concrete: $class, id: 'data', implementation: 'update' );

		$this->assertTrue(
			$this->container->hasContextualBinding( concrete: $class )
		);

		$this->assertSame( expected: 'update', actual: $this->container->get( id: $class )->data );

		$this->container->when( concrete: $class )
			->needs( requirement: 'data' )
			->give( value: 'With Builder' );

		$this->assertSame( 'With Builder', $this->container->get( id: $class )->data );

		$this->container->when( concrete: $class )
			->needs( requirement: 'data' )
			->give( value: static fn (): string => 'With Builder from closure' );

		$this->assertSame( 'With Builder from closure', $this->container->get( id: $class )->data );
	}
}
