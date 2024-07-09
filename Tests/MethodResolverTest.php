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

	/** @dataProvider provideVariousBindings */
	public function testWithMethodBinding(
		Closure $binding,
		string $method,
		bool $expected,
		bool ...$args
	): void {
		$test = new Binding( $binding, ...$args );
		$id   = Binding::class . '@' . spl_object_id( $test ) . "::{$method}";

		$this->app->expects( $this->once() )->method( 'hasBinding' )->with( $id )->willReturn( true );
		$this->app->expects( $this->once() )->method( 'getBinding' )->with( $id )->willReturn( $test );

		$this->assertSame(
			expected: $expected,
			actual: $this->resolver->resolve( array( $test, $method ), default: null )
		);
	}

	public function provideVariousBindings(): array {
		return array(
			array( static fn( $test ) => $test->isInstance(), 'isInstance', false, true, false ),
			array( static fn( $test ) => $test->isSingleton(), 'isSingleton', true, true, false ),
		);
	}

	public function testInstantiatedClassPassedToCbValueAsStringThrowsException(): void {
		$test           = new Binding( 'test', instance: true );
		$instantiatedId = Binding::class . '@' . spl_object_id( $test ) . '::isInstance';

		$this->app->expects( $this->once() )
			->method( 'getEntryFrom' )
			->with( Binding::class )
			->willReturn( Binding::class );

		$this->expectException( BadResolverArgument::class );
		$this->expectExceptionMessage(
			message: 'Cannot resolve instantiated class method "' . Binding::class . '::isInstance()"'
		);

		$this->resolver->resolve( $instantiatedId, default: null );
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
			public function __invoke( string $prefix = 'Beautiful' ): string {
				return "{$prefix} world!";
			}

			public function alt( string $prefix ): string {
				return "{$prefix} alternate world!";
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
			expected: 'Namaste alternate world!'
		);

		$this->assertSame(
			actual: $this->resolver->resolve( $test, default: null ),
			expected: 'Beautiful world!'
		);

		$this->assertSame(
			actual: $this->resolver->resolve( $test, default: null, params: array( 'prefix' => 'Amazing' ) ),
			expected: 'Amazing world!'
		);

		$this->assertSame(
			message: 'The "$default" method name does not matter if "$cb" is an object instance.',
			actual: $this->resolver->resolve( cb: $test, default: 'alt' ),
			expected: 'Beautiful world!'
		);

		$this->assertSame( expected: array( $test, '__invoke' ), actual: $this->resolver->unwrappedCallback() );
	}

	public function testMethodResolverCbSetterGetter(): void {
		$test = new class() {
			public function alt( string $prefix ): string {
				return "{$prefix} alternate world!";
			}
		};

		$this->assertNull( $this->resolver->unwrappedCallback() );

		$this->resolver->with( classOrInstance: $test, method: 'alt' );

		$this->assertSame( expected: array( $test, 'alt' ), actual: $this->resolver->unwrappedCallback() );
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

	/** @dataProvider provideVariousArtefactGetterData */
	public function testArtefactAndInstantiatedClass( callable|string $from, ?int $objectId ): void {
		$expected = null === $objectId
			? self::class
			: self::class . "@{$objectId}::provideVariousArtefactGetterData";

		$this->assertSame( $expected, actual: MethodResolver::getArtefact( $from ) );
	}

	/** @return mixed[] */
	public function provideVariousArtefactGetterData(): array {
		$objectId = spl_object_id( $this );

		return array(
			array( array( $this, __FUNCTION__ ), $objectId ),
			array( $this->provideVariousArtefactGetterData( ... ), $objectId ),
			array( self::class . '::' . __FUNCTION__, null ),
		);
	}
}
