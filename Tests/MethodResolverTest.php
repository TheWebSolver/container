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
use TheWebSolver\Codegarage\Lib\Container\Helper\Event;
use TheWebSolver\Codegarage\Lib\Container\Helper\MethodResolver;
use TheWebSolver\Codegarage\Lib\Container\Error\BadResolverArgument;

class MethodResolverTest extends TestCase {
	private Container|MockObject|null $app;
	private Event|MockObject|null $event;
	private ?MethodResolver $resolver;

	protected function setUp(): void {
		$this->app      = $this->createMock( Container::class );
		$this->event    = $this->createMock( Event::class );
		$this->resolver = new MethodResolver( $this->app, $this->event );
	}

	protected function tearDown(): void {
		$this->app      = null;
		$this->event    = null;
		$this->resolver = null;
	}

	public function testWithMethodBinding(): void {
		$test = new Binding( 'test', instance: false );

		// The return value of Unwrap::asString(object: $test, methodName: 'isInstance').
		$entry = Binding::class . '.' . spl_object_id( $test ) . '::isInstance';

		$this->resolver->bind(
			id: $test->isInstance( ... ),
			cb: static function ( $resolvedObject, $app ) {
				self::assertInstanceOf( expected: Binding::class, actual: $resolvedObject );
				self::assertInstanceOf( expected: Container::class, actual: $app );

				return $resolvedObject->isInstance();
			}
		);

		$this->assertTrue( condition: $this->resolver->hasBinding( $entry ) );
		$this->assertTrue( $this->resolver->hasBinding( id: $test->isInstance( ... ) ) );
		$this->assertFalse( condition: $this->resolver->fromBinding( $entry, $test, $this->app ) );
		$this->assertFalse(
			condition: $this->resolver->resolve( cb: array( $test, 'isInstance' ), default: null )
		);

		$this->resolver->bind(
			$entry = Binding::class . '::isSingleton',
			cb: static fn ( $resolvedObject ) => $resolvedObject->isSingleton()
		);

		$this->app->expects( $this->exactly( 2 ) )
			->method( 'get' )
			->with( Binding::class )
			->willReturn( $bound = new Binding( 'test', singleton: true ), Binding::class );

		$this->app->expects( $this->once() )
			->method( 'getEntryFrom' )
			->with( Binding::class )
			->willReturn( 'app.binder' );

		$this->assertTrue( $this->resolver->hasBinding( Binding::class . '::isSingleton' ) );
		$this->assertTrue( $this->resolver->fromBinding( $entry, $bound ) );
		$this->assertTrue( $this->resolver->resolve( $entry, default: null ) );
		$this->expectException( BadResolverArgument::class );
		$this->expectExceptionMessageMatches( '/Unable to instantiate entry: app.binder./' );

		$this->resolver->resolve( $entry, default: null );
	}

	public function testInstantiatedClassPassedAsCallbackAsStringThrowsException(): void {
		$test = new Binding( 'test', instance: true );

		$this->app->expects( $this->once() )->method( 'getEntryFrom' )->with( Binding::class );

		$this->assertFalse( $this->resolver->hasBinding( $test->isSingleton( ... ) ) );

		// The return value of Unwrap::asString(object: $test, methodName: 'isInstance').
		$callbackAsString = Binding::class . '.' . spl_object_id( $test ) . '::isInstance';
		$callbackAsArray  = array( $test, 'isInstance' );

		$this->assertFalse( $this->resolver->hasBinding( $callbackAsString ) );
		$this->assertTrue( $this->resolver->resolve( $callbackAsArray, default: null ) );

		$this->expectException( BadResolverArgument::class );

		$this->resolver->resolve( $callbackAsString, default: null );
	}

	/** @dataProvider provideInvalidClassMethod */
	public function testInvalidClassOrMethodThrowsException( string $cls, ?string $method ): void {
		$msg                = "/Unable to find method for class: {$cls}./";
		$validNonResolvable = str_contains( haystack: $cls, needle: '::' );

		if ( $method || $validNonResolvable ) {
			$pre = $validNonResolvable ? explode( '::', $cls, 2 )[0] : $cls;

			foreach ( array( 'get', 'getEntryFrom' ) as $name ) {
				$this->app->expects( $this->once() )->method( $name )->with( $pre )->willReturn( $pre );
			}

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

	public function testResolvingVariousMethodCalls(): void {
		$test = new class() {
			public function __invoke( string $prefix ): string {
				return "{$prefix} world!";
			}

			public function alt( string $prefix ): string {
				return "{$prefix} world!";
			}
		};

		$this->app->expects( $this->exactly( 2 ) )
			->method( 'get' )
			->with( $test::class )
			->willReturn( new $test() );

		$this->assertSame(
			actual: $this->resolver->resolve( $test::class, default: null, params: array( 'prefix' => 'Hello' ) ),
			expected: 'Hello world!'
		);

		$this->assertSame(
			actual: $this->resolver->resolve( $test::class, default: 'alt', params: array( 'prefix' => 'Namaste' ) ),
			expected: 'Namaste world!'
		);
	}

	public function testLazyClassInstantiationAndMethodCallWithParamResolver(): void {
		$test = new class() {
			public function test( int $base = 3 ): int {
				return $base + 2;
			}
		};

		$cb = $test::class . '::test';

		$this->app->expects( $this->exactly( 4 ) )
			->method( 'get' )
			->with( $test::class )
			->willReturn( new $test() );

		// If no contextual, eventual or injected value, default value used.
		$this->assertSame( expected: 5, actual: $this->resolver->resolve( $cb, default: null ) );

		// Contextual value will take precedence over default value.
		$this->app->expects( $this->exactly( 1 ) )
			->method( 'getContextualFor' )
			->with( '$base' )
			->willReturn( static fn() => 13 );

		// Eventual value will take precedence over contextual & default value.
		$this->event->expects( $this->exactly( 2 ) )
			->method( 'fireDuringBuild' )
			->with( 'int', 'base' )
			->willReturn( new Binding( concrete: 8 ), null );

		$this->assertSame( expected: 10, actual: $this->resolver->resolve( $cb, default: null ) );
		$this->assertSame( expected: 15, actual: $this->resolver->resolve( $cb, default: null ) );

		// Injected value will take precedence over all other values.
		$this->assertSame(
			actual: $this->resolver->resolve( $cb, default: null, params: array( 'base' => 18 ) ),
			expected: 20
		);
	}
}
