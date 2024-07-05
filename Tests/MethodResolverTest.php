<?php
/**
 * Method Resolver test.
 *
 * @package TheWebSolver\Codegarage\Test
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Helper\MethodResolver;

class MethodResolverTest extends TestCase {
	public function testDefineWithStackIntegration(): void {
		$resolver = new MethodResolver( new Stack() );
		$test     = new Binding( 'test' );

		$resolver->bind(
			abstract: $test->isInstance( ... ),
			callback: static function ( $binding, $methodResolver ) {
				self::assertInstanceOf( expected: Binding::class, actual: $binding );
				self::assertInstanceOf( expected: MethodResolver::class, actual: $methodResolver );

				return $binding->isInstance();
			}
		);

		$key = Binding::class . '.' . spl_object_id( $test ) . '::isInstance';

		$this->assertTrue( condition: $resolver->hasBinding( $key ) );
		$this->assertFalse( condition: $resolver->fromBinding( $key, $test ) );
		$this->assertFalse(
			condition: $resolver->resolve(
				app: $this->createMock( Container::class ),
				callback: array( $test, 'isInstance' )
			)
		);
	}

	public function testLazyMethodCall(): void {
		$resolver = new MethodResolver( new Stack() );
		$app      = $this->createMock( Container::class );
		$test     = new class() {
			public function test( int $base = 3 ): int {
				return $base + 2;
			}
		};

		$app->expects( $this->once() )
			->method( 'get' )
			->with( $test::class )
			->willReturn( new $test() );

		$this->assertSame(
			actual: $resolver->resolve( $app, callback: $test::class . '::test' ),
			expected: 5
		);
	}
}
