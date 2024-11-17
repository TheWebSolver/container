<?php
/**
 * Stack test for accessing as Array.
 *
 * @package TheWebSolver\Codegarage\Test
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Tests;

use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;

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
}
