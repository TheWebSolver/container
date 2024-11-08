<?php
/**
 * Method Resolver test.
 *
 * @package TheWebSolver\Codegarage\Test
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Event\BuildingEvent;
use TheWebSolver\Codegarage\Lib\Container\Event\EventDispatcher;
use TheWebSolver\Codegarage\Lib\Container\Helper\MethodResolver;
use TheWebSolver\Codegarage\Lib\Container\Error\BadResolverArgument;

class MethodResolverTest extends TestCase {
	private EventDispatcher|MockObject|null $dispatcher;
	private Container|MockObject|null $app;
	private ?MethodResolver $resolver;

	protected function setUp(): void {
		/** @var Container&MockObject */
		$this->app = $this->createMock( Container::class );
		/** @var EventDispatcher&MockObject */
		$this->dispatcher = $this->createMock( EventDispatcher::class );

		$this->resolver = new MethodResolver( $this->app, $this->dispatcher );
	}

	protected function tearDown(): void {
		$this->app        = null;
		$this->dispatcher = null;
		$this->resolver   = null;
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
			->willReturn( new Binding( concrete: static fn( $class ) => $class->get( 'John' ) ), null );

		$this->dispatcher->expects( $this->once() )
			->method( 'hasListeners' )
			->willReturn( true );

		$this->dispatcher
			->expects( $this->once() )
			->method( 'dispatch' )
			->willReturn( $event );

		$this->assertSame(
			expected: 'Name: John',
			actual: $this->resolver->resolve( cb: array( $test, 'get' ), default: 'no effect' )
		);

		$this->assertSame(
			expected: 'Name: Default',
			actual: $this->resolver->resolve( cb: array( $test, 'get' ), default: 'no effect' )
		);
	}

	public function testMethodBindingWithInstantiatedClosureAsCb(): void {
		[ $test,, $instanceId ] = $this->getTestClassInstanceStub();

		$this->app
			->expects( $this->exactly( 2 ) )
			->method( 'getBinding' )
			->with( $instanceId )
			->willReturn( new Binding( concrete: static fn( $class ) => $class->get( 'John' ) ), null );

		$this->assertSame(
			expected: 'Name: John',
			actual: $this->resolver->resolve( cb: $test->get( ... ), default: null )
		);

		$this->assertSame(
			expected: 'Name: Default',
			actual: $this->resolver->resolve( cb: $test->get( ... ), default: null )
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
			actual: $this->resolver->resolve( cb: $normalId, default: 'no effect if null or valid method' )
		);

		$this->assertSame(
			expected: 'Name: Default',
			actual: $this->resolver->resolve( cb: $normalId, default: 'no effect if null or valid method' )
		);
	}

	public function testInstantiatedClassPassedToCbAsStringThrowsException(): void {
		[ $test, $normalId, $instanceId ] = $this->getTestClassInstanceStub();

		$this->expectException( BadResolverArgument::class );
		$this->expectExceptionMessage(
			message: 'Cannot resolve instantiated class method "' . $normalId . '()".'
		);

		$this->resolver->resolve( $instanceId, default: null );
	}

	/** @dataProvider provideInvalidClassMethod */
	public function testInvalidClassOrMethodThrowsException( string $cls, ?string $method ): void {
		$msg                = "/Unable to find method for class: {$cls}./";
		$validNonResolvable = str_contains( haystack: $cls, needle: '::' );

		if ( $method || $validNonResolvable ) {
			$pre = $validNonResolvable ? explode( '::', $cls, 2 )[0] : $cls;

			$this->app->expects( $this->once() )->method( 'get' )->with( $pre );

			$msg = "/Unable to instantiate entry: {$pre}./";
		}

		$this->expectException( BadResolverArgument::class );
		$this->expectExceptionMessageMatches( $msg );

		$this->resolver->resolve( ...func_get_args() );
	}

	/** @return array<string[]> */
	public function provideInvalidClassMethod(): array {
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
			actual: $this->resolver->resolve( cb: $test::class, default: null /* defaults to "__invoke */ ),
			expected: 'Name: Invocable'
		);

		$this->assertSame(
			actual: $this->resolver->resolve( cb: $test::class, default: 'alt' ),
			expected: 'Name: Alternate'
		);

		$this->assertSame(
			actual: $this->resolver->resolve( cb: $test::class, default: 'get' ),
			expected: 'Name: Default'
		);

		$this->assertSame(
			actual: $this->resolver->resolve( cb: $test, default: 'ignored when $cb is invocable object' ),
			expected: 'Name: Invocable'
		);

		$this->assertSame(
			actual: $this->resolver->resolve( cb: $test, default: 'ignored', params: array( 'name' => 'Inject' ) ),
			expected: 'Name: Inject'
		);
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
			actual: $this->resolver->resolve( cb: $withGetMethod, default: null )
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
			->willReturn( new Binding( concrete: 'Eventual' ) );

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
			actual: $this->resolver->resolve( cb: $withGetMethod, default: null )
		);

		// Falling back to contextual value when eventual value is "null".
		$this->assertSame(
			expected: 'Name: Contextual',
			actual: $this->resolver->resolve( cb: $withGetMethod, default: null )
		);

		// Injected value will take precedence over all other values.
		$this->assertSame(
			expected: 'Name: Injected',
			actual: $this->resolver
				->resolve( cb: $withGetMethod, default: null, params: array( 'name' => 'Injected' ) ),
		);

		// Binding will take precedence over everything else.
		$this->assertSame(
			expected: 'Name: Binding',
			actual: $this->resolver->resolve( cb: $withGetMethod, default: 'ignored', params: array( 'ignored' ) ),
			message: 'Even though cb string is passed with ::get() method, binding value is '
							. ' resolved by directly invoking class (has __invoke() method).',
		);
	}
}
