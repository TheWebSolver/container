<?php
/**
 * Stack test for accessing as Array.
 *
 * @package TheWebSolver\Codegarage\Test
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;

class StackWithArrayAccessTest extends TestCase {
	private Stack $stack;

	protected function setUp(): void {
		$this->stack = new Stack();
	}

	protected function tearDown(): void {
		$this->setUp();
	}

	public function testWithArrayAccess(): void {
		foreach ( array( 'one', 'two', 'three', 'four' ) as $index => $value ) {
			$this->stack->set( "i:$index", $value );
		}

		$this->stack['i:5'] = 'Intel';

		$this->assertTrue( isset( $this->stack['i:5'] ) );
		$this->assertSame( 'Intel', $this->stack['i:5'] );

		unset( $this->stack['i:1'] );

		$this->assertArrayNotHasKey( 'i:1', $this->stack );
	}

	public function testWithBindingDTO(): void {
		$concrete = new class() {};

		$singleton                = new Binding( $concrete, singleton: true );
		$this->stack['singleton'] = $singleton;

		$this->assertTrue( condition: $this->stack['singleton']->isSingleton() );

		$this->assertTrue( condition: isset( $this->stack['singleton'] ) );
		$this->assertFalse( condition: isset( $this->stack['instance'] ) );

		$instance                = new Binding( static fn() => 1, instance: true );
		$this->stack['instance'] = $instance;

		$this->assertSame(
			actual: $this->stack->getItems(),
			expected: array(
				'singleton' => $singleton,
				'instance'  => $instance,
			),
		);
	}
}
