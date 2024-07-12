<?php
/**
 * Index Stack test.
 *
 * @package TheWebSolver\Codegarage\Test
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Lib\Container\Pool\IndexStack;

class IndexStackTest extends TestCase {
	private ?IndexStack $stack;

	protected function setUp(): void {
		$this->stack = new IndexStack();
	}

	protected function tearDown(): void {
		$this->stack = null;
	}

	public function testSetterGetter(): void {
		$values = array(
			'one',
			array(
				'one' => 1,
				'two' => 2,
			),
			'last',
		);

		foreach ( $values as $value ) {
			$this->stack->set( $value );
		}

		$this->assertTrue( condition: $this->stack->hasItems() );
		$this->assertSame( expected: $values, actual: $this->stack->getItems() );

		$this->stack->restackWith( newValue: array( 'newArrayMerged' ), mergeArray: true, shift: false );

		$this->assertSame( expected: 'newArrayMerged', actual: $this->stack->getItems()[3] );

		$this->stack->restackWith( newValue: array( 'newArrayMerged' ), mergeArray: true, shift: true );

		$this->assertSame( expected: 'newArrayMerged', actual: $this->stack->getItems()[0] );

		$this->stack->restackWith( newValue: 'newStringMerged', mergeArray: true, shift: false );

		$this->assertSame( expected: 'newStringMerged', actual: $this->stack->getItems()[5] );

		$this->stack->restackWith( newValue: 'newStringMerged', mergeArray: true, shift: true );

		$this->assertSame( expected: 'newStringMerged', actual: $this->stack->getItems()[0] );

		$this->stack->restackWith( newValue: array( 'newArrayNotMerged' ), mergeArray: false, shift: false );

		$this->assertSame( expected: array( 'newArrayNotMerged' ), actual: $this->stack->getItems()[7] );

		$this->stack->restackWith( newValue: array( 'newArrayNotMerged' ), mergeArray: false, shift: true );

		$this->assertSame( expected: array( 'newArrayNotMerged' ), actual: $this->stack->getItems()[0] );

		$this->stack->restackWith( newValue: 'newStringNotMerged', mergeArray: false, shift: false );

		$this->assertSame( expected: 'newStringNotMerged', actual: $this->stack->getItems()[9] );

		$this->stack->restackWith( newValue: 'newStringNotMerged', mergeArray: false, shift: true );

		$this->assertSame( expected: 'newStringNotMerged', actual: $this->stack->getItems()[0] );

		$this->assertCount( expectedCount: 11, haystack: $this->stack->getItems() );
	}
}
