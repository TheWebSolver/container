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
use TheWebSolver\Codegarage\Lib\Container\Data\MethodBinding;

class StackWithArrayAccessTest extends TestCase {
	/** @var null|Stack&ArrayAccess<string,Binding|MethodBinding> */
	private ?Stack $stack;

	protected function setUp(): void {
		$this->stack = new Stack();
	}

	protected function tearDown(): void {
		$this->stack = null;
	}

	public function testWithBindingDTO(): void {
		$concrete = new class() { public function __invoke() {
				return 'expected';
		} };

		$singleton                = new Binding( concrete: $concrete, singleton: true );
		$this->stack['singleton'] = $singleton;

		$this->assertTrue( condition: $this->stack['singleton']->isSingleton() );
		$this->assertSame( expected: $concrete, actual: $this->stack['singleton']->concrete );
		$this->assertSame( 'expected', ( $this->stack['singleton']->concrete )() );

		$this->assertTrue( condition: isset( $this->stack['singleton'] ) );
		$this->assertFalse( condition: isset( $this->stack['instance'] ) );

		$instance                = new Binding( static fn() => 1, instance: true );
		$this->stack['instance'] = $instance;

		$this->assertTrue( condition: $this->stack['instance']->isInstance() );
		$this->assertSame(
			actual: $this->stack->getItems(),
			expected: array(
				'singleton' => $singleton,
				'instance'  => $instance,
			),
		);
	}
}
