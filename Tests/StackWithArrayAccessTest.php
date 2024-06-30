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

	public function testAsCollection(): void {
		$this->stack->asCollection();

		$this->stack->set( key: 'center', value: '1' );
		$this->stack->set( key: 'center', value: '2' );
		$this->stack->set( key: 'centre', value: '3' );

		$this->assertSame( expected: array( '1', '2' ), actual: $this->stack['center'] );
		$this->assertSame( expected: array( '3' ), actual: $this->stack['centre'] );
		$this->assertSame( 2, $this->stack->count( 'center' ) );
		$this->assertSame( 1, $this->stack->count( 'centre' ) );
	}

	public function testWithNestedKeyedValue(): void {
		$this->stack->asCollection();

		$key                 = Stack::keyFrom( id: 'key', name: 'john' );
		$this->stack[ $key ] = 'doe';

		$this->assertSame( expected: array( 'john' => 'doe' ), actual: $this->stack->get( 'key' ) );
		$this->assertSame( expected: 'doe', actual: $this->stack->get( item: $key ) );
		$this->assertSame( expected: array( 'john' => 'doe' ), actual: $this->stack['key'] );
		$this->assertSame( expected: 'doe', actual: $this->stack['key']['john'] );
		$this->assertSame( expected: 'doe', actual: $this->stack[ $key ] );

		$this->assertSame( 1, $this->stack->count( 'key' ) );
		$this->assertSame( 0, $this->stack->count( $key ) );
	}
}
