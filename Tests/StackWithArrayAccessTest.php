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
		$concrete = new class() {};

		$singleton                = new Binding( concrete: $concrete, singleton: true );
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

	public function testAsCollection(): void {
		$this->stack->asCollection();

		$this->stack->set( key: 'center', value: '1' );
		$this->stack->set( key: 'center', value: '2' );
		$this->stack->set( key: 'centre', value: '3' );

		$this->assertSame( expected: array( '1', '2' ), actual: $this->stack['center'] );
		$this->assertSame( expected: array( '3' ), actual: $this->stack['centre'] );
		$this->assertCount( expectedCount: 2, haystack: $this->stack->withKey( 'center' ) );
		$this->assertCount( expectedCount: 1, haystack: $this->stack->withKey( 'centre' ) );
	}

	public function testWithNestedKeyedValue(): void {
		$this->stack->asCollection();

		$key = Stack::keyFrom( id: 'key', name: 'john' );

		$this->assertSame( expected: 'key||john', actual: $key );

		$this->stack[ $key ] = array( 'doe' );

		$this->assertSame( expected: array( 'john' => array( 'doe' ) ), actual: $this->stack['key'] );
		$this->assertSame( expected: array( 'john' => array( 'doe' ) ), actual: $this->stack->get( 'key' ) );

		$this->assertSame( expected: array( 'doe' ), actual: $this->stack->get( item: 'key||john' ) );
		$this->assertSame( expected: array( 'doe' ), actual: $this->stack['key||john'] );

		$this->assertSame( expected: 'doe', actual: $this->stack['key']['john'][0] );

		$this->assertCount( expectedCount: 1, haystack: $this->stack );
		$this->assertCount( expectedCount: 1, haystack: $this->stack->withKey( 'key||john' ) );
	}

	public function testGetterSetterWithKey(): void {
		$this->stack->set( 'primary||first', 'value' );

		$this->assertTrue( $this->stack->has( 'primary||first' ) );

		$this->stack->set( key: 'primary||third', value: 'next value' );
		$this->stack->set( key: 'primary||second', value: 'another' );

		$this->assertSame(
			actual: $this->stack->get( item: 'primary' ),
			expected: array(
				'first'  => 'value',
				'third'  => 'next value',
				'second' => 'another',
			)
		);

		$this->assertSame( expected: 'next value', actual: $this->stack->get( 'primary||third' ) );

		$this->assertTrue( condition: $this->stack->remove( 'primary||third' ) );
		$this->assertArrayNotHasKey( key: 'third', array: $this->stack->get( 'primary' ) );
		$this->assertCount( expectedCount: 2, haystack: $this->stack->withKey( 'primary' ) );

		$this->stack->set( key: 'primary:second', value: 'update second' );
		$this->assertSame(
			actual: $this->stack->get( item: 'primary:second' ),
			expected: 'update second'
		);
	}

	public function testGetterSetterWithIndex(): void {
		$this->stack->asCollection();

		$this->stack->set( key: 'primary', value: 'value' );

		$this->assertTrue( condition: $this->stack->has( 'primary' ) );

		$this->stack->set( key: 'primary||2', value: 'next value' );
		$this->stack->set( key: 'primary||1', value: 'another' );
		$this->stack->set( key: 'primary', value: 'again without index in key' );

		$this->assertSame(
			actual: $this->stack->get( item: 'primary' ),
			expected: array(
				0 => 'value',
				2 => 'next value',
				1 => 'another',
				3 => 'again without index in key',
			)
		);

		$this->assertSame( expected: 'next value', actual: $this->stack->get( 'primary||2' ) );

		$this->assertTrue( condition: $this->stack->remove( 'primary||0' ) );
		$this->assertArrayNotHasKey( key: 0, array: $this->stack->get( 'primary' ) );
		$this->assertCount( expectedCount: 3, haystack: $this->stack->withKey( 'primary' ) );

		$this->stack->set( key: 'primary||1', value: 'update 1' );
		$this->assertSame( expected: 'update 1', actual: $this->stack->get( item: 'primary||1' ) );
	}

	public function testGetterSetterWithIndexWithoutDeclaringAsCollection(): void {
		$this->stack->set( key: 'primary||0', value: 'value' );
		$this->stack->set( key: 'primary||1', value: 'another' );

		$this->assertTrue( condition: $this->stack->has( key: 'primary||1' ) );
		$this->assertSame(
			actual: $this->stack->get( item: 'primary' ),
			expected: array( 'value', 'another' )
		);
	}
}
