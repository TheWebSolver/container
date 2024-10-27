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
use TheWebSolver\Codegarage\Lib\Container\Event\EventType;
use TheWebSolver\Codegarage\Lib\Container\Attribute\ListenTo;
use TheWebSolver\Codegarage\Lib\Container\Event\BuildingEvent;
use TheWebSolver\Codegarage\Lib\Container\Error\ContainerError;
use TheWebSolver\Codegarage\Lib\Container\Event\AfterBuildEvent;
use TheWebSolver\Codegarage\Lib\Container\Event\BeforeBuildEvent;

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
		// $this->assertInstanceOf( Closure::class, actual: $this->app->getBinding( 'testClass' )->concrete );
		$this->assertSame( array( 'testClass' => self::class ), $this->app->getBinding( 'testClass' )->concrete );

		$this->assertInstanceOf( self::class, $this->app->get( id: 'testClass' ) );
		$this->assertTrue( $this->app->resolved( id: 'testClass' ) );
		$this->assertTrue( $this->app->resolved( id: self::class ) );

		$this->app->setShared( id: stdClass::class, concrete: null );

		$this->assertFalse( $this->app->isInstance( stdClass::class ) );

		$this->assertSame(
			expected: $this->app->get( id: stdClass::class ),
			actual: $this->app->get( id: stdClass::class )
		);

		// The singleton is resolved and bound as an instance thereafter.
		$this->assertTrue( $this->app->isInstance( stdClass::class ) );

		$this->assertFalse( $this->app->isInstance( id: 'instance' ) );
		$this->assertFalse( $this->app->resolved( id: 'instance' ) );

		$newClass = $this->app->setInstance( id: 'instance', instance: new class() {} );

		$this->assertTrue( $this->app->isInstance( id: 'instance' ) );
		$this->assertSame( $newClass, $this->app->get( id: 'instance' ) );
		$this->assertTrue( $this->app->resolved( id: 'instance' ) );
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
			->needs( requirement: '$concrete' )
			->give( value: 'With Builder' );

		$this->assertSame( 'With Builder', $this->app->get( id: Binding::class )->concrete );

		$this->app->when( Binding::class )
			->needs( requirement: '$concrete' )
			->give( value: static fn (): string => 'With Builder from closure' );

		$this->assertSame( 'With Builder from closure', $this->app->get( id: Binding::class )->concrete );

		$class          = _Stack__ContextualBindingWithArrayAccess__Stub::class;
		$implementation = static function ( Container $app ) {
			$stack = $app->get( Stack::class );

			$stack['key'] = 'value';

			return $stack;
		};

		$this->app->when( $class )
			->needs( requirement: ArrayAccess::class )
			->give( value: $implementation );

		$this->assertSame( expected: 'value', actual: $this->app->get( $class )->getStack()->get( 'key' ) );

		$this->app->when( $class )
			->needs( requirement: ArrayAccess::class )
			->give( value: Stack::class );

		$this->assertInstanceOf( expected: Stack::class, actual: $this->app->get( $class )->getStack() );
	}

	public function testContextualBindingWithAliasing(): void {
		$this->app->setAlias( entry: Binding::class, alias: 'test' );
		$this->app->when( 'test' )->needs( requirement: '$concrete' )->give( value: 'update' );

		$this->assertSame(
			actual: $this->app->getContextual( for: 'test', context: '$concrete' ),
			expected: 'update'
		);
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
			$this->assertTrue( $this->app->resolved( id: $classname ) );
		}
	}

	public function testResolvingParamDuringBuildEventIntegration(): void {
		$this->app->when( EventType::Building )
			->for( entry: ArrayAccess::class, paramName: 'array' )
			->listen(
				function ( BuildingEvent $event ) {
					$stack = new Stack();
					$stack->set( 'key', 'value' );

					$event->setBinding( new Binding( concrete: $stack ) );
				}
			);

		$this->assertSame(
			expected: 'value',
			actual: $this->app->get( _Stack__ContextualBindingWithArrayAccess__Stub::class )->array->get( 'key' )
		);

		$AutoWiredClass = new class() extends Stack {
			public function __construct() {
				$this->set( 'key', 'withParams' );
			}
		};

		$this->assertSame(
			message: 'The injected param value when resolving entry must override event value.',
			expected: 'withParams',
			actual: $this->app->get(
				id: _Stack__ContextualBindingWithArrayAccess__Stub::class,
				with: array( 'array' => $AutoWiredClass )
			)->array->get( 'key' )
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
			->listen(
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
				;
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
			->give( value: static fn(): int => 95 );

		$this->assertSame( expected: 100, actual: $this->app->call( $test ) );
		$this->assertSame( expected: 100, actual: $this->app->call( array( $test, '__invoke' ) ) );

		$this->app->when( $testInvokeString )
			->needs( '$val' )
			->give( value: static fn(): int => 195 );

		$this->assertSame( expected: 200, actual: $this->app->call( $testInvokeString ) );
		$this->assertSame( expected: 200, actual: $this->app->call( $test::class ) );

		$this->app->when( $testGetString )
			->needs( '$val' )
			->give( value: static fn(): int => 298 );

		$this->assertSame( expected: 300, actual: $this->app->call( $testGetString ) );

		$this->app->when( $test->addsTen( ... ) )
			->needs( '$val' )
			->give( value: static fn(): int => 380 );

		$this->assertSame( expected: 390, actual: $this->app->call( $test->addsTen( ... ) ) );
		$this->assertSame( expected: 390, actual: $this->app->call( array( $test, 'addsTen' ) ) );

		$this->app->when( $test::class . '::addsTen' )
			->needs( '$val' )
			->give( value: static fn (): int => 2990 );

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
			->listen( fn ( BuildingEvent $e ) => $e->setBinding( new Binding( concrete: $value ) ) );
	}

	public function testReboundValueOfDependencyBindingUpdatedAtLaterTime(): void {
		$this->app->set(
			id: Stack::class,
			concrete: function () {
				$stack = new Stack();

				$stack->asCollection();

				$stack->set( key: 'john', value: 'doe' );

				return $stack;
			}
		);

		$this->app->set(
			id: 'aliases',
			concrete: fn ( Container $app ) => new Aliases(
				entryStack: $app->useRebound( of: Stack::class, with: static fn ( Stack $obj ) => $obj )
			)
		);

		/** @var Aliases */
		$aliases = $this->app->get( id: 'aliases' );

		$this->assertSame( expected: 'doe', actual: $aliases->get( id: 'john', asEntry: true )[0] );

		$this->app->set(
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

	public function testFireBeforeBuild(): void {
		$app = new Container();

		$app->when( EventType::BeforeBuild )
			->for( entry: _Stack__ContextualBindingWithArrayAccess__Stub::class )
			->listen( $this->beforeBuildListener( ... ) );

		$app->get( _Stack__ContextualBindingWithArrayAccess__Stub::class, with: array( 'array' => new WeakMap() ) );
	}

	private function beforeBuildListener( BeforeBuildEvent $event ): void {
		$this->assertSame( _Stack__ContextualBindingWithArrayAccess__Stub::class, $event->getEntry() );
		$this->assertInstanceOf( WeakMap::class, actual: $event->getParams()['array'] );
	}

	public function testUsingEventListenerDuringBuild(): void {
		/** @var _Main__EntryClass__Stub_Child */
		$instance = $this->app->get( _Main__EntryClass__Stub_Child::class );

		$this->assertInstanceOf( _Primary__EntryClass__Stub::class, $instance->primary );

		$this->app->when( EventType::Building )
			->for( entry: _Primary__EntryClass__Stub::class, paramName: 'primary' )
			->listen( fn( BuildingEvent $e ) => $e->setBinding( new Binding( 'this listener is halted' ) ) );

		/** @var _Main__EntryClass__Stub */
		$instance = $this->app->get( _Main__EntryClass__Stub::class );

		$this->assertTrue(
			$this->app->isInstance( id: Stack::keyFrom( _Primary__EntryClass__Stub::class, 'primary' ) )
		);
		$this->assertInstanceOf( _Primary__EntryClass__Stub_Child::class, actual: $instance->primary );

		/** @var _Main__EntryClass__Stub_Child */
		$instance = $this->app->get( _Main__EntryClass__Stub_Child::class );

		$this->assertInstanceOf(
			message: 'Same type hinted parameter name must resolve same instance by the container if binding set by the Event Listener has instance set to "true".',
			expected: _Primary__EntryClass__Stub_Child::class,
			actual: $instance->primary
		);
	}

	public function testEventListenerFromParamAttributeOverridesUserDefinedListener(): void {
		$listener = function ( BuildingEvent $e ): void {
			$concrete = new WeakMap();

			$concrete[ EventType::Building ] = 'from user';

			$e->setBinding( new Binding( $concrete ) );
		};

		$this->app->when( EventType::Building )
			->for( WeakMap::class, 'attrListenerPrecedence' )
			->listen( $listener );

			/** @var _OverrideWth_Param_Event_Listener__Stub */
		$instance = $this->app->get( _OverrideWth_Param_Event_Listener__Stub::class );

		$this->assertSame( 'from attribute', actual: $instance->attrListenerPrecedence[ EventType::Building ] );

		$value = $this->app->call( $instance->getValue( ... ) );
		$this->assertSame( 'from attribute', $value );
	}

	public function testEventOverridesPreviousListenerBindingDuringBuild(): void {
		$this->app->when( EventType::Building )
			->for( entry: ArrayAccess::class, paramName: 'array' )
			->listen(
				fn ( BuildingEvent $event ) => $event->setBinding(
					new Binding( $this->app->get( Stack::class ), instance: true )
				)
			);

		$this->app->when( EventType::Building )
			->for( entry: ArrayAccess::class, paramName: 'array' )
			->listen(
				fn ( BuildingEvent $event ) => $event->setBinding(
					new Binding( $this->app->get( Stack::class ), instance: false )
				)
			);

			$afterBuild = function ( AfterBuildEvent $e ) {
				// $e->call( fn( Stack $stack ) => $stack->set( key: 'afterBuild', value: 'Stack As ArrayAccess' ) );
				$e->update(
					with: fn( Stack $stack ) => $stack->set( key: 'afterBuild', value: 'Stack As ArrayAccess' ),
				);
			};

		$this->app->when( EventType::AfterBuild )
			->for( Stack::class )
			->listen( $afterBuild );

			/** @var _Stack__ContextualBindingWithArrayAccess__Stub */
		$instance = $this->app->get( _Stack__ContextualBindingWithArrayAccess__Stub::class );

		$this->assertFalse(
			condition: $this->app->isInstance( id: Stack::keyFrom( ArrayAccess::class, 'array' ) ),
			message: 'Only one listener is allowed during build to resolve the particular entry parameter.'
			. ' Subsequent listener will override the previous listener binding.'
		);

		$this->assertSame( 'Stack As ArrayAccess', actual: $instance->array['afterBuild'] );
	}

	public function testAllEvents(): void {
		$this->app->set( JustTest__Stub::class, _Stack__ContextualBindingWithArrayAccess__Stub::class );

		$resolvedArray = static function ( BeforeBuildEvent $e ) {
			$e->setParam( name: 'array', value: new Stack() );
		};

		$this->app->when( EventType::BeforeBuild )
			->for( _Stack__ContextualBindingWithArrayAccess__Stub::class )
			->listen( $resolvedArray );

		$this->app->when( EventType::Building )
			->for( 'string', 'name' )
			->listen( fn( BuildingEvent $e ) => $e->setBinding( new Binding( concrete: 'hello!' ) ) );

		$this->app->when( EventType::AfterBuild )
			->for( _Stack__ContextualBindingWithArrayAccess__Stub::class )
			->listen(
				function ( AfterBuildEvent $e ) {
					$e->decorateWith( _Stack__ContextualBindingWithArrayAccess__Decorator__Stub::class )
						->update(
							with: function ( _Stack__ContextualBindingWithArrayAccess__Decorator__Stub $decorator ) {
								$decorator->stub->array['updated'] = 'from event';
							}
						);
				}
			);

		/** @var _Stack__ContextualBindingWithArrayAccess__Decorator__Stub */
		$instance = $this->app->get( JustTest__Stub::class );

		$this->assertInstanceOf( _Stack__ContextualBindingWithArrayAccess__Decorator__Stub::class, $instance );
		$this->assertSame( 'from event', $instance->stub->array['updated'] );
		$this->assertSame( 'hello!', $instance->name );
	}

	public function testWithAbstract(): void {
		$this->app->setAlias( self::class, 'selfClass' );
		$this->app->set( TestCase::class, 'selfClass' );
		// $this->app->set( self::class, self::class );
		$this->assertInstanceOf( self::class, $this->app->get( self::class ) );
	}

	private static function useForTest(): void {
		// var_dump( 'works' );
	}
}

interface JustTest__Stub {}

class _Stack__ContextualBindingWithArrayAccess__Stub implements JustTest__Stub {
	public function __construct( public readonly ArrayAccess $array ) {
		try {
			call_user_func( ContainerTest::class . '::useForTest' );
		} catch ( TypeError $e ) {
			$reflection = new ReflectionMethod( ContainerTest::class, 'useForTest' );
			$reflection->setAccessible( true );

			$reflection->invoke( null );
		}
	}

	public function has( string $key ) {
		return $this->array->offsetExists( $key );
	}

	public function getStack(): ArrayAccess {
		return $this->array;
	}
}

class _Stack__ContextualBindingWithArrayAccess__Decorator__Stub {
	public function __construct( public readonly _Stack__ContextualBindingWithArrayAccess__Stub $stub, public readonly string $name ) {}

	public function test( Stack $stack ): void {
		$stack->set( 'asAttr', 'works' );
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

	public function getValue( #[ListenTo( array( self::class, 'setType' ) )] EventType $type ): string {
		return $this->attrListenerPrecedence[ $type ];
	}

	public static function override( BuildingEvent $e ): void {
		$concrete = new WeakMap();

		$concrete[ EventType::Building ] = 'from attribute';

		$e->setBinding( new Binding( $concrete ) );
	}

	public static function setType( BuildingEvent $e ): void {
		$e->setBinding( new Binding( EventType::Building ) );
	}
}
