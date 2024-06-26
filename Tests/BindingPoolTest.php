<?php
/**
 * Context Pool test.
 *
 * @package TheWebSolver\Codegarage\Test
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Pool\Bind;

class BindingPoolTest extends TestCase {
	private ?Bind $bindingPool;

	protected function setUp(): void {
		$this->bindingPool = new Bind();
	}

	protected function tearDown(): void {
		$this->bindingPool = null;
	}

	public function testBindingPool(): void {
		$concrete = fn() => false;
		$binding  = new Binding( concrete: $concrete, singleton: true );

		$this->bindingPool->set( key: 'bindingShared', value: $binding );

		$this->assertTrue( $this->bindingPool->hasItems() );

		$this->assertTrue( $this->bindingPool->get( key: 'bindingShared' )->isSingleton() );
		$this->assertSame(
			expected: $concrete,
			actual: $this->bindingPool->get( key: 'bindingShared' )->concrete
		);

		$this->assertTrue( $this->bindingPool->has( key: 'bindingShared' ) );
		$this->assertFalse( $this->bindingPool->has( key: 'custom' ) );

		$this->bindingPool->set(
			key: 'custom',
			value: $custom = new Binding( fn() => 1, singleton: false, instance: true )
		);

		$this->assertTrue( $this->bindingPool->get( key: 'custom' )->isInstance() );

		$this->assertSame(
			actual: $this->bindingPool->getItems(),
			expected: array(
				'bindingShared' => $binding,
				'custom'        => $custom,
			),
		);

		$this->assertTrue( $this->bindingPool->remove( key: 'bindingShared' ) );

		$this->bindingPool->flush();

		$this->assertEmpty( $this->bindingPool->getItems() );
	}
}
