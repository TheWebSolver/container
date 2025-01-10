<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Container\Container;
use TheWebSolver\Codegarage\Container\Data\Binding;
use TheWebSolver\Codegarage\Container\Event\BuildingEvent;
use TheWebSolver\Codegarage\Container\Event\EventDispatcher;
use TheWebSolver\Codegarage\Container\Helper\MethodResolver;
use TheWebSolver\Codegarage\Container\Error\BadResolverArgument;

class MethodResolverTest extends TestCase {
	private EventDispatcher|MockObject $dispatcher;
	private Container|MockObject $app;
	private MethodResolver $resolver;

	protected function setUp(): void {
		/** @var Container&MockObject */
		$this->app = $this->createMock( Container::class );
		/** @var EventDispatcher&MockObject */
		$this->dispatcher = $this->createMock( EventDispatcher::class );
		$this->resolver   = ( new MethodResolver( $this->app ) )->usingEventDispatcher( $this->dispatcher );
	}

	protected function tearDown(): void {
		$this->setUp();
	}

	/** @return array{0:object,1:string,2:string} */
	private function getTestClassInstanceStub(): array {
		$test = new class() {
			public function get( string $name = 'Default' ): string {
				return "Name: {$name}";
			}

			public function alt( string $name = 'Alternate' ): string {
				return $this->get( $name );
			}

			public function __invoke( string $name = 'Invocable' ): string {
				return $this->get( $name );
			}

			public static function getStatic( string $name = 'Static' ): string {
				return "Name: {$name}";
			}
		};

		$normalId   = $test::class . '::get';
		$instanceId = $test::class . '#' . spl_object_id( $test ) . '::get';

		return array( $test, $normalId, $instanceId );
	}

	public function testMethodBindingWithInstantiatedClassAsCb(): void {
		[ $test,, $instanceId ] = $this->getTestClassInstanceStub();
		$event                  = new BuildingEvent( $this->app, 'test' );

		$this->app
			->expects( $this->exactly( 2 ) )
			->method( 'getBinding' )
			->with( $instanceId )
			->willReturn( new Binding( static fn( $class ) => $class->get( 'John' ) ), null );

		$this->dispatcher->expects( $this->once() )
			->method( 'hasListeners' )
			->willReturn( true );

		$this->dispatcher
			->expects( $this->once() )
			->method( 'dispatch' )
			->willReturn( $event );

		$this->assertSame(
			expected: 'Name: John',
			actual: $this->resolver->withCallback( array( $test, 'get' ), 'no effect' )->resolve()
		);

		$this->assertSame(
			expected: 'Name: Default',
			actual: $this->resolver->withCallback( array( $test, 'get' ), 'no effect' )->resolve()
		);
	}

	public function testMethodBindingWithInstantiatedClosureAsCb(): void {
		[ $test,, $instanceId ] = $this->getTestClassInstanceStub();

		$this->app
			->expects( $this->exactly( 2 ) )
			->method( 'getBinding' )
			->with( $instanceId )
			->willReturn( new Binding( static fn( $class ) => $class->get( 'John' ) ), null );

		$this->assertSame(
			expected: 'Name: John',
			actual: $this->resolver->withCallback( $test->get( ... ) )->resolve()
		);

		$this->assertSame(
			expected: 'Name: Default',
			actual: $this->resolver->withCallback( $test->get( ... ) )->resolve()
		);
	}

	public function testMethodBindingWithLazyInstantiatedClassWithCbAsString(): void {
		[ $test, $normalId ] = $this->getTestClassInstanceStub();

		$this->app->expects( $this->exactly( 2 ) )
			->method( 'get' )
			->with( $test::class )
			->willReturn( new $test() );

		$this->app->expects( $this->exactly( 2 ) )
			->method( 'getBinding' )
			->with( $normalId )
			->willReturn( new Binding( static fn( $class ) => $class->get( 'From Binding' ) ), null );

		$this->assertSame(
			expected: 'Name: From Binding',
			actual: $this->resolver->withCallback( $normalId, 'no effect if null or valid method' )->resolve()
		);

		$this->assertSame(
			expected: 'Name: Default',
			actual: $this->resolver->withCallback( $normalId, 'no effect if null or valid method' )->resolve()
		);
	}

	public function testInstantiatedClassPassedToCbAsStringThrowsException(): void {
		[ $test, $normalId, $instanceId ] = $this->getTestClassInstanceStub();

		$this->expectException( BadResolverArgument::class );
		$this->expectExceptionMessage(
			message: 'Cannot resolve instantiated class method "' . $normalId . '()".'
		);

		$this->resolver->withCallback( $instanceId )->resolve();
	}

	#[ DataProvider( 'provideInvalidClassMethod' ) ]
	public function testInvalidClassOrMethodThrowsException( string $cls, ?string $method ): void {
		$msg                = "Unable to find method for class: {$cls}.";
		$validNonResolvable = str_contains( haystack: $cls, needle: '::' );

		if ( $method || $validNonResolvable ) {
			$pre = $validNonResolvable ? explode( '::', $cls, 2 )[0] : $cls;

			$this->app->expects( $this->once() )->method( 'get' )->with( $pre );

			$msg = "Unable to instantiate entry: {$pre}.";
		}

		$this->expectException( BadResolverArgument::class );
		$this->expectExceptionMessage( $msg );

		$this->resolver->withCallback( $cls, $method )->resolve();
	}

	/** @return array<string[]> */
	public static function provideInvalidClassMethod(): array {
		return array(
			array( 'invalidClass', null ),
			array( self::class, null ),
			array( 'invalidClass', 'invalidMethod' ),
			array( self::class, '___invalidMethod____' ),
			array( 'invalidClass::invalidMethod', null ),
			array( 'invalidClass::invalidMethod', 'invalidMethod' ),
		);
	}

	public function testResolvingVariousMethodCallsWithInvocableClass(): void {
		[ $test ] = $this->getTestClassInstanceStub();

		$this->app->expects( $this->exactly( 3 ) )
			->method( 'get' )
			->with( $test::class )
			->willReturn( new $test() );

		$this->assertSame(
			actual: $this->resolver->withCallback( $test::class /* defaults to "__invoke */ )->resolve(),
			expected: 'Name: Invocable'
		);

		$this->assertSame(
			actual: $this->resolver->withCallback( $test::class, 'alt' )->resolve(),
			expected: 'Name: Alternate'
		);

		$this->assertSame(
			actual: $this->resolver->withCallback( $test::class, 'get' )->resolve(),
			expected: 'Name: Default'
		);

		$this->assertSame(
			actual: $this->resolver->withCallback( $test, 'ignored when $cb is invocable object' )->resolve(),
			expected: 'Name: Invocable'
		);

		$resolved = $this->resolver
			->withParameter( array( 'name' => 'Inject' ) )
			->withCallback( $test, 'ignored' )
			->resolve();

		$this->assertSame( actual: $resolved, expected: 'Name: Inject' );
	}

	public function testLazyClassInstantiationAndMethodCallWithVariousParamResolver(): void {
		[ $test, $withGetMethod ] = $this->getTestClassInstanceStub();

		$this->app->expects( $this->exactly( 5 ) )
			->method( 'getBinding' )
			->with( $withGetMethod )
			->willReturn( null, null, null, null, new Binding( static fn( $test ) => $test( 'Binding' ) ) );

		$this->app->expects( $this->exactly( 5 ) )
			->method( 'get' )
			->with( $test::class )
			->willReturn( new $test() );

		// If no contextual, eventual or injected value, default value used.
		$this->assertSame(
			expected: 'Name: Default',
			actual: $this->resolver->withCallback( $withGetMethod )->resolve()
		);

		// Contextual value will take precedence over default value.
		$this->app->expects( $this->exactly( 1 ) )
			->method( 'getContextualFor' )
			->with( '$name' )
			->willReturn( static fn() => 'Contextual' );

		$eventWithValue = $this->createMock( BuildingEvent::class );
		$eventWithValue
			->expects( $this->once() )
			->method( 'getBinding' )
			->willReturn( new Binding( fn() => 'Eventual' ) );

		$eventWithoutValue = $this->createMock( BuildingEvent::class );
		$eventWithValue->expects( $this->once() )->method( 'getBinding' )->willReturn( null );

		// Binding from Event Dispatcher value will take precedence over contextual & default value.
		$this->dispatcher->expects( $this->exactly( 2 ) )
			->method( 'hasListeners' )
			->willReturn( true, true );

		$this->dispatcher->expects( $this->exactly( 2 ) )
			->method( 'dispatch' )
			->willReturn( $eventWithValue, $eventWithoutValue );

		$this->assertSame(
			expected: 'Name: Eventual',
			actual: $this->resolver->withCallback( $withGetMethod )->resolve()
		);

		// Falling back to contextual value when eventual value is "null".
		$this->assertSame(
			expected: 'Name: Contextual',
			actual: $this->resolver->withCallback( $withGetMethod )->resolve()
		);

		// Injected value will take precedence over all other values.
		$this->assertSame(
			expected: 'Name: Injected',
			actual: $this->resolver
				->withParameter( array( 'name' => 'Injected' ) )
				->withCallback( $withGetMethod )
				->resolve()
		);

		// Binding will take precedence over everything else.
		$this->assertSame(
			expected: 'Name: Binding',
			actual: $this->resolver
				->withCallback( $withGetMethod, 'ignored' )
				->withParameter( array( 'ignored' ) )
				->resolve(),
			message: 'Even though cb string is passed with ::get() method, binding value is '
							. ' resolved by directly invoking class (has __invoke() method).',
		);
	}
}
