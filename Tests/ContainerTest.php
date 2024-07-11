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
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Helper\Unwrap;

class ContainerTest extends TestCase {
	private ?Container $app;

	protected function setUp(): void {
		$this->app = new Container();
	}

	protected function tearDown(): void {
		$this->app = null;
	}

	public function testSingleton(): void {
		$this->assertSame( Container::boot(), Container::boot() );
	}

	public function testArrayAccessible(): void {
		$this->app[ self::class ] = self::class;

		$this->assertTrue( isset( $this->app[ self::class ] ) );
		$this->assertInstanceOf( self::class, $this->app[ self::class ] );

		unset( $this->app[ self::class ] );

		$this->assertFalse( isset( $this->app[ self::class ] ) );
	}

	public function testBasicSetterGetterAndAssertionIntegration(): void {
		$this->assertFalse( $this->app->has( entryOrAlias: 'testClass' ) );
		$this->assertFalse( $this->app->has( entryOrAlias: self::class ) );
		$this->assertFalse( $this->app->hasBinding( 'testClass' ) );
		$this->assertFalse( $this->app->isAlias( 'testClass' ) );
		$this->assertFalse( $this->app->resolved( id: 'testClass' ) );

		$this->app->alias( entry: self::class, alias: 'testClass' );

		$this->assertTrue( $this->app->isAlias( name: 'testClass' ) );
		$this->assertSame( self::class, $this->app->getEntryFrom( alias: 'testClass' ) );
		$this->assertInstanceOf( self::class, $this->app->get( 'testClass' ) );

		$this->app->bind( id: 'testClass', concrete: self::class );

		// Bind will purge alias from the alias pool coz no need for storing same alias
		// multiple places (like in alias pool as well as in binding pool).
		$this->assertFalse( $this->app->isAlias( name: 'testClass' ) );
		$this->assertTrue( $this->app->has( entryOrAlias: 'testClass' ) );
		$this->assertFalse( $this->app->has( entryOrAlias: self::class ), 'Bound with alias.' );
		$this->assertTrue( $this->app->hasBinding( id: 'testClass' ) );
		$this->assertFalse( $this->app->hasBinding( id: self::class ), 'Bound using alias.' );
		$this->assertSame( 'testClass', $this->app->getEntryFrom( alias: 'testClass' ) );

		$this->assertInstanceOf( self::class, $this->app->get( id: 'testClass' ) );
		$this->assertTrue( $this->app->resolved( id: 'testClass' ) );
		$this->assertTrue( $this->app->resolved( id: self::class ) );

		$this->assertFalse( $this->app->isSingleton( id: stdClass::class ) );

		$this->app->singleton( id: stdClass::class, concrete: null );

		$this->assertTrue( $this->app->isSingleton( id: stdClass::class ) );
		$this->assertTrue( $this->app->isShared( id: stdClass::class ) );
		$this->assertFalse( $this->app->isInstance( stdClass::class ) );

		$this->assertSame(
			expected: $this->app->get( id: stdClass::class ),
			actual: $this->app->get( id: stdClass::class )
		);

		// The singleton is resolved and bound as an instance thereafter.
		$this->assertFalse( $this->app->isSingleton( stdClass::class ) );
		$this->assertTrue( $this->app->isInstance( stdClass::class ) );

		$this->assertFalse( $this->app->isInstance( id: 'instance' ) );
		$this->assertFalse( $this->app->resolved( id: 'instance' ) );

		$newClass = $this->app->instance( id: 'instance', instance: new class() {} );

		$this->assertTrue( $this->app->isInstance( id: 'instance' ) );
		$this->assertTrue( $this->app->isShared( id: 'instance' ) );
		$this->assertSame( $newClass, $this->app->get( id: 'instance' ) );
		$this->assertTrue( $this->app->resolved( id: 'instance' ) );
	}

	public function testAliasAndGetWithoutBinding(): void {
		$this->app->alias( entry: self::class, alias: 'testClass' );

		$this->assertInstanceOf( self::class, $this->app->get( id: 'testClass' ) );
		$this->assertInstanceOf( self::class, $this->app->get( id: self::class ) );
	}

	public function testKeepingBothAliasAndBinding(): void {
		$this->app->alias( entry: self::class, alias: 'test' );
		$this->app->bind( id: self::class, concrete: null );

		$this->assertTrue( $this->app->isAlias( name: 'test' ) );
		$this->assertTrue( $this->app->hasBinding( id: self::class ) );
		$this->assertSame( self::class, $this->app->getEntryFrom( alias: 'test' ) );
		$this->assertSame( self::class, $this->app->getEntryFrom( alias: self::class ) );
	}

	public function testContextualBinding(): void {
		$class = _Test_Resolved__container_object__::class;
		$this->assertFalse(
			$this->app->hasContextualBinding( concrete: $class )
		);

		$this->app->addContextual( with: 'update', for: $class, id: '$data' );

		$this->assertTrue( $this->app->hasContextualBinding( concrete: $class ) );
		$this->assertTrue( $this->app->hasContextualBinding( concrete: $class, nestedKey: '$data' ) );

		$this->assertSame( expected: 'update', actual: $this->app->get( id: $class )->data );

		$this->assertSame( 'update', $this->app->getContextual( $class, '$data' ) );

		$this->app->when( concrete: $class )
			->needs( requirement: '$data' )
			->give( value: 'With Builder' );

		$this->assertSame( 'With Builder', $this->app->get( id: $class )->data );

		$this->app->when( concrete: $class )
			->needs( requirement: '$data' )
			->give( value: static fn (): string => 'With Builder from closure' );

		$this->assertSame( 'With Builder from closure', $this->app->get( id: $class )->data );

		$stack = $this->createMock( Stack::class );
		$class = _TestStack__Contextual_Binding_WithArrayAccess::class;

		$stack->expects( $this->once() )
			->method( 'offsetExists' )
			->with( 'testKey' )
			->willReturn( true );

		$this->app->when( $class )
			->needs( requirement: ArrayAccess::class )
			->give( value: static fn(): Stack => $stack );

		$this->assertTrue( $this->app->get( $class )->has( 'testKey' ) );
	}

	public function testContextualBindingWithAliasing(): void {
		$class = _Test_Resolved__container_object__::class;

		$this->app->alias( entry: $class, alias: 'test' );
		$this->app->addContextual( with: 'update', for: 'test', id: '$data' );

		$this->assertSame(
			actual: $this->app->getContextual( for: 'test', id: '$data' ),
			expected: 'update'
		);
	}

	public function testAutoWireDependenciesRecursively(): void {
		$this->app->get( _TestMain__EntryClass::class );

		$toBeResolved = array(
			_TestMain__EntryClass::class,
			_TestPrimary__EntryClass::class,
			_TestSecondary__EntryClass::class,
			stdClass::class,
		);

		foreach ( $toBeResolved as $classname ) {
			$this->assertTrue( $this->app->resolved( id: $classname ) );
		}
	}

	public function testResolvingParamDuringBuildEventIntegration(): void {
		$subscribedClass = new class() extends _TestPrimary__EntryClass {
			public function __construct( public readonly string $value = 'Using Event' ) {}
		};

		$this->app->subscribeDuringBuild(
			id: _TestPrimary__EntryClass::class,
			paramName: 'primary',
			implementation: new Binding( $subscribedClass )
		);

		$this->assertSame(
			expected: 'Using Event',
			actual: $this->app->get( _TestMain__EntryClass::class )->primary->value
		);

		$AutoWiredClass = new class() extends _TestPrimary__EntryClass {
			public function __construct( public readonly string $value = 'Using Injection' ) {}
		};

		$this->assertSame(
			message: 'The injected param value when resolving entry must override event value.',
			expected: 'Using Injection',
			actual: $this->app->get(
				id: _TestMain__EntryClass::class,
				with: array( 'primary' => $AutoWiredClass )
			)->primary->value
		);
	}

	public function testWithEventBuilder(): void {
		$this->assertInstanceOf(
			expected: _TestSecondary__EntryClass::class,
			actual: $this->app->get( _TestPrimary__EntryClass::class )->secondary
		);

		$eventualClass = new class() extends _TestSecondary__EntryClass {
			public function __construct( public readonly string $value = 'Using Event Builder' ) {}
		};

		$this->app
			->matches( paramName: 'secondary' )
			->for( concrete: _TestSecondary__EntryClass::class )
			->give( implementation: new Binding( $eventualClass ) );

		$secondaryClass = $this->app->get( _TestPrimary__EntryClass::class )->secondary;

		$this->assertInstanceOf( $eventualClass::class, $secondaryClass );
		$this->assertSame( expected: 'Using Event Builder', actual: $secondaryClass->value );
	}

	/** @return array{0:object,1:string,2:string} */
	private function getTestClassInstanceStub(): array {
		$test = new class() {
			public function get( int $val ): int {
				return $val + 2;
				;
			}

			public function alt( int $val = 7 ): int {
				return $val + 10;
			}

			public function __invoke( int $val ): int {
				return $val + 5;
			}

			public static function getStatic( int $val ): int {
				return $val + 3;
			}
		};

		$normalId   = $test::class . '::get';
		$instanceId = $test::class . '#' . spl_object_id( $test ) . '::get';

		return array( $test, $normalId, $instanceId );
	}

	public function testMethodCallForInvocableClassInstance(): void {
		$app                      = new Container();
		[ $test, $testGetString ] = $this->getTestClassInstanceStub();
		$testInvokeInstance       = Unwrap::callback( cb: $test );
		$testInvokeString         = Unwrap::asString( object: $test::class, methodName: '__invoke' );

		$app->when( concrete: $testInvokeInstance )
			->needs( '$val' )
			->give( value: static fn(): int => 95 );

		$this->assertSame( expected: 100, actual: $app->call( $test ) );

		$app->when( concrete: $testInvokeString )
			->needs( '$val' )
			->give( value: static fn(): int => 195 );

		$this->assertSame( expected: 200, actual: $app->call( $testInvokeString ) );

		$app->when( concrete: $testGetString )
			->needs( '$val' )
			->give( value: static fn(): int => 298 );

		$this->assertSame( expected: 300, actual: $app->call( $testGetString ) );

		$app->matches( paramName: 'val' )
			->for( concrete: 'int' )
			->give( implementation: new Binding( concrete: 85 ) );

		$this->assertSame( expected: 90, actual: $app->call( $test ) );

		$app->matches( paramName: 'val' )
			->for( concrete: 'int' )
			->give( implementation: new Binding( concrete: 185 ) );

		$this->assertSame( expected: 190, actual: $app->call( $test::class ) );

		$app->matches( paramName: 'val' )
			->for( concrete: 'int' )
			->give( implementation: new Binding( concrete: 188 ) );

		$this->assertSame( expected: 190, actual: $app->call( $testGetString ) );

		$this->assertSame( expected: 30, actual: $app->call( $test, params: array( 'val' => 25 ) ) );

		$this->assertSame(
			expected: 130,
			actual: $app->call( $test::class, params: array( 'val' => 125 ) )
		);

		$this->assertSame(
			expected: 140,
			actual: $app->call( $testGetString, params: array( 'val' => 138 ) )
		);

		$app->bindMethod( entry: $testInvokeInstance, callback: static fn( $test ) => $test( 15 ) );

		$this->assertSame( expected: 20, actual: $app->call( $test ) );

		$app->bindMethod( entry: $testInvokeString, callback: static fn( $test ) => $test( 115 ) );

		$this->assertSame( expected: 120, actual: $app->call( $test::class ) );

		// Actually not with "get" method. Instead bound with "alt" method.
		$app->bindMethod( entry: $testGetString, callback: static fn( $test ) => $test->alt( 140 ) );

		$this->assertSame( expected: 150, actual: $app->call( $testGetString ) );
	}

	public function testMethodCallWithDifferentImplementation(): void {
		$app  = new Container();
		$test = new class() {
			public function test( int $arg = 3 ): int {
				return $arg + 2;
			}
		};

		// Unwrapped string of "Unwrap::asString($test, 'test')".
		$app->when( $test::class . '#' . spl_object_id( $test ) . '::test' )
			->needs( '$arg' )
			->give( fn() => 18 );

		$this->assertSame( expected: 20, actual: $app->call( array( $test, 'test' ) ) );
		$this->assertSame( expected: 20, actual: $app->call( $test->test( ... ) ) );

		// Setting instance as "true" so multiple calls resolve the event binding value.
		$app->subscribeDuringBuild( 'int', 'arg', new Binding( 28, instance: true ) );

		$this->assertSame( expected: 30, actual: $app->call( array( $test, 'test' ) ) );
		$this->assertSame( expected: 30, actual: $app->call( $test->test( ... ) ) );

		$this->assertSame(
			actual: $app->call( array( $test, 'test' ), array( 'arg' => 38 ) ),
			expected: 40
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
