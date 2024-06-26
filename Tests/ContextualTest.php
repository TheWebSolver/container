<?php
/**
 * Contextual test.
 *
 * @package TheWebSolver\Codegarage\Test
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Lib\Container\Pool\Contextual;

class ContextualTest extends TestCase {
	public function testContextualBindingData(): void {
		$contextual = new Contextual();
		$closure    = $this->testContextualBindingData( ... );

		$contextual->set( self::class, key: 'dependency1', implementation: $closure );
		$contextual->set( self::class, key: 'dependency2', implementation: TestCase::class );
		$contextual->get( self::class, 'dependency1' );

		$this->assertTrue( $contextual->has( self::class ) );

		$this->assertSame(
			actual: $contextual->getItems(),
			expected: array(
				self::class => array(
					'dependency1' => $closure,
					'dependency2' => TestCase::class,
				),
			),
		);

		$this->assertSame( $closure, actual: $contextual->get( self::class, 'dependency1' ) );
		$this->assertSame( TestCase::class, actual: $contextual->get( self::class, 'dependency2' ) );
	}
}
