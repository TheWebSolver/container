<?php
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
// phpcs:disable PEAR.NamingConventions.ValidClassName.StartWithCapital
// phpcs:disable PEAR.NamingConventions.ValidClassName.Invalid

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Tests;

use WeakMap;
use stdClass;
use Countable;
use ArrayAccess;
use ArrayObject;
use SplFixedArray;
use LogicException;
use ReflectionClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\NotFoundExceptionInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Container\Container;
use TheWebSolver\Codegarage\Container\Pool\Stack;
use TheWebSolver\Codegarage\Container\Data\Binding;
use TheWebSolver\Codegarage\Container\Helper\Unwrap;
use TheWebSolver\Codegarage\Container\Event\EventType;
use TheWebSolver\Codegarage\Container\Attribute\ListenTo;
use TheWebSolver\Codegarage\Container\Data\SharedBinding;
use TheWebSolver\Codegarage\Container\Error\LogicalError;
use TheWebSolver\Codegarage\Container\Event\BuildingEvent;
use TheWebSolver\Codegarage\Container\Error\ContainerError;
use TheWebSolver\Codegarage\Container\Pool\CollectionStack;
use TheWebSolver\Codegarage\Container\Event\AfterBuildEvent;
use TheWebSolver\Codegarage\Container\Event\EventDispatcher;
use TheWebSolver\Codegarage\Container\Interfaces\Compilable;
use TheWebSolver\Codegarage\Container\Attribute\DecorateWith;
use TheWebSolver\Codegarage\Container\Event\BeforeBuildEvent;
use TheWebSolver\Codegarage\Container\Event\Manager\EventManager;
use TheWebSolver\Codegarage\Container\Event\Manager\AfterBuildHandler;

class ContainerTest extends TestCase {
	private Container $app;

	protected function setUp(): void {
		$this->app = new Container();
	}

	protected function tearDown(): void {
		$this->setUp();
	}

	public function testSingleton(): void {
		$this->assertSame( Container::boot(), Container::boot() );
	}

	public function testInstanceAssignment(): void {
		$reflection = new ReflectionClass( Container::class );
		$original   = Container::boot();
		$new        = new Container();

		$this->assertFalse( Container::use( $new ) === $new );

		// Already booted container cannot set instance again. Removing singleton.
		$reflection->setStaticPropertyValue( 'instance', null );

		$this->assertTrue( Container::use( $new ) === $new );
		$this->assertTrue( Container::boot() === $new );

		// Reset container singleton back to its original state.
		$reflection->setStaticPropertyValue( 'instance', $original );

		$this->assertFalse( Container::boot() === $new );
		$this->assertTrue( Container::boot() === $original );
	}

	public function testArrayAccessible(): void {
		$this->app[ WeakMap::class ] = WeakMap::class;

		$this->assertTrue( isset( $this->app[ WeakMap::class ] ) );
		$this->assertInstanceOf( WeakMap::class, $this->app[ WeakMap::class ] );

		unset( $this->app[ WeakMap::class ] );

		$this->assertFalse( isset( $this->app[ WeakMap::class ] ) );
	}

	public function testBasicSetterGetterAndAssertionIntegration(): void {
		$this->assertFalse( $this->app->has( id: 'testClass' ) );
		$this->assertFalse( $this->app->has( id: WeakMap::class ) );
		$this->assertFalse( $this->app->hasBinding( 'testClass' ) );
		$this->assertFalse( $this->app->isAlias( 'testClass' ) );
		$this->assertFalse( $this->app->hasResolved( id: 'testClass' ) );

		$this->app->setAlias( entry: WeakMap::class, alias: 'testClass' );

		$this->assertTrue( $this->app->isAlias( id: 'testClass' ) );
		$this->assertSame( WeakMap::class, $this->app->getEntryFrom( 'testClass' ) );
		$this->assertInstanceOf( WeakMap::class, $this->app->get( 'testClass' ) );

		$this->app->set( id: 'testClass', concrete: WeakMap::class );

		// Bind will purge alias from the alias pool coz no need for storing same alias
		// multiple places (like in alias pool as well as in binding pool).
		$this->assertFalse( $this->app->isAlias( id: 'testClass' ) );
		$this->assertTrue( $this->app->has( id: 'testClass' ) );
		$this->assertFalse( $this->app->has( id: WeakMap::class ), 'Bound with alias.' );
		$this->assertTrue( $this->app->hasBinding( id: 'testClass' ) );
		$this->assertFalse( $this->app->hasBinding( id: WeakMap::class ), 'Bound using alias.' );
		$this->assertSame( 'testClass', $this->app->getEntryFrom( 'testClass' ) );
		$this->assertSame( WeakMap::class, $this->app->getBinding( 'testClass' )->material );

		$this->assertInstanceOf( WeakMap::class, $this->app->get( id: 'testClass' ) );
		$this->assertTrue( $this->app->hasResolved( id: 'testClass' ) );
		$this->assertTrue( $this->app->hasResolved( id: WeakMap::class ) );
		$this->assertSame( WeakMap::class, $this->app->getResolved( WeakMap::class ) );
		$this->assertTrue( $this->app->removeResolved( WeakMap::class ) );

		$this->app->setShared( id: stdClass::class );

		$this->assertFalse( $this->app->isInstance( stdClass::class ) );

		$this->assertSame(
			expected: $this->app->get( id: stdClass::class ),
			actual: $this->app->get( id: stdClass::class )
		);
		$this->assertSame(
			array( stdClass::class => stdClass::class ),
			$this->app->getResolved( stdClass::class )
		);

		// The singleton is resolved and bound as an instance thereafter.
		$this->assertTrue( $this->app->isInstance( stdClass::class ) );
		$this->assertTrue( $this->app->hasResolved( stdClass::class ) );

		$this->assertFalse( $this->app->removeInstance( 'aliasedClass' ) );

		$this->app->setShared( 'aliasedClass', WeakMap::class );

		$this->assertFalse( $this->app->removeResolved( 'aliasedClass' ) );

		$this->app->get( 'aliasedClass' );
		$this->assertTrue( $this->app->isInstance( 'aliasedClass' ) );
		$this->assertTrue( $this->app->hasResolved( 'aliasedClass' ) );
		$this->assertSame(
			array( WeakMap::class => WeakMap::class ),
			$this->app->getResolved( 'aliasedClass' )
		);
		$this->assertNull(
			actual: $this->app->getResolved( WeakMap::class ),
			message: 'Cannot get resolved Stack value using concrete when alias was used when shared.'
		);

		$this->assertTrue( $this->app->removeResolved( 'aliasedClass' ) );
		$this->assertTrue( $this->app->removeInstance( 'aliasedClass' ) );
		$this->assertFalse( $this->app->isInstance( 'aliasedClass' ) );

		$this->assertFalse( $this->app->isInstance( id: 'instance' ) );
		$this->assertFalse( $this->app->hasResolved( id: 'instance' ) );

		$newClass = $this->app->setInstance( id: 'instance', instance: new class() {} );

		$this->assertTrue( $this->app->isInstance( id: 'instance' ) );
		$this->assertSame( $newClass, $this->app->get( id: 'instance' ) );
		$this->assertTrue( $this->app->hasResolved( id: 'instance' ) );
		$this->assertSame(
			array( $newClass::class => $newClass::class ),
			$this->app->getResolved( 'instance' )
		);

		$this->app->setInstance( WeakMap::class, new WeakMap() );
		$this->app->set( WeakMap::class, WeakMap::class );

		$this->assertFalse(
			condition: $this->app->isInstance( WeakMap::class ),
			message: 'Instance must be purged if another binding created.'
		);

		$this->tearDown();

		$this->app->setAlias( WeakMap::class, 'mapOfEnums' );
		$this->app->set( ArrayAccess::class, 'mapOfEnums' );

		$this->assertInstanceOf( WeakMap::class, $this->app->get( 'mapOfEnums' ) );

		$this->assertTrue( $this->app->has( ArrayAccess::class ) );
		$this->assertFalse( $this->app->has( WeakMap::class ) );
		$this->assertTrue( $this->app->has( 'mapOfEnums' ) );
		$this->assertTrue( $this->app->isAlias( 'mapOfEnums' ) );
		$this->assertTrue( $this->app->hasBinding( ArrayAccess::class ) );
		$this->assertFalse( $this->app->hasResolved( ArrayAccess::class ) );
		$this->assertInstanceOf( WeakMap::class, $this->app->get( ArrayAccess::class ) );
		$this->assertTrue( $this->app->hasResolved( ArrayAccess::class ) );

		$this->tearDown();

		$this->app->setAlias( ArrayAccess::class, 'arrayAccessible' );
		$this->app->set( 'arrayAccessible', WeakMap::class );

		$this->assertFalse( $this->app->isAlias( 'arrayAccessible' ) );
		$this->assertTrue( $this->app->hasBinding( 'arrayAccessible' ) );
		$this->assertInstanceOf( WeakMap::class, $this->app['arrayAccessible'] );
	}

	public function testAliasAndGetWithoutBinding(): void {
		$this->app->setAlias( entry: WeakMap::class, alias: 'testClass' );

		$this->assertInstanceOf( WeakMap::class, $this->app->get( id: 'testClass' ) );
		$this->assertInstanceOf( WeakMap::class, $this->app->get( id: WeakMap::class ) );
	}

	public function testKeepingBothAliasAndBinding(): void {
		$this->app->setAlias( entry: self::class, alias: 'test' );
		$this->app->set( id: self::class );

		$this->assertTrue( $this->app->isAlias( id: 'test' ) );
		$this->assertTrue( $this->app->hasBinding( id: self::class ) );
		$this->assertSame( self::class, $this->app->getEntryFrom( 'test' ) );
		$this->assertSame( self::class, $this->app->getEntryFrom( self::class ) );
	}

	public function testPurgeAliasWhenInstanceIsBoundAndPerformRebound(): void {
		$this->app->setAlias( entry: _Rebound__SetterGetter__Stub::class, alias: 'test' );

		$this->assertTrue( $this->app->isAlias( id: 'test' ) );

		$this->app->set( id: Binding::class, concrete: fn() => new Binding( 'original' ) );

		$this->app->setInstance(
			id: 'test',
			instance: ( new _Rebound__SetterGetter__Stub() )->set(
				binding: $this->app->useRebound(
					Binding::class,
					// Get the "test" instantiated class & update it with rebounded Binding instance.
					fn( Binding $obj, Container $app ) => $app->get( id: 'test' )->set( binding: $obj )
				)
			)
		);

		$this->assertFalse( condition: $this->app->isAlias( id: 'test' ) );

		$this->assertSame( expected: 'original', actual: $this->app->get( id: 'test' )->get()->material );

		$this->app->set( id: Binding::class, concrete: fn() => new Binding( 'updated' ) );

		$this->assertSame( expected: 'updated', actual: $this->app->get( 'test' )->get()->material );
	}

	public function testContextualBinding(): void {
		$this->app->when( Binding::class )
			->needs( '$material' )
			->give( static fn (): string => 'With Builder from closure' );

		$this->assertSame( 'With Builder from closure', $this->app->get( id: Binding::class )->material );

		$class          = _Stack__ContextualBindingWithArrayAccess__Stub::class;
		$implementation = static function ( Container $app ) {
			$stack = $app->get( Stack::class );

			$stack['key'] = 'value';

			return $stack;
		};

		$this->app->when( $class )
			->needs( ArrayAccess::class )
			->give( $implementation );

		$this->assertSame( expected: 'value', actual: $this->app->get( $class )->getStack()->offsetGet( 'key' ) );

		$this->app->when( $class )
			->needs( ArrayAccess::class )
			->give( Stack::class );

		$this->assertInstanceOf( expected: Stack::class, actual: $this->app->get( $class )->getStack() );

		$class1 = new class() {
			public function __construct( public ArrayAccess $stack = new WeakMap() ) {}
		};

		$class2 = new class() {
			public function __construct( public ArrayAccess $stack = new WeakMap() ) {}
		};

		$this->app->when( $classes = array( $class1::class, $class2::class ) )
			->needs( ArrayAccess::class )
			->give( Stack::class );

		foreach ( $classes as $classname ) {
			$this->assertInstanceOf( Stack::class, $this->app->get( $classname )->stack );
		}
	}

	public function testContextualBindingNeedsConcreteInsteadOfAliasToGetContextualData(): void {
		$this->app->setAlias( entry: Binding::class, alias: 'test' );
		$this->app->when( 'test' )->needs( '$material' )->give( static fn() => 'update' );

		$this->assertSame( 'update', $this->app->getContextual( Binding::class, '$material' )() );
		$this->assertSame( 'update', $this->app->get( 'test' )->material );

		$this->app->setAlias( entry: _Stack__ContextualBindingWithArrayAccess__Stub::class, alias: 'stack' );
		$this->app->set( JustTest__Stub::class, 'stack' );

		$stub = $this->createStub( ArrayAccess::class );

		$this->app->when( 'stack' )->needs( ArrayAccess::class )->give( static fn() => $stub );

		$this->assertSame(
			$stub,
			$this->app->getContextual( _Stack__ContextualBindingWithArrayAccess__Stub::class, ArrayAccess::class )()
		);
		$this->assertSame( $stub, $this->app->get( JustTest__Stub::class )->array );
	}

	public function testAutoWireDependenciesRecursively(): void {
		$this->app->get( _Main__EntryClass__Stub_Child::class );

		$toBeResolved = array(
			_Main__EntryClass__Stub_Child::class,
			_Primary__EntryClass__Stub::class,
			_Secondary__EntryClass__Stub::class,
			stdClass::class,
		);

		foreach ( $toBeResolved as $classname ) {
			$this->assertTrue( $this->app->hasResolved( id: $classname ) );
		}
	}

	public function testResolvingParamDuringBuildEventIntegration(): void {
		$this->app->when( EventType::Building )
			->for( ArrayAccess::class, paramName: 'array' )
			->listenTo( fn ( BuildingEvent $event ) => $event->setBinding( new Binding( WeakMap::class ) ) );

		$this->assertInstanceOf(
			expected: WeakMap::class,
			actual: $this->app->get( _Stack__ContextualBindingWithArrayAccess__Stub::class )->array
		);

		$this->assertInstanceOf(
			message: 'The injected param value when resolving entry must override event value.',
			expected: Stub::class,
			actual: $this->app->get(
				id: _Stack__ContextualBindingWithArrayAccess__Stub::class,
				args: array( 'array' => $this->createStub( ArrayAccess::class ) )
			)->array
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

		$stdClass      = new stdClass();
		$eventualClass = new class( $stdClass ) extends _Secondary__EntryClass__Stub {
			public function __construct( public readonly stdClass $opt ) {}
		};

		$this->app->when( EventType::Building )
			->for( _Secondary__EntryClass__Stub::class, paramName: 'secondary' )
			->listenTo(
				function ( BuildingEvent $event ) use ( $eventualClass ) {
					$event->setBinding( new SharedBinding( $eventualClass ) );
				}
			);

		$secondaryClass = $this->app->get( _Primary__EntryClass__Stub::class )->secondary;

		$this->assertInstanceOf( $eventualClass::class, $secondaryClass );
		$this->assertSame( expected: $stdClass, actual: $secondaryClass->opt );
	}

	public function testMethodCallWithSplObjectStorage(): void {
		$binding  = new Binding( '' );
		$instance = $this->app->call(
			entry: Container::class,
			methodName: 'setInstance',
			args: array(
				'id'       => 'test',
				'instance' => $binding,
			)
		);
		$this->assertSame( $instance, $binding );
	}

	/** @return array{0:class-string,1:string,2:string} */
	private function getTestClassInstanceStub(): array {
		$test = new class() {
			public function get( int $val ): int {
				return $val + 2;
			}

			public function addsTen( int $val = 7 ): int {
				return $val + 10;
			}

			public function __invoke( int $val ): int {
				return $val + 5;
			}

			public static function addsThree( int $val ): int {
				return $val + 3;
			}

			private int $value;

			public function setValue( int $val ): self {
				$this->value = $val;

				return $this;
			}

			public function getValue(): int {
				return $this->value;
			}

			public function getArrayAccess( ArrayAccess $stack ): ArrayAccess {
				return $stack;
			}
		};

		$normalId   = $test::class . '::get';
		$instanceId = $test::class . '#' . spl_object_id( $test ) . '::get';

		$this->app->setInstance( $test::class, $test );

		return array( $test::class, $normalId, $instanceId );
	}

	public function testMethodCallForInvocableClassInstance(): void {
		[ $test, $testGetString ] = $this->getTestClassInstanceStub();
		$testInvokeInstance       = $test . '#' . spl_object_id( $this->app->get( $test ) ) . '::__invoke';
		$testInvokeString         = $test . '::__invoke';

		$this->app->when( $testInvokeInstance )
			->needs( '$val' )
			->give( static fn(): int => 95 );

		$this->assertSame( expected: 100, actual: $this->app->call( $this->app->get( $test ) ) );
		$this->assertSame( expected: 100, actual: $this->app->call( array( $this->app->get( $test ), '__invoke' ) ) );

		$this->app->when( $testInvokeString )
			->needs( '$val' )
			->give( static fn(): int => 195 );

		$this->assertSame( expected: 200, actual: $this->app->call( $testInvokeString ) );
		$this->assertSame( expected: 200, actual: $this->app->call( $test ) );

		$this->app->when( $testGetString )
			->needs( '$val' )
			->give( static fn(): int => 298 );

		$this->assertSame( expected: 300, actual: $this->app->call( $testGetString ) );

		$this->app->when( $this->app->get( $test )->addsTen( ... ) )
			->needs( '$val' )
			->give( static fn(): int => 380 );

		$this->assertSame( expected: 390, actual: $this->app->call( $this->app->get( $test )->addsTen( ... ) ) );
		$this->assertSame( expected: 390, actual: $this->app->call( array( $this->app->get( $test ), 'addsTen' ) ) );

		$this->app->when( $test . '::addsTen' )
			->needs( '$val' )
			->give( static fn (): int => 2990 );

		$this->assertSame( expected: 3000, actual: $this->app->call( $test . '::addsTen' ) );
		$this->assertSame( expected: 3000, actual: $this->app->call( $test, methodName: 'addsTen' ) );

		$this->app->when( "{$test}::getArrayAccess" )
			->needs( ArrayAccess::class )
			->give( WeakMap::class );

		$this->assertInstanceOf( WeakMap::class, $this->app->call( $test, methodName: 'getArrayAccess' ) );

		$arrayAccessMethod = $this->app->get( $test )->getArrayAccess( ... );

		$this->app->when( $arrayAccessMethod )
			->needs( ArrayAccess::class )
			->give( WeakMap::class );

		$this->assertInstanceOf( WeakMap::class, $this->app->call( $arrayAccessMethod ) );

		$this->withEventListenerValue( value: 85 );

		$this->assertSame( expected: 90, actual: $this->app->call( $test ) );

		$this->assertSame( expected: 90, actual: $this->app->call( array( $this->app->get( $test ), '__invoke' ) ) );

		$this->withEventListenerValue( value: 185 );

		$this->assertSame( expected: 190, actual: $this->app->call( $test ) );

		$this->withEventListenerValue( value: 188 );

		$this->assertSame( expected: 190, actual: $this->app->call( $testGetString ) );

		$this->withEventListenerValue( value: 0 );

		$this->assertSame( expected: 10, actual: $this->app->call( $this->app->get( $test )->addsTen( ... ) ) );

		$this->withEventListenerValue( value: 0 );

		$this->assertSame( expected: 10, actual: $this->app->call( array( $this->app->get( $test ), 'addsTen' ) ) );

		$this->assertSame( expected: 30, actual: $this->app->call( $test, array( 'val' => 25 ) ) );
		$this->assertSame(
			expected: 30,
			actual: $this->app->call( $this->app->get( $test ), array( 'val' => 25 ), 'no effect' )
		);

		$this->assertSame(
			expected: 130,
			actual: $this->app->call( $test, array( 'val' => 125 ), '__invoke' )
		);
		$this->assertSame(
			expected: 127,
			actual: $this->app->call( $test, array( 'val' => 125 ), 'get' )
		);
		$this->assertSame(
			expected: 140,
			actual: $this->app->call( $testGetString, array( 'val' => 138 ) )
		);

		$this->assertSame(
			expected: 40,
			actual: $this->app->call( $this->app->get( $test )->addsTen( ... ), array( 'val' => 30 ), 'no effect' )
		);

		$this->app->setMethod( entry: $testInvokeInstance, callback: static fn( $test ) => $test( 15 ) );

		$this->assertSame( expected: 20, actual: $this->app->call( $this->app->get( $test ) ) );

		$this->app->setMethod( entry: $testInvokeString, callback: static fn( $test ) => $test( 115 ) );

		$this->assertSame( expected: 120, actual: $this->app->call( $test ) );

		$this->app->setMethod( entry: $testGetString, callback: static fn( $test ) => $test->addsTen( 140 ) );

		$this->assertSame( expected: 150, actual: $this->app->call( $testGetString ) );
		$this->assertSame(
			expected: 150,
			actual: $this->app->call( $test, array( 'val' => 490 ), 'get' ),
			message: 'Coz "$test::get" is already bound ($testGetString), we get binding result instead.'
		);

		$this->app->setMethod( entry: $this->app->get( $test )->addsTen( ... ), callback: static fn() => 23 );

		$this->assertSame( expected: 23, actual: $this->app->call( $this->app->get( $test )->addsTen( ... ) ) );
		$this->assertSame( expected: 23, actual: $this->app->call( array( $this->app->get( $test ), 'addsTen' ) ) );

		$this->assertSame(
			expected: 500,
			actual: $this->app->call( $test, array( 'val' => 490 ), 'addsTen' )
		);

		// $this->app->setMethod( $this->app->get( $test )->getValue( ... ), static fn( $test ) => $test->setValue( 99 )->getValue() );
		// Or
		// $this->app->call( $this->app->get( $test )->setValue( ... ), array( 'val' => 99 ) );
		// Or
		$this->app->get( $test )->setValue( 99 );

		$this->assertSame( 99, $this->app->call( $this->app->get( $test )->getValue( ... ) ) );
		$this->assertSame( 99, $this->app->get( $test )->getValue() );
	}

	private function withEventListenerValue( int $value ): void {
		$this->app->when( EventType::Building )
			->for( 'int', paramName: 'val' )
			->listenTo( fn ( BuildingEvent $e ) => $e->setBinding( new Binding( fn() => $value ) ) );
	}

	public function testReboundValueOfDependencyBindingUpdatedAtLaterTime(): void {
		$this->app->set(
			id: CollectionStack::class,
			concrete: function () {
				$stack = new CollectionStack();

				$stack->set( key: 'PHP', value: 'Developer' );

				return $stack;
			}
		);

		$this->app->set( id: Binding::class, concrete: fn() => new Binding( 'original' ) );

		$this->app->setShared(
			id: 'test',
			concrete: fn ( Container $app ) => ( new _Rebound__SetterGetter__Stub() )->set(
				binding: $app->useRebound(
					Binding::class,
					fn( Binding $obj, Container $app ) => $app->get( id: 'test' )->set( binding: $obj )
				)
			)
		);

		$this->assertSame( expected: 'original', actual: $this->app->get( id: 'test' )->get()->material );

		$this->app->set( id: Binding::class, concrete: fn() => new Binding( 'mutated' ) );

		$this->assertSame( expected: 'mutated', actual: $this->app->get( 'test' )->get()->material );

		$this->expectException( exception: NotFoundExceptionInterface::class );
		$this->expectExceptionMessage(
			message: 'Unable to find entry for the given id: "notBoundYet" when possible rebinding was expected.'
		);

		$this->app->set(
			id: 'noDependencyBound',
			concrete: fn( Container $app ) => ( new _Rebound__SetterGetter__Stub() )
				->set( binding: $app->useRebound( 'notBoundYet', function () {} ) )
		);

		$this->app->get( id: 'noDependencyBound' );
	}

	public function testEventListenerBeforeBuild(): void {
		$this->app->when( EventType::BeforeBuild )
			->for( _Stack__ContextualBindingWithArrayAccess__Stub::class )
			->listenTo(
				static function ( BeforeBuildEvent $e ) {
					if ( _Stack__ContextualBindingWithArrayAccess__Stub::class === $e->getEntry() ) {
						$e->setParam( 'array', new WeakMap() );
					}
				}
			);

		$this->assertInstanceOf(
			expected: WeakMap::class,
			actual: $this->app->get( _Stack__ContextualBindingWithArrayAccess__Stub::class )->array
		);
	}

	public function testEventListenerDuringBuild(): void {
		/** @var _Main__EntryClass__Stub_Child */
		$instance = $this->app->get( _Main__EntryClass__Stub_Child::class );

		$this->assertInstanceOf( _Primary__EntryClass__Stub::class, $instance->primary );

		$this->app = new Container( eventManager: $eventManager = new EventManager() );

		$this->app->when( EventType::Building )
			->for( _Primary__EntryClass__Stub::class, paramName: 'primary' )
			->listenTo(
				static fn( BuildingEvent $e )
					=> $e->setBinding( new Binding( 'attribute listener stops propagation and this is never listened.' ) )
			);

		/** @var _Main__EntryClass__Stub */
		$instance = $this->app->get( _Main__EntryClass__Stub::class );

		$this->assertTrue( $this->app->isInstance( id: _Primary__EntryClass__Stub::class . ':primary' ) );
		$this->assertInstanceOf( _Primary__EntryClass__Stub_Child::class, actual: $instance->primary );
		$this->assertCount(
			expectedCount: 2,
			haystack: $eventManager->getDispatcher( EventType::Building )->getListeners(
				forEntry: _Primary__EntryClass__Stub::class . ':primary'
			)
		);

		/** @var _Main__EntryClass__Stub_Child */
		$instance = $this->app->get( _Main__EntryClass__Stub_Child::class );

		$this->assertInstanceOf(
			message: 'Same type hinted parameter name must resolve same instance by the container if binding set by the Event Listener has instance set to "true".',
			expected: _Primary__EntryClass__Stub_Child::class,
			actual: $instance->primary
		);

		$this->expectException( LogicException::class );

		$this->app->when( EventType::Building )->for( JustTest__Stub::class )->listenTo( static function ( $e ) {} );
	}

	public function testEventListenerFromParamAttributeAndUserDefinedListener(): void {
		$listener = function ( BuildingEvent $e ): void {
			$concrete = new WeakMap();

			$concrete[ EventType::Building ] = 'from user';

			$e->setBinding( new Binding( fn() => $concrete ) );
		};

		$this->app->when( EventType::Building )
			->for( WeakMap::class, 'attrListenerPrecedence' )
			->listenTo( $listener );

			/** @var _OverrideWth_Param_Event_Listener__Stub */
		$instance = $this->app->get( _OverrideWth_Param_Event_Listener__Stub::class );

		$this->assertSame( 'from attribute', actual: $instance->attrListenerPrecedence[ EventType::Building ] );

		$value = $this->app->call( $instance->getValue( ... ) );
		$this->assertSame( 'from attribute', $value );

		$stoppableListener = static function ( BuildingEvent $e ): void {
			// Use binding from previous listener.
			$concrete = ( $e->getBinding()->material )();

			$concrete[ EventType::Building ] = 'halted attribute listener';

			$e->setBinding( new Binding( fn() => $concrete ) )->stopPropagation();
		};

		$this->app->when( EventType::Building )
			->for( WeakMap::class, 'attrListenerPrecedence' )
			->listenTo( $stoppableListener );

		$this->app->when( EventType::Building )
			->for( WeakMap::class, 'attrListenerPrecedence' )
			->listenTo(
				static function ( BuildingEvent $e ) {
					echo 'Listener with lowest priority -10 is listened.';

					self::assertNull(
						actual: $e->getBinding(),
						message: 'Earliest Listener must not have any bindings unless this listener set it.'
					);
				},
				-10
			);

		$this->expectOutputString( 'Listener with lowest priority -10 is listened.' );

		$this->assertSame(
			expected: 'halted attribute listener',
			actual: $this->app->get( _OverrideWth_Param_Event_Listener__Stub::class )
				->attrListenerPrecedence[ EventType::Building ]
		);
	}

	public function testEventOverridesPreviousListenerBindingDuringBuild(): void {
		$id = ArrayAccess::class . ':array';

		$this->app->when( EventType::Building )
			->for( ArrayAccess::class, paramName: 'array' )
			->listenTo(
				static fn ( BuildingEvent $event ) => $event->setBinding(
					new SharedBinding( new Stack() )
				)
			);

		$this->app->get( _Stack__ContextualBindingWithArrayAccess__Stub::class );

		$this->assertTrue( $this->app->isInstance( $id ) );

		$this->setUp();

		$this->app->when( EventType::Building )
			->for( ArrayAccess::class, paramName: 'array' )
			->listenTo(
				static fn ( BuildingEvent $event ) => $event->setBinding(
					new SharedBinding( $event->app()->get( Stack::class ) )
				)
			);

		$this->app->when( EventType::Building )
			->for( ArrayAccess::class, paramName: 'array' )
			->listenTo(
				static fn ( BuildingEvent $event ) => $event->setBinding(
					new Binding( Stack::class )
				)
			);

			$afterBuild = static function ( AfterBuildEvent $e ) {
				$e->update(
					with: static fn( Stack $stack ) => $stack->set( key: 'afterBuild', value: 'Stack As ArrayAccess' ),
				);
			};

		$this->app->when( EventType::AfterBuild )
			->for( Stack::class )
			->listenTo( $afterBuild );

			/** @var _Stack__ContextualBindingWithArrayAccess__Stub */
		$instance = $this->app->get( _Stack__ContextualBindingWithArrayAccess__Stub::class );

		$this->assertFalse(
			condition: $this->app->isInstance( $id ),
			message: 'Shared binding of a listener is overridden by subsequent listeners.'
		);

		$this->assertSame( 'Stack As ArrayAccess', actual: $instance->array['afterBuild'] );
	}

	private function getClassWithDecoratorAttribute(): JustTest__Stub {
		return new #[DecorateWith( listener: array( self::class, 'useDecorator' ) )]
		class() implements JustTest__Stub {
			public function __construct( public ArrayAccess $stack = new Stack() ) {}

			public function getStack(): ArrayAccess {
				return $this->stack;
			}

			public function getStatus(): string {
				return 'Base-Class:';
			}

			public static function useDecorator( AfterBuildEvent $e ): void {
				$e->decorateWith( _Stack__ContextualBindingWithArrayAccess__Decorator__Stub::class )
					->update(
						with: static fn ( JustTest__Stub $decorator ) =>
							( $decorator->getStack()['updated'] = 'from attribute' )
					);
			}
		};
	}

	public function testAllEvents(): void {
		$this->app->setAlias( _Stack__ContextualBindingWithArrayAccess__Stub::class, 'decoratorTest' );
		$this->app->set( JustTest__Stub::class, 'decoratorTest' );

		$this->app->when( EventType::BeforeBuild )
			->for( JustTest__Stub::class )
			->listenTo( static fn ( BeforeBuildEvent $e ) => $e->setParam( name: 'array', value: new Stack() ) );

		$this->app->when( EventType::Building )
			->for( 'string', paramName: 'name' )
			->listenTo( static fn( BuildingEvent $e ) => $e->setBinding( new Binding( fn() => 'hello!' ) ) );

		$this->app->when( EventType::AfterBuild )
			->for( JustTest__Stub::class )
			->listenTo(
				static fn ( AfterBuildEvent $e ) => $e
					->decorateWith( _Stack__ContextualBindingWithArrayAccess__Decorator__Stub::class )
					->update( with: static fn ( JustTest__Stub $d ) => ( $d->getStack()['updated'] = 'from event' ) )
			);

		/** @var JustTest__Stub */
		$instance = $this->app->get( JustTest__Stub::class );

		$this->assertInstanceOf( _Stack__ContextualBindingWithArrayAccess__Decorator__Stub::class, $instance );
		$this->assertSame( 'from event', $instance->getStack()['updated'] );
		$this->assertSame( 'hello!', $instance->name );

		$this->tearDown();
		$this->setUp();

		$baseClass = $this->getClassWithDecoratorAttribute();

		$this->app->setAlias( $baseClass::class, 'baseClass' );
		$this->app->set( JustTest__Stub::class, 'baseClass' );

		$firstDecorator = new class() implements JustTest__Stub {
			public function __construct( public ?JustTest__Stub $stub = null ) {}

			public function getStack(): ArrayAccess {
				$stack          = $this->stub->getStack();
				$stack['first'] = 'value';

				return $stack;
			}

			public function getStatus(): string {
				return $this->stub->getStatus() . 'First-Decorator:';
			}
		};

		$finalDecorator = new class() implements JustTest__Stub {
			public function __construct( public ?JustTest__Stub $stub = null ) {}

			public function getStack(): ArrayAccess {
				$stack          = $this->stub->getStack();
				$stack['final'] = 'value';

				return $stack;
			}

			public function getStatus(): string {
				return $this->stub->getStatus() . 'Final-Decorator:';
			}
		};

		$this->app->when( EventType::AfterBuild )
			->for( 'baseClass' )
			->listenTo( static fn ( AfterBuildEvent $e ) => $e->decorateWith( $finalDecorator::class ), priority: 20 );

			$this->app->when( EventType::AfterBuild )
			->for( 'baseClass' )
			->listenTo( static fn ( AfterBuildEvent $e ) => $e->decorateWith( $firstDecorator::class ), priority: -10 );

		$stub = $this->app->get( JustTest__Stub::class );

		$this->assertSame( 'Base-Class:Main-Decorator:First-Decorator:Final-Decorator:', $stub->getStatus() );
		$this->assertInstanceOf( $finalDecorator::class, $stub );

		$this->app->when( EventType::AfterBuild )
			->for( 'baseClass' )
			->listenTo(
				priority: 99,
				listener: static fn( AfterBuildEvent $e )
					=> $e->decorateWith(
						static fn( JustTest__Stub $stub ) => new _Stack__ContextualBindingWithArrayAccess__Decorator__Stub( $stub, 'asCallable' )
					),
			);

			$this->assertSame( 'asCallable', $this->app->get( JustTest__Stub::class )->name );

			$eventManager = new EventManager();
			$eventManager->setDispatcher( false, EventType::Building );

			$this->app = new Container( eventManager: $eventManager );

			$this->expectException( LogicalError::class );

			$this->app->when( EventType::Building )
				->for( 'string', paramName: 'name' )
				->listenTo( static function () {} );
	}

	#[ DataProvider( 'provideVariousExceptionTypesForAfterBuildEvent' ) ]
	public function testWaysDecoratorsCanFail( string $decorator, string $exception ): void {
		$this->app->set( JustTest__Stub::class, _Stack__ContextualBindingWithArrayAccess__Stub::class );

		$this->app->when( EventType::AfterBuild )
			->for( JustTest__Stub::class )
			->listenTo( static fn( AfterBuildEvent $e ) => $e->decorateWith( $decorator ) );

		if ( class_exists( $exception ) ) {
			$this->expectException( $exception );
		} else {
			$this->expectExceptionMessage( sprintf( $exception, $decorator ) );
		}

		$this->app->get( JustTest__Stub::class, args: array( 'array' => $this->createStub( ArrayAccess::class ) ) );
	}

	public static function provideVariousExceptionTypesForAfterBuildEvent(): array {
		$building             = _Stack__ContextualBindingWithArrayAccess__Stub::class;
		$invalidTypeOrNoParam = AfterBuildHandler::INVALID_TYPE_HINT_OR_NOT_FIRST_PARAM;
		$withNoParam          = _Stack__ContextualBindingWithArrayAccess__DecoratorWithNoParam__Stub::class;
		$withInvalidType      = new class() implements JustTest__Stub {
			public function __construct( public string $shouldBe__JustTest__Stub = '' ) {}

			public function getStack(): ArrayAccess {
				return new WeakMap();
			}

			public function getStatus(): string {
				return '';
			}
		};

		return array(
			array( $withNoParam, sprintf( AfterBuildHandler::ZERO_PARAM_IN_CONSTRUCTOR, $withNoParam ) ),
			array( parent::class, 'Unable to instantiate the target class: "%s"' ),
			array( $withInvalidType::class, sprintf( $invalidTypeOrNoParam, $withInvalidType::class, $building ) ),
			array( self::class, sprintf( $invalidTypeOrNoParam, self::class, $building ) ),
		);
	}

	public function testAfterBuildEventForInstanceShouldInvokeDispatcherMethodsOnlyOnce(): void {
		/** @var EventManager&MockObject */
		$eventManager = $this->createMock( EventManager::class );
		$dispatcher   = $this->createMock( EventDispatcher::class );
		$instance     = $this->getClassWithDecoratorAttribute();
		$this->app    = new Container( eventManager: $eventManager );

		// Not checking behavior of event manager here. Only interested in dispatcher.
		$eventManager->expects( $this->any() )
			->method( 'getDispatcher' )
			->with( EventType::AfterBuild )
			->willReturn( $dispatcher );

		$dispatcher->expects( $this->once() )
			->method( 'getPriorities' )
			->willReturn( array( 'high' => 10, 'low' => 5 ) ); // phpcs:ignore

		$dispatcher->expects( $this->once() )->method( 'reset' )->with( 'test' );
		$dispatcher->expects( $this->once() )
			->method( 'addListener' )
			->with( static function () {}, 'test', 4 );

		$this->app->setInstance( 'test', $instance );

		$this->app->get( 'test' );
		$this->app->get( 'test' );
		$this->app->get( 'test' );
		$this->app->get( 'test' );
	}

	public function testAfterBuildEventListenersAreInvalidatedForAnInstance(): void {
		$this->app  = new Container( eventManager: $eventManager = new EventManager() );
		$dispatcher = $eventManager->getDispatcher( EventType::AfterBuild );
		$concrete   = $this->getClassWithDecoratorAttribute();

		// When already instantiated class is set as a shared instance.
		$this->app->setInstance( 'test', $concrete );
		$this->assertNull( $this->app->getResolved( 'test' ) );
		$this->assertFalse( $this->app->hasResolved( 'test' ) );
		$this->assertTrue( $this->app->isInstance( 'test' ) );

		$this->app->when( EventType::AfterBuild )
			->for( 'test' )
			->listenTo(
				static fn( AfterBuildEvent $e )
					=> $e->decorateWith( _Stack__ContextualBindingWithArrayAccess__Decorator__Stub::class )
			);

		// Because concrete is an instantiated class, "forEntry" will be the $id when set to container.
		$this->assertTrue( $dispatcher->hasListeners( forEntry: 'test' ) );

		$instance = $this->app->get( 'test' );

		$this->assertTrue( $this->app->hasResolved( 'test' ) );
		$this->assertSame(
			array( $concrete::class => _Stack__ContextualBindingWithArrayAccess__Decorator__Stub::class ),
			$this->app->getResolved( 'test' )
		);

		$this->assertInstanceOf( _Stack__ContextualBindingWithArrayAccess__Decorator__Stub::class, $instance );
		$this->assertFalse( $dispatcher->hasListeners( forEntry: 'test' ) );
		$this->assertSame( $instance, $this->app->get( 'test' ) );

		// When a classname is set as a shared instance.
		$this->app->setShared( JustTest__Stub::class, _Stack__ContextualBindingWithArrayAccess__Stub::class );

		$this->assertFalse( $this->app->hasResolved( JustTest__Stub::class ) );
		$this->assertFalse( $this->app->isInstance( JustTest__Stub::class ) );

		$this->app->when( EventType::AfterBuild )
			->for( JustTest__Stub::class )
			->listenTo(
				static fn( AfterBuildEvent $e )
					=> $e->decorateWith( _Stack__ContextualBindingWithArrayAccess__Decorator__Stub::class )
			);

		// Because concrete is a classname, "forEntry" will be the $concrete when set to container.
		$this->assertTrue( $dispatcher->hasListeners( _Stack__ContextualBindingWithArrayAccess__Stub::class ) );

		$instance = $this->app->get( JustTest__Stub::class, args: array( 'array' => new WeakMap() ) );

		$this->assertTrue( $this->app->hasResolved( JustTest__Stub::class ) );
		$this->assertTrue( $this->app->isInstance( JustTest__Stub::class ) );
		$this->assertSame(
			array( _Stack__ContextualBindingWithArrayAccess__Stub::class => _Stack__ContextualBindingWithArrayAccess__Decorator__Stub::class ),
			$this->app->getResolved( JustTest__Stub::class )
		);

		$this->assertFalse( $dispatcher->hasListeners( _Stack__ContextualBindingWithArrayAccess__Stub::class ) );
		$this->assertInstanceOf( _Stack__ContextualBindingWithArrayAccess__Decorator__Stub::class, $instance );
		$this->assertSame( $instance, $this->app->get( JustTest__Stub::class ) );
	}

	public function testEventListenerAttributeCompiledForEntry(): void {
		$concrete = $this->getClassWithDecoratorAttribute();

		$this->assertFalse( $this->app->isListenerFetchedFrom( $concrete::class, DecorateWith::class ) );

		$this->app->setShared( JustTest__Stub::class, $concrete::class );
		$this->app->get( JustTest__Stub::class );

		$this->assertTrue( $this->app->isListenerFetchedFrom( $concrete::class, DecorateWith::class ) );

		$this->app->get( JustTest__Stub::class );
		$this->app->get( JustTest__Stub::class );
		$this->app->get( JustTest__Stub::class );

		$singleton = $this->app->get( JustTest__Stub::class );

		$this->assertInstanceOf( _Stack__ContextualBindingWithArrayAccess__Decorator__Stub::class, $singleton );
	}

	public function testBuildingParamIsDecoratedWithAfterBuildEvent(): void {
		$typeWithParamName = JustTest__Stub::class . ':stub';
		$class             = new class() {
			public function __construct( public ?JustTest__Stub $stub = null ) {}
		};

		$this->app->when( _Stack__ContextualBindingWithArrayAccess__Stub::class )
			->needs( ArrayAccess::class )
			->give( WeakMap::class );

		$this->app->when( EventType::Building )
			->for( JustTest__Stub::class, 'stub' )
			->listenTo(
				static fn( BuildingEvent $e )
					=> $e->setBinding( new Binding( _Stack__ContextualBindingWithArrayAccess__Stub::class ) )
			);

		$this->app->get( $class::class );

		$this->assertFalse( $this->app->isInstance( $typeWithParamName ) );

		$this->app->when( EventType::AfterBuild )
			->for( _Stack__ContextualBindingWithArrayAccess__Stub::class )
			->listenTo(
				static fn( AfterBuildEvent $e )
					=> $e->decorateWith( _Stack__ContextualBindingWithArrayAccess__Decorator__Stub::class )
			);

		$classInstance = $this->app->get( $class::class );

		$this->assertInstanceOf( _Stack__ContextualBindingWithArrayAccess__Decorator__Stub::class, $classInstance->stub );

		$this->setUp();

		$this->app->when( _Stack__ContextualBindingWithArrayAccess__Stub::class )
			->needs( ArrayAccess::class )
			->give( WeakMap::class );

		$this->app->when( EventType::Building )
			->for( JustTest__Stub::class, 'stub' )
			->listenTo(
				static fn( BuildingEvent $e )
					=> $e->setBinding(
						new SharedBinding( $e->app()->get( _Stack__ContextualBindingWithArrayAccess__Stub::class ) )
					)
			);

		$this->app->when( EventType::AfterBuild )
			->for( $typeWithParamName )
			->listenTo(
				static fn( AfterBuildEvent $e )
					=> $e->decorateWith( _Stack__ContextualBindingWithArrayAccess__Decorator__Stub::class )
			);

		$classInstance = $this->app->get( $class::class );

		$this->assertTrue( $this->app->isInstance( $typeWithParamName ) );
		$this->assertInstanceOf( _Stack__ContextualBindingWithArrayAccess__Decorator__Stub::class, $classInstance->stub );
	}

	public function testWithCompiledContainer(): void {
		$interfaceStub = $this->createStub( Compilable::class )::class;
		$concreteStub  = $this->createStub( ArrayAccess::class )::class;

		$bindings = array(
			'usingAlias'          => new SharedBinding( new stdClass() ),
			self::class           => new SharedBinding( $this ),
			Compilable::class     => new Binding( $interfaceStub, isShared: true ),
			'stack'               => new Binding( $concreteStub, isShared: true ),
			JustTest__Stub::class => new Binding( _Stack__ContextualBindingWithArrayAccess__Stub::class ),
		);

		$contextual = array(
			_Stack__ContextualBindingWithArrayAccess__Stub::class => array(
				ArrayAccess::class => WeakMap::class,
			),
		);

		$app = new Container(
			bindings: Stack::fromCompiledArray( $bindings ),
			contextManager: CollectionStack::fromCompiledArray( $contextual )
		);

		$this->assertInstanceOf( stdClass::class, $app->get( 'usingAlias' ) );
		$this->assertSame( $this, $app->get( self::class ) );

		$stub = $app->get( JustTest__Stub::class );

		$this->assertInstanceOf( _Stack__ContextualBindingWithArrayAccess__Stub::class, $stub );

		$this->assertSame( $app->get( Compilable::class ), $app->get( Compilable::class ) );
		$this->assertSame( $app->get( 'stack' ), $app->get( 'stack' ) );
	}

	public function acceptsDifferentTypesOfParameters( string $name, Countable $object, ArrayAccess ...$variadic ) {
		$this->assertSame( 'test', $name );
		$this->assertInstanceOf( ArrayObject::class, $object );
		$this->assertInstanceOf( SplFixedArray::class, $variadic[0] );
		$this->assertInstanceOf( WeakMap::class, $variadic[1] );
	}

	public function testParamResolverResolvesDependencies(): void {
		$target = Unwrap::forBinding( $this->acceptsDifferentTypesOfParameters( ... ) );

		$this->app->when( $target )->needs( '$name' )->give( 'test' );
		$this->app->when( $target )->needs( ArrayAccess::class )->give(
			static fn () => array( new SplFixedArray(), new WeakMap() )
		);

		$this->app->call( $this->acceptsDifferentTypesOfParameters( ... ), array( 'object' => new ArrayObject() ) );
	}
}

interface JustTest__Stub {
	public function getStack(): ArrayAccess;
	public function getStatus(): string;
}

class _Stack__ContextualBindingWithArrayAccess__Stub implements JustTest__Stub {
	public function __construct( public readonly ArrayAccess $array ) {}

	public function has( string $key ) {
		return $this->array->offsetExists( $key );
	}

	public function getStack(): ArrayAccess {
		return $this->array;
	}

	public function getStatus(): string {
		return 'Main-Class:';
	}
}

class _Stack__ContextualBindingWithArrayAccess__Decorator__Stub implements JustTest__Stub {
	public function __construct( public readonly JustTest__Stub $stub, public readonly string $name = '' ) {}

	public function test( Stack $stack ): void {
		$stack->set( 'asAttr', 'works' );
	}

	public function getStack(): ArrayAccess {
		return $this->stub->getStack();
	}

	public function getStatus(): string {
		return $this->stub->getStatus() . 'Main-Decorator:';
	}
}

class _Stack__ContextualBindingWithArrayAccess__DecoratorWithNoParam__Stub implements JustTest__Stub {
	public function __construct() {}

	public function getStack(): ArrayAccess {
		return new WeakMap();
	}

	public function getStatus(): string {
		return '';
	}
}

class _Main__EntryClass__Stub {
	public function __construct(
		#[ListenTo( listener: array( self::class, 'resolvePrimaryChild' ) )]
		public _Primary__EntryClass__Stub $primary
	) {}

	public static function resolvePrimaryChild( BuildingEvent $event ): void {
		$event->stopPropagation()->setBinding(
			new SharedBinding( $event->app()->get( _Primary__EntryClass__Stub_Child::class ) )
		);
	}
}

class _Main__EntryClass__Stub_Child extends _Main__EntryClass__Stub {
	public function __construct( public _Primary__EntryClass__Stub $primary ) {}
}

class _Primary__EntryClass__Stub {
	public function __construct( public readonly _Secondary__EntryClass__Stub $secondary ) {}
}

class _Primary__EntryClass__Stub_Child extends _Primary__EntryClass__Stub {
	public function __construct() {}
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

class _OverrideWth_Param_Event_Listener__Stub {
	public function __construct(
		#[ListenTo( listener: array( self::class, 'override' ), isFinal: true )]
		public readonly WeakMap $attrListenerPrecedence
	) {}

	public function getValue( #[ListenTo( array( self::class, 'useType' ) )] EventType $type ): string {
		return $this->attrListenerPrecedence[ $type ];
	}

	public static function override( BuildingEvent $e ): void {
		$concrete = new WeakMap();

		$concrete[ EventType::Building ] = 'from attribute';

		$e->setBinding( new Binding( fn() => $concrete ) );
	}

	public static function useType( BuildingEvent $e ): void {
		$e->setBinding( new Binding( fn() => EventType::Building ) );
	}
}
