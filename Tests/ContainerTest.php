<?php
/**
 * Container test.
 *
 * @package TheWebSolver\Codegarage\Test
 *
 * @phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
 * @phpcs:disable PEAR.NamingConventions.ValidClassName.StartWithCapital
 * @phpcs:disable PEAR.NamingConventions.ValidClassName.Invalid
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;

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
		$this->assertInstanceOf( self::class, $this->container->get( id: self::class ) );
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

		$this->container->addContext( with: 'update', concrete: $class, id: '$data' );

		$this->assertTrue(
			$this->container->hasContextualBinding( concrete: $class )
		);

		$this->assertSame( expected: 'update', actual: $this->container->get( id: $class )->data );

		$this->container->when( concrete: $class )
			->needs( requirement: '$data' )
			->give( value: 'With Builder' );

		$this->assertSame( 'With Builder', $this->container->get( id: $class )->data );

		$this->container->when( concrete: $class )
			->needs( requirement: '$data' )
			->give( value: static fn (): string => 'With Builder from closure' );

		$this->assertSame( 'With Builder from closure', $this->container->get( id: $class )->data );

		$stack = $this->createMock( Stack::class );
		$class = _TestStack__Contextual_Binding_WithArrayAccess::class;

		$stack->expects( $this->once() )
			->method( 'offsetExists' )
			->with( 'testKey' )
			->willReturn( true );

		$this->container->when( $class )
			->needs( requirement: ArrayAccess::class )
			->give( value: static fn(): Stack => $stack );

		$this->assertTrue( $this->container->get( $class )->has( 'testKey' ) );
	}

	public function testAutoWireDependenciesRecursively(): void {
		$this->container->get( _TestMain__EntryClass::class );

		$toBeResolved = array(
			_TestMain__EntryClass::class,
			_TestPrimary__EntryClass::class,
			_TestSecondary__EntryClass::class,
			stdClass::class,
		);

		foreach ( $toBeResolved as $classname ) {
			$this->assertTrue( $this->container->resolved( id: $classname ) );
		}
	}

	public function testResolvingParamDuringBuildEventIntegration(): void {
		$subscribedClass = new class() extends _TestPrimary__EntryClass {
			public function __construct( public readonly string $value = 'Using Event' ) {}
		};

		$this->container->subscribeDuringBuild(
			id: _TestPrimary__EntryClass::class,
			paramName: 'primary',
			callback: static fn ( string $paramName ): Binding => new Binding( $subscribedClass )
		);

		$this->assertSame(
			expected: 'Using Event',
			actual: $this->container->make( _TestMain__EntryClass::class )->primary->value
		);

		$AutoWiredClass = new class() extends _TestPrimary__EntryClass {
			public function __construct( public readonly string $value = 'Using Injection' ) {}
		};

		$this->assertSame(
			message: 'The injected param value when resolving entry must override event value.',
			expected: 'Using Injection',
			actual: $this->container->make(
				id: _TestMain__EntryClass::class,
				with: array( 'primary' => $AutoWiredClass )
			)->primary->value
		);
	}
}


class _TestStack__Contextual_Binding_WithArrayAccess {
	public function __construct( public readonly ArrayAccess $array ) {}

	public function has( string $key ) {
		return $this->array->offsetExists( $key );
	}
}

class _TestMain__EntryClass {
	public function __construct( public readonly _TestPrimary__EntryClass $primary ) {}
}

class _TestPrimary__EntryClass {
	public function __construct( public readonly _TestSecondary__EntryClass $secondary ) {}
}

class _TestSecondary__EntryClass {
	public function __construct( public readonly stdClass $opt ) {}
}
