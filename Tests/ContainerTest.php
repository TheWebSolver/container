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
use Psr\Container\NotFoundExceptionInterface;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;
use TheWebSolver\Codegarage\Lib\Container\Data\Aliases;
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Helper\Unwrap;
use TheWebSolver\Codegarage\Lib\Container\Error\ContainerError;

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
		$this->assertFalse( $this->app->has( id: 'testClass' ) );
		$this->assertFalse( $this->app->has( id: self::class ) );
		$this->assertFalse( $this->app->hasBinding( 'testClass' ) );
		$this->assertFalse( $this->app->isAlias( 'testClass' ) );
		$this->assertFalse( $this->app->resolved( id: 'testClass' ) );

		$this->app->alias( entry: self::class, alias: 'testClass' );

		$this->assertTrue( $this->app->isAlias( id: 'testClass' ) );
		$this->assertSame( self::class, $this->app->getEntryFrom( alias: 'testClass' ) );
		$this->assertInstanceOf( self::class, $this->app->get( 'testClass' ) );

		$this->app->bind( id: 'testClass', concrete: self::class );

		// Bind will purge alias from the alias pool coz no need for storing same alias
		// multiple places (like in alias pool as well as in binding pool).
		$this->assertFalse( $this->app->isAlias( id: 'testClass' ) );
		$this->assertTrue( $this->app->has( id: 'testClass' ) );
		$this->assertFalse( $this->app->has( id: self::class ), 'Bound with alias.' );
		$this->assertTrue( $this->app->hasBinding( id: 'testClass' ) );
		$this->assertFalse( $this->app->hasBinding( id: self::class ), 'Bound using alias.' );
		$this->assertSame( 'testClass', $this->app->getEntryFrom( alias: 'testClass' ) );
		$this->assertInstanceOf( Closure::class, actual: $this->app->getBinding( 'testClass' )->concrete );

		$this->assertInstanceOf( self::class, $this->app->get( id: 'testClass' ) );
		$this->assertTrue( $this->app->resolved( id: 'testClass' ) );
		$this->assertTrue( $this->app->resolved( id: self::class ) );

		$this->app->singleton( id: stdClass::class, concrete: null );

		$this->assertFalse( $this->app->isInstance( stdClass::class ) );

		$this->assertSame(
			expected: $this->app->get( id: stdClass::class ),
			actual: $this->app->get( id: stdClass::class )
		);

		// The singleton is resolved and bound as an instance thereafter.
		$this->assertTrue( $this->app->isInstance( stdClass::class ) );

		$this->assertFalse( $this->app->isInstance( id: 'instance' ) );
		$this->assertFalse( $this->app->resolved( id: 'instance' ) );

		$newClass = $this->app->instance( id: 'instance', instance: new class() {} );

		$this->assertTrue( $this->app->isInstance( id: 'instance' ) );
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

		$this->assertTrue( $this->app->isAlias( id: 'test' ) );
		$this->assertTrue( $this->app->hasBinding( id: self::class ) );
		$this->assertSame( self::class, $this->app->getEntryFrom( alias: 'test' ) );
		$this->assertSame( self::class, $this->app->getEntryFrom( alias: self::class ) );
	}

	public function testPurgeAliasWhenInstanceIsBoundAndPerformRebound(): void {
		$this->app->alias( entry: _Rebound__SetterGetter__Stub::class, alias: 'test' );

		$this->assertTrue( $this->app->isAlias( id: 'test' ) );

		$this->app->bind( id: Binding::class, concrete: fn() => new Binding( concrete: 'original' ) );

		$this->app->instance(
			id: 'test',
			instance: ( new _Rebound__SetterGetter__Stub() )->set(
				binding: $this->app->useRebound(
					of: Binding::class,
					// Get the "test" instantiated class & update it with rebounded Binding instance.
					with: fn( Binding $obj, Container $app ) => $app->get( id: 'test' )->set( binding: $obj )
				)
			)
		);

		$this->assertFalse( condition: $this->app->isAlias( id: 'test' ) );

		$this->assertSame( expected: 'original', actual: $this->app->get( id: 'test' )->get()->concrete );

		$this->app->bind( id: Binding::class, concrete: fn() => new Binding( concrete: 'updated' ) );

		$this->assertSame( expected: 'updated', actual: $this->app->get( 'test' )->get()->concrete );
	}

	public function testContextualBinding(): void {
		$this->app->when( concrete: Binding::class )
			->needs( requirement: '$concrete' )
			->give( value: 'With Builder' );

		$this->assertSame( 'With Builder', $this->app->get( id: Binding::class )->concrete );

		$this->app->when( concrete: Binding::class )
			->needs( requirement: '$concrete' )
			->give( value: static fn (): string => 'With Builder from closure' );

		$this->assertSame( 'With Builder from closure', $this->app->get( id: Binding::class )->concrete );

		$class          = _Stack__ContextualBindingWithArrayAccess__Stub::class;
		$implementation = static function ( Container $app ) {
			$stack = $app->get( Stack::class );

			$stack['key'] = 'value';

			return $stack;
		};

		$this->app->when( concrete: $class )
			->needs( requirement: ArrayAccess::class )
			->give( value: $implementation );

		$this->assertSame( expected: 'value', actual: $this->app->get( $class )->getStack()->get( 'key' ) );

		$this->app->when( concrete: $class )
			->needs( requirement: ArrayAccess::class )
			->give( value: Stack::class );

		$this->assertInstanceOf( expected: Stack::class, actual: $this->app->get( $class )->getStack() );
	}

	public function testContextualBindingWithAliasing(): void {
		$this->app->alias( entry: Binding::class, alias: 'test' );
		$this->app->when( concrete: 'test' )->needs( requirement: '$concrete' )->give( value: 'update' );

		$this->assertSame(
			actual: $this->app->getContextual( for: 'test', context: '$concrete' ),
			expected: 'update'
		);
	}

	public function testAutoWireDependenciesRecursively(): void {
		$this->app->get( _Main__EntryClass__Stub::class );

		$toBeResolved = array(
			_Main__EntryClass__Stub::class,
			_Primary__EntryClass__Stub::class,
			_Secondary__EntryClass__Stub::class,
			stdClass::class,
		);

		foreach ( $toBeResolved as $classname ) {
			$this->assertTrue( $this->app->resolved( id: $classname ) );
		}
	}

	public function testResolvingParamDuringBuildEventIntegration(): void {
		$subscribedClass = new class() extends _Primary__EntryClass__Stub {
			public function __construct( public readonly string $value = 'Using Event' ) {}
		};

		$this->app
			->matches( paramName: 'primary' )
			->for( concrete: _Primary__EntryClass__Stub::class )
			->give( implementation: new Binding( $subscribedClass ) );

		$this->assertSame(
			expected: 'Using Event',
			actual: $this->app->get( _Main__EntryClass__Stub::class )->primary->value
		);

		$AutoWiredClass = new class() extends _Primary__EntryClass__Stub {
			public function __construct( public readonly string $value = 'Using Injection' ) {}
		};

		$this->assertSame(
			message: 'The injected param value when resolving entry must override event value.',
			expected: 'Using Injection',
			actual: $this->app->get(
				id: _Main__EntryClass__Stub::class,
				with: array( 'primary' => $AutoWiredClass )
			)->primary->value
		);
	}

	public function testUnresolvableClass(): void {
		$this->expectException( ContainerError::class );
		$this->expectExceptionMessage( 'Unable to find the target class: "\\Invalid\\ClassName".' );

		$this->app->get( id: '\\Invalid\\ClassName' );
	}

	public function testUnInstantiableClass(): void {
		$previous = array(
			_PassesFirstBuild__EntryClass__Stub::class,
			_UnResolvable__EntryClass__Stub::class,
		);

		$this->expectException( ContainerError::class );
		$this->expectExceptionMessage(
			'Unable to instantiate the target class: "' . _StaticOnly__Class__Stub::class . '"'
			. ' while building [' . implode( separator: ', ', array: $previous ) . '].'
		);

		$this->app->get( id: _PassesFirstBuild__EntryClass__Stub::class );
	}

	public function testWithEventBuilder(): void {
		$this->assertInstanceOf(
			expected: _Secondary__EntryClass__Stub::class,
			actual: $this->app->get( _Primary__EntryClass__Stub::class )->secondary
		);

		$eventualClass = new class() extends _Secondary__EntryClass__Stub {
			public function __construct( public readonly string $value = 'Using Event Builder' ) {}
		};

		$this->app
			->matches( paramName: 'secondary' )
			->for( concrete: _Secondary__EntryClass__Stub::class )
			->give( implementation: new Binding( $eventualClass ) );

		$secondaryClass = $this->app->get( _Primary__EntryClass__Stub::class )->secondary;

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

		$this->app->when( concrete: $testInvokeInstance )
			->needs( '$val' )
			->give( value: static fn(): int => 95 );

		$this->assertSame( expected: 100, actual: $this->app->call( $test ) );
		$this->assertSame( expected: 100, actual: $this->app->call( array( $test, '__invoke' ) ) );

		$this->app->when( concrete: $testInvokeString )
			->needs( '$val' )
			->give( value: static fn(): int => 195 );

		$this->assertSame( expected: 200, actual: $this->app->call( $testInvokeString ) );
		$this->assertSame( expected: 200, actual: $this->app->call( $test::class ) );

		$this->app->when( concrete: $testGetString )
			->needs( '$val' )
			->give( value: static fn(): int => 298 );

		$this->assertSame( expected: 300, actual: $this->app->call( $testGetString ) );

		$this->app->when( concrete: $test->alt( ... ) )
			->needs( '$val' )
			->give( value: static fn(): int => 380 );

		$this->assertSame( expected: 390, actual: $this->app->call( $test->alt( ... ) ) );
		$this->assertSame( expected: 390, actual: $this->app->call( array( $test, 'alt' ) ) );

		$this->app->when( concrete: $test::class . '::alt' )
			->needs( '$val' )
			->give( value: static fn (): int => 2990 );

		$this->assertSame( expected: 3000, actual: $this->app->call( $test::class . '::alt' ) );

		$this->app->matches( paramName: 'val' )
			->for( concrete: 'int' )
			->give( implementation: new Binding( concrete: 85 ) );

		$this->assertSame( expected: 90, actual: $this->app->call( $test ) );

		$this->app->matches( paramName: 'val' )
			->for( concrete: 'int' )
			->give( implementation: new Binding( concrete: 85 ) );

		$this->assertSame( expected: 90, actual: $this->app->call( array( $test, '__invoke' ) ) );

		$this->app->matches( paramName: 'val' )
			->for( concrete: 'int' )
			->give( implementation: new Binding( concrete: 185 ) );

		$this->assertSame( expected: 190, actual: $this->app->call( $test::class ) );

		$this->app->matches( paramName: 'val' )
			->for( concrete: 'int' )
			->give( implementation: new Binding( concrete: 188 ) );

		$this->assertSame( expected: 190, actual: $this->app->call( $testGetString ) );

		$this->app->matches( paramName: 'val' )
			->for( concrete: 'int' )
			->give( implementation: new Binding( concrete: 0 ) );

		$this->assertSame( expected: 10, actual: $this->app->call( $test->alt( ... ) ) );

		$this->app->matches( paramName: 'val' )
			->for( concrete: 'int' )
			->give( implementation: new Binding( concrete: 0 ) );

		$this->assertSame( expected: 10, actual: $this->app->call( array( $test, 'alt' ) ) );

		$this->assertSame( expected: 30, actual: $this->app->call( $test, params: array( 'val' => 25 ) ) );
		$this->assertSame(
			expected: 30,
			actual: $this->app->call( $test, params: array( 'val' => 25 ), defaultMethod: 'no effect' )
		);

		$this->assertSame(
			expected: 130,
			actual: $this->app->call( $test::class, params: array( 'val' => 125 ), defaultMethod: '__invoke' )
		);
		$this->assertSame(
			expected: 127,
			actual: $this->app->call( $test::class, params: array( 'val' => 125 ), defaultMethod: 'get' )
		);
		$this->assertSame(
			expected: 140,
			actual: $this->app->call( $testGetString, params: array( 'val' => 138 ) )
		);

		$this->assertSame(
			expected: 40,
			actual: $this->app->call( $test->alt( ... ), params: array( 'val' => 30 ), defaultMethod: 'no effect' )
		);

		$this->app->bindMethod( entry: $testInvokeInstance, callback: static fn( $test ) => $test( 15 ) );

		$this->assertSame( expected: 20, actual: $this->app->call( $test ) );

		$this->app->bindMethod( entry: $testInvokeString, callback: static fn( $test ) => $test( 115 ) );

		$this->assertSame( expected: 120, actual: $this->app->call( $test::class ) );

		$this->app->bindMethod( entry: $testGetString, callback: static fn( $test ) => $test->alt( 140 ) );

		$this->assertSame( expected: 150, actual: $this->app->call( $testGetString ) );
		$this->assertSame(
			expected: 150,
			actual: $this->app->call( $test::class, params: array( 'val' => 490 ), defaultMethod: 'get' ),
			message: 'Coz "$test::get" is already bound ($testGetString), we get binding result instead.'
		);

		$this->app->bindMethod( entry: $test->alt( ... ), callback: static fn() => 23 );

		$this->assertSame( expected: 23, actual: $this->app->call( $test->alt( ... ) ) );
		$this->assertSame( expected: 23, actual: $this->app->call( array( $test, 'alt' ) ) );

		$this->assertSame(
			expected: 500,
			actual: $this->app->call( $test::class, params: array( 'val' => 490 ), defaultMethod: 'alt' )
		);
	}

	public function testReboundValueOfDependencyBindingUpdatedAtLaterTime(): void {
		$this->app->bind(
			id: Stack::class,
			concrete: function () {
				$stack = new Stack();

				$stack->asCollection();

				$stack->set( key: 'john', value: 'doe' );

				return $stack;
			}
		);

		$this->app->bind(
			id: 'aliases',
			concrete: fn ( Container $app ) => new Aliases(
				entryStack: $app->useRebound( of: Stack::class, with: static fn ( Stack $obj ) => $obj )
			)
		);

		/** @var Aliases */
		$aliases = $this->app->get( id: 'aliases' );

		$this->assertSame( expected: 'doe', actual: $aliases->get( id: 'john', asEntry: true )[0] );

		$this->app->bind(
			id: Stack::class,
			concrete: function () {
				$stack = new Stack();

				$stack->set( key: 'PHP', value: 'Developer' );

				return $stack;
			}
		);

		/** @var Aliases */
		$aliasWithReboundEntries = $this->app->get( id: 'aliases' );

		$this->assertSame(
			expected: 'Developer',
			actual: $aliasWithReboundEntries->get( id: 'PHP', asEntry: true )
		);

		$this->app->bind( id: Binding::class, concrete: fn() => new Binding( concrete: 'original' ) );

		$this->app->singleton(
			id: 'test',
			concrete: fn ( Container $app ) => ( new _Rebound__SetterGetter__Stub() )->set(
				binding: $app->useRebound(
					of: Binding::class,
					with: fn( Binding $obj, Container $app ) => $app->get( id: 'test' )->set( binding: $obj )
				)
			)
		);

		$this->assertSame( expected: 'original', actual: $this->app->get( id: 'test' )->get()->concrete );

		$this->app->bind( id: Binding::class, concrete: fn() => new Binding( concrete: 'mutated' ) );

		$this->assertSame( expected: 'mutated', actual: $this->app->get( 'test' )->get()->concrete );

		$this->expectException( exception: NotFoundExceptionInterface::class );
		$this->expectExceptionMessage(
			message: 'Unable to find entry for the given id: "notBoundYet" when possible rebinding was expected.'
		);

		$this->app->bind(
			id: 'noDependencyBound',
			concrete: fn( Container $app ) => ( new _Rebound__SetterGetter__Stub() )
				->set( binding: $app->useRebound( of: 'notBoundYet', with: function () {} ) )
		);

		$this->app->get( id: 'noDependencyBound' );
	}
}

class _Stack__ContextualBindingWithArrayAccess__Stub {
	public function __construct( public readonly ArrayAccess $array ) {}

	public function has( string $key ) {
		return $this->array->offsetExists( $key );
	}

	public function getStack(): ArrayAccess {
		return $this->array;
	}
}

class _Main__EntryClass__Stub {
	public function __construct( public readonly _Primary__EntryClass__Stub $primary ) {}
}

class _Primary__EntryClass__Stub {
	public function __construct( public readonly _Secondary__EntryClass__Stub $secondary ) {}
}

class _Secondary__EntryClass__Stub {
	public function __construct( public readonly stdClass $opt ) {}
}

class _UnResolvable__EntryClass__Stub {
	public function __construct( private readonly _StaticOnly__Class__Stub $primary ) {}
}

class _PassesFirstBuild__EntryClass__Stub {
	public function __construct( private readonly _UnResolvable__EntryClass__Stub $triggersError ) {}
}

class _StaticOnly__Class__Stub {
	private function __construct() {}
}

class _Rebound__SetterGetter__Stub {
	private Binding $binding;

	public function set( Binding $binding ): self {
		$this->binding = $binding;

		return $this;
	}

	public function get(): Binding {
		return $this->binding;
	}
}
