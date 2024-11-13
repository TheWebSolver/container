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
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\NotFoundExceptionInterface;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;
use TheWebSolver\Codegarage\Lib\Container\Data\Aliases;
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Helper\Unwrap;
use TheWebSolver\Codegarage\Lib\Container\Event\EventType;
use TheWebSolver\Codegarage\Lib\Container\Attribute\ListenTo;
use TheWebSolver\Codegarage\Lib\Container\Event\BuildingEvent;
use TheWebSolver\Codegarage\Lib\Container\Error\ContainerError;
use TheWebSolver\Codegarage\Lib\Container\Pool\CollectionStack;
use TheWebSolver\Codegarage\Lib\Container\Event\AfterBuildEvent;
use TheWebSolver\Codegarage\Lib\Container\Event\EventDispatcher;
use TheWebSolver\Codegarage\Lib\Container\Attribute\DecorateWith;
use TheWebSolver\Codegarage\Lib\Container\Event\BeforeBuildEvent;
use TheWebSolver\Codegarage\Lib\Container\Attribute\UpdateOnReset;
use TheWebSolver\Codegarage\Lib\Container\Event\Manager\EventManager;
use TheWebSolver\Codegarage\Lib\Container\Event\Manager\AfterBuildHandler;

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
		$this->assertFalse( $this->app->hasResolved( id: 'testClass' ) );

		$this->app->setAlias( entry: self::class, alias: 'testClass' );

		$this->assertTrue( $this->app->isAlias( id: 'testClass' ) );
		$this->assertSame( self::class, $this->app->getEntryFrom( alias: 'testClass' ) );
		$this->assertInstanceOf( self::class, $this->app->get( 'testClass' ) );

		$this->app->set( id: 'testClass', concrete: self::class );

		// Bind will purge alias from the alias pool coz no need for storing same alias
		// multiple places (like in alias pool as well as in binding pool).
		$this->assertFalse( $this->app->isAlias( id: 'testClass' ) );
		$this->assertTrue( $this->app->has( id: 'testClass' ) );
		$this->assertFalse( $this->app->has( id: self::class ), 'Bound with alias.' );
		$this->assertTrue( $this->app->hasBinding( id: 'testClass' ) );
		$this->assertFalse( $this->app->hasBinding( id: self::class ), 'Bound using alias.' );
		$this->assertSame( 'testClass', $this->app->getEntryFrom( alias: 'testClass' ) );
		$this->assertSame( array( 'testClass' => self::class ), $this->app->getBinding( 'testClass' )->concrete );

		$this->assertInstanceOf( self::class, $this->app->get( id: 'testClass' ) );
		$this->assertTrue( $this->app->hasResolved( id: 'testClass' ) );
		$this->assertTrue( $this->app->hasResolved( id: self::class ) );
		$this->assertSame( self::class, $this->app->getResolved( self::class ) );
		$this->assertTrue( $this->app->removeResolved( self::class ) );

		$this->app->setShared( id: stdClass::class, concrete: null );

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
	}

	public function testAliasAndGetWithoutBinding(): void {
		$this->app->setAlias( entry: self::class, alias: 'testClass' );

		$this->assertInstanceOf( self::class, $this->app->get( id: 'testClass' ) );
		$this->assertInstanceOf( self::class, $this->app->get( id: self::class ) );
	}

	public function testKeepingBothAliasAndBinding(): void {
		$this->app->setAlias( entry: self::class, alias: 'test' );
		$this->app->set( id: self::class, concrete: null );

		$this->assertTrue( $this->app->isAlias( id: 'test' ) );
		$this->assertTrue( $this->app->hasBinding( id: self::class ) );
		$this->assertSame( self::class, $this->app->getEntryFrom( alias: 'test' ) );
		$this->assertSame( self::class, $this->app->getEntryFrom( alias: self::class ) );
	}

	public function testPurgeAliasWhenInstanceIsBoundAndPerformRebound(): void {
		$this->app->setAlias( entry: _Rebound__SetterGetter__Stub::class, alias: 'test' );

		$this->assertTrue( $this->app->isAlias( id: 'test' ) );

		$this->app->set( id: Binding::class, concrete: fn() => new Binding( concrete: 'original' ) );

		$this->app->setInstance(
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

		$this->app->set( id: Binding::class, concrete: fn() => new Binding( concrete: 'updated' ) );

		$this->assertSame( expected: 'updated', actual: $this->app->get( 'test' )->get()->concrete );
	}

	public function testContextualBinding(): void {
		$this->app->when( Binding::class )
			->needs( '$concrete' )
			->give( 'With Builder' );

		$this->assertSame( 'With Builder', $this->app->get( id: Binding::class )->concrete );

		$this->app->when( Binding::class )
			->needs( '$concrete' )
			->give( static fn (): string => 'With Builder from closure' );

		$this->assertSame( 'With Builder from closure', $this->app->get( id: Binding::class )->concrete );

		$class          = _Stack__ContextualBindingWithArrayAccess__Stub::class;
		$implementation = static function ( Container $app ) {
			$stack = $app->get( Stack::class );

			$stack['key'] = 'value';

			return $stack;
		};

		$this->app->when( $class )
			->needs( ArrayAccess::class )
			->give( $implementation );

		$this->assertSame( expected: 'value', actual: $this->app->get( $class )->getStack()->get( 'key' ) );

		$this->app->when( $class )
			->needs( ArrayAccess::class )
			->give( Stack::class );

		$this->assertInstanceOf( expected: Stack::class, actual: $this->app->get( $class )->getStack() );
	}

	public function testContextualBindingWithAliasing(): void {
		$this->app->setAlias( entry: Binding::class, alias: 'test' );
		$this->app->when( 'test' )->needs( '$concrete' )->give( static fn() => 'update' );

		$this->assertSame(
			actual: $this->app->getContextual( for: 'test', context: '$concrete' )(),
			expected: 'update'
		);

		$this->assertSame( 'update', $this->app->get( 'test' )->concrete );
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
			->for( entry: ArrayAccess::class, paramName: 'array' )
			->listenTo( fn ( BuildingEvent $event ) => $event->setBinding( new Binding( concrete: new WeakMap() ) ) );

		$this->assertInstanceOf(
			expected: WeakMap::class,
			actual: $this->app->get( _Stack__ContextualBindingWithArrayAccess__Stub::class )->array
		);

		$this->assertInstanceOf(
			message: 'The injected param value when resolving entry must override event value.',
			expected: Stub::class,
			actual: $this->app->get(
				id: _Stack__ContextualBindingWithArrayAccess__Stub::class,
				with: array( 'array' => $this->createStub( ArrayAccess::class ) )
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

		$eventualClass = new class() extends _Secondary__EntryClass__Stub {
			public function __construct( public readonly string $value = 'Using Event Builder' ) {}
		};

		$this->app->when( EventType::Building )
			->for( entry: _Secondary__EntryClass__Stub::class, paramName: 'secondary' )
			->listenTo(
				function ( BuildingEvent $event ) use ( $eventualClass ) {
					$event->setBinding( new Binding( concrete: $eventualClass ) );
				}
			);

		$secondaryClass = $this->app->get( _Primary__EntryClass__Stub::class )->secondary;

		$this->assertInstanceOf( $eventualClass::class, $secondaryClass );
		$this->assertSame( expected: 'Using Event Builder', actual: $secondaryClass->value );
	}

	/** @return array{0:object,1:string,2:string} */
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
		};

		$normalId   = $test::class . '::get';
		$instanceId = $test::class . '#' . spl_object_id( $test ) . '::get';

		return array( $test, $normalId, $instanceId );
	}

	public function testMethodCallForInvocableClassInstance(): void {
		[ $test, $testGetString ] = $this->getTestClassInstanceStub();
		$testInvokeInstance       = Unwrap::callback( cb: $test );
		$testInvokeString         = Unwrap::asString( object: $test::class, methodName: '__invoke' );

		$this->app->when( $testInvokeInstance )
			->needs( '$val' )
			->give( static fn(): int => 95 );

		$this->assertSame( expected: 100, actual: $this->app->call( $test ) );
		$this->assertSame( expected: 100, actual: $this->app->call( array( $test, '__invoke' ) ) );

		$this->app->when( $testInvokeString )
			->needs( '$val' )
			->give( static fn(): int => 195 );

		$this->assertSame( expected: 200, actual: $this->app->call( $testInvokeString ) );
		$this->assertSame( expected: 200, actual: $this->app->call( $test::class ) );

		$this->app->when( $testGetString )
			->needs( '$val' )
			->give( static fn(): int => 298 );

		$this->assertSame( expected: 300, actual: $this->app->call( $testGetString ) );

		$this->app->when( $test->addsTen( ... ) )
			->needs( '$val' )
			->give( static fn(): int => 380 );

		$this->assertSame( expected: 390, actual: $this->app->call( $test->addsTen( ... ) ) );
		$this->assertSame( expected: 390, actual: $this->app->call( array( $test, 'addsTen' ) ) );

		$this->app->when( $test::class . '::addsTen' )
			->needs( '$val' )
			->give( static fn (): int => 2990 );

		$this->assertSame( expected: 3000, actual: $this->app->call( $test::class . '::addsTen' ) );

		$this->withEventListenerValue( value: 85 );

		$this->assertSame( expected: 90, actual: $this->app->call( $test ) );

		$this->assertSame( expected: 90, actual: $this->app->call( array( $test, '__invoke' ) ) );

		$this->withEventListenerValue( value: 185 );

		$this->assertSame( expected: 190, actual: $this->app->call( $test::class ) );

		$this->withEventListenerValue( value: 188 );

		$this->assertSame( expected: 190, actual: $this->app->call( $testGetString ) );

		$this->withEventListenerValue( value: 0 );

		$this->assertSame( expected: 10, actual: $this->app->call( $test->addsTen( ... ) ) );

		$this->withEventListenerValue( value: 0 );

		$this->assertSame( expected: 10, actual: $this->app->call( array( $test, 'addsTen' ) ) );

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
			actual: $this->app->call( $test->addsTen( ... ), params: array( 'val' => 30 ), defaultMethod: 'no effect' )
		);

		$this->app->setMethod( entry: $testInvokeInstance, callback: static fn( $test ) => $test( 15 ) );

		$this->assertSame( expected: 20, actual: $this->app->call( $test ) );

		$this->app->setMethod( entry: $testInvokeString, callback: static fn( $test ) => $test( 115 ) );

		$this->assertSame( expected: 120, actual: $this->app->call( $test::class ) );

		$this->app->setMethod( entry: $testGetString, callback: static fn( $test ) => $test->addsTen( 140 ) );

		$this->assertSame( expected: 150, actual: $this->app->call( $testGetString ) );
		$this->assertSame(
			expected: 150,
			actual: $this->app->call( $test::class, params: array( 'val' => 490 ), defaultMethod: 'get' ),
			message: 'Coz "$test::get" is already bound ($testGetString), we get binding result instead.'
		);

		$this->app->setMethod( entry: $test->addsTen( ... ), callback: static fn() => 23 );

		$this->assertSame( expected: 23, actual: $this->app->call( $test->addsTen( ... ) ) );
		$this->assertSame( expected: 23, actual: $this->app->call( array( $test, 'addsTen' ) ) );

		$this->assertSame(
			expected: 500,
			actual: $this->app->call( $test::class, params: array( 'val' => 490 ), defaultMethod: 'addsTen' )
		);
	}

	private function withEventListenerValue( int $value ): void {
		$this->app->when( EventType::Building )
			->for( entry: 'int', paramName: 'val' )
			->listenTo( fn ( BuildingEvent $e ) => $e->setBinding( new Binding( concrete: $value ) ) );
	}

	public function testReboundValueOfDependencyBindingUpdatedAtLaterTime(): void {
		$this->app->set(
			id: CollectionStack::class,
			concrete: function () {
				$stack = new CollectionStack();

				$stack->set( key: 'john', value: 'doe' );

				return $stack;
			}
		);

		$this->app->set(
			id: 'aliases',
			concrete: fn ( Container $app ) => new Aliases(
				entryStack: $app->useRebound( of: CollectionStack::class, with: static fn ( CollectionStack $obj ) => $obj )
			)
		);

		/** @var Aliases */
		$aliases = $this->app->get( id: 'aliases' );

		$this->assertSame( expected: 'doe', actual: $aliases->get( id: 'john', asEntry: true )[0] );

		$this->app->set(
			id: CollectionStack::class,
			concrete: function () {
				$stack = new CollectionStack();

				$stack->set( key: 'PHP', value: 'Developer' );

				return $stack;
			}
		);

		/** @var Aliases */
		$aliasWithReboundEntries = $this->app->get( id: 'aliases' );
		$jobs                    = $aliasWithReboundEntries->get( id: 'PHP', asEntry: true );

		$this->assertSame( expected: 'Developer', actual: reset( $jobs ) );

		$this->app->set( id: Binding::class, concrete: fn() => new Binding( concrete: 'original' ) );

		$this->app->setShared(
			id: 'test',
			concrete: fn ( Container $app ) => ( new _Rebound__SetterGetter__Stub() )->set(
				binding: $app->useRebound(
					of: Binding::class,
					with: fn( Binding $obj, Container $app ) => $app->get( id: 'test' )->set( binding: $obj )
				)
			)
		);

		$this->assertSame( expected: 'original', actual: $this->app->get( id: 'test' )->get()->concrete );

		$this->app->set( id: Binding::class, concrete: fn() => new Binding( concrete: 'mutated' ) );

		$this->assertSame( expected: 'mutated', actual: $this->app->get( 'test' )->get()->concrete );

		$this->expectException( exception: NotFoundExceptionInterface::class );
		$this->expectExceptionMessage(
			message: 'Unable to find entry for the given id: "notBoundYet" when possible rebinding was expected.'
		);

		$this->app->set(
			id: 'noDependencyBound',
			concrete: fn( Container $app ) => ( new _Rebound__SetterGetter__Stub() )
				->set( binding: $app->useRebound( of: 'notBoundYet', with: function () {} ) )
		);

		$this->app->get( id: 'noDependencyBound' );
	}

	public function testEventListenerBeforeBuild(): void {
		$app = new Container();

		$app->when( EventType::BeforeBuild )
			->for( entry: _Stack__ContextualBindingWithArrayAccess__Stub::class )
			->listenTo( static fn ( BeforeBuildEvent $e ) => $e->setParam( 'array', new WeakMap() ) );

		$this->assertInstanceOf(
			expected: WeakMap::class,
			actual: $app->get( _Stack__ContextualBindingWithArrayAccess__Stub::class )->array
		);
	}

	public function testEventListenerDuringBuild(): void {
		/** @var _Main__EntryClass__Stub_Child */
		$instance = $this->app->get( _Main__EntryClass__Stub_Child::class );

		$this->assertInstanceOf( _Primary__EntryClass__Stub::class, $instance->primary );

		$this->app->when( EventType::Building )
			->for( entry: _Primary__EntryClass__Stub::class, paramName: 'primary' )
			->listenTo(
				static fn( BuildingEvent $e )
					=> $e->setBinding( new Binding( 'attribute listener stops propagation and this is never listened.' ) )
			);

		/** @var _Main__EntryClass__Stub */
		$instance = $this->app->get( _Main__EntryClass__Stub::class );

		$this->assertTrue( $this->app->isInstance( id: 'primary' ) );
		$this->assertInstanceOf( _Primary__EntryClass__Stub_Child::class, actual: $instance->primary );

		/** @var _Main__EntryClass__Stub_Child */
		$instance = $this->app->get( _Main__EntryClass__Stub_Child::class );

		$this->assertInstanceOf(
			message: 'Same type hinted parameter name must resolve same instance by the container if binding set by the Event Listener has instance set to "true".',
			expected: _Primary__EntryClass__Stub_Child::class,
			actual: $instance->primary
		);

		$this->expectException( LogicException::class );

		$this->app->when( EventType::Building )->for( entry: JustTest__Stub::class )->listenTo( static function ( $e ) {} );
	}

	public function testEventListenerFromParamAttributeAndUserDefinedListener(): void {
		$listener = function ( BuildingEvent $e ): void {
			$concrete = new WeakMap();

			$concrete[ EventType::Building ] = 'from user';

			$e->setBinding( new Binding( $concrete ) );
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
			$concrete = $e->getBinding()->concrete;

			$concrete[ EventType::Building ] = 'halted attribute listener';

			$e->setBinding( new Binding( $concrete ) )->stopPropagation();
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
		$this->app->when( EventType::Building )
			->for( entry: ArrayAccess::class, paramName: 'array' )
			->listenTo(
				static fn ( BuildingEvent $event ) => $event->setBinding(
					new Binding( $event->app()->get( Stack::class ), instance: true )
				)
			);

		$this->app->when( EventType::Building )
			->for( entry: ArrayAccess::class, paramName: 'array' )
			->listenTo(
				static fn ( BuildingEvent $event ) => $event->setBinding(
					new Binding( $event->app()->get( Stack::class ), instance: false )
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
			condition: $this->app->isInstance( id: ArrayAccess::class . '||array' ),
			message: 'Only one listener is allowed during build to resolve the particular entry parameter.'
			. ' Subsequent listener will override the previous listener binding.'
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
			->for( entry: 'string', paramName: 'name' )
			->listenTo( static fn( BuildingEvent $e ) => $e->setBinding( new Binding( concrete: 'hello!' ) ) );

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

		$this->app->setAlias( $firstDecorator::class, 'firstDecorator' );
		$this->app->setAlias( $finalDecorator::class, 'finalDecorator' );

		$this->app->when( EventType::AfterBuild )
			->for( 'baseClass' )
			->listenTo( static fn ( AfterBuildEvent $e ) => $e->decorateWith( 'finalDecorator' ), priority: 20 );

			$this->app->when( EventType::AfterBuild )
			->for( 'baseClass' )
			->listenTo( static fn ( AfterBuildEvent $e ) => $e->decorateWith( 'firstDecorator' ), priority: -10 );

		$stub = $this->app->get( JustTest__Stub::class );

		$this->assertSame( 'Base-Class:Main-Decorator:First-Decorator:Final-Decorator:', $stub->getStatus() );
		$this->assertInstanceOf( $finalDecorator::class, $stub );
	}

	/** @dataProvider provideVariousExceptionTypesForAfterBuildEvent */
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

		$this->app->get( JustTest__Stub::class, with: array( 'array' => $this->createStub( ArrayAccess::class ) ) );
	}

	public function provideVariousExceptionTypesForAfterBuildEvent(): array {
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

	public function testAfterBuildEventForInstanceWithMock(): void {
		/** @var EventManager&MockObject */
		$eventManager = $this->createMock( EventManager::class );
		$dispatcher   = $this->createMock( EventDispatcher::class );
		$instance     = $this->getClassWithDecoratorAttribute();
		$this->app    = new Container( eventManager: $eventManager );

		$eventManager->expects( $this->exactly( 3 ) )
			->method( 'getDispatcher' )
			->with( EventType::AfterBuild )
			->willReturn( $dispatcher );

		$dispatcher->expects( $this->exactly( 1 ) )
			->method( 'getPriorities' )
			->willReturn( array( 'high' => 10, 'low' => 5 ) ); // phpcs:ignore

		$dispatcher->expects( $this->once() )->method( 'reset' )->with( 'test' );
		$dispatcher->expects( $this->once() )->method( 'addListener' );

		$this->app->setInstance( 'test', $instance );

		$this->app->get( 'test' );
		$this->app->get( 'test' );
		$this->app->get( 'test' );
		$this->app->get( 'test' );
	}

	public function testAfterBuildEventListenersAreInvalidatedForAnInstance(): void {
		$dispatcher = $this->app->getEventManager()->getDispatcher( EventType::AfterBuild );
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

		$instance = $this->app->get( JustTest__Stub::class, with: array( 'array' => new WeakMap() ) );

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

	public function testAttributeCompiledForEntry(): void {
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

	public function resetBindingWithEachUpdate(): void {
		// FIXME: Works even without attribute.
		$test = new class() {
			public function __construct( #[UpdateOnReset] public ?ArrayAccess $accessor = null ) {}
		};

		$this->app->set( ArrayAccess::class, Stack::class );

		$this->assertInstanceOf( Stack::class, $this->app->get( $test::class )->accessor );

		$this->app->set( ArrayAccess::class, WeakMap::class );

		$this->assertInstanceOf( WeakMap::class, $this->app->get( $test::class )->accessor );

		$test2 = new class() {
			public ArrayAccess $accessor;

			public function set( #[UpdateOnReset] ArrayAccess $accessor ) {
				$this->accessor = $accessor;
			}
		};

		$this->app->setInstance( $test2::class, $test2 );

		$this->app->set( ArrayAccess::class, Container::class );
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
			new Binding( concrete: $event->app()->get( _Primary__EntryClass__Stub_Child::class ), instance: true )
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

		$e->setBinding( new Binding( $concrete ) );
	}

	public static function useType( BuildingEvent $e ): void {
		$e->setBinding( new Binding( EventType::Building ) );
	}
}
