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

		$resolver->define(
			abstract: $test->isInstance( ... ),
			callback: static function ( $binding, $methodResolver ) {
				self::assertInstanceOf( expected: Binding::class, actual: $binding );
				self::assertInstanceOf( expected: MethodResolver::class, actual: $methodResolver );

				return $binding->isInstance();
			}
		);

		$key = Binding::class . '.' . spl_object_id( $test ) . '::isInstance';

		$this->assertTrue( condition: $resolver->hasBinding( $key ) );
		$this->assertFalse( condition: $resolver->fromInstanceMethod( $key, $test ) );
		$this->assertFalse(
			condition: $resolver->resolve(
				app: $this->createMock( Container::class ),
				callback: array( $test, 'isInstance' )
			)
		);
	}
}
