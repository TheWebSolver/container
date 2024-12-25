<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Tests;

use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Container\Pool\CollectionStack;

class CollectionStackTest extends TestCase {
	private CollectionStack $stack;

	protected function setUp(): void {
		$this->stack = new CollectionStack();
	}

	protected function tearDown(): void {
		$this->setUp();
	}

	public function testCollectionStackWithNumericAutoIndex(): void {
		foreach ( array( 'first', 'second', 'third' ) as $value ) {
			$this->stack->set( 'key', $value );
		}

		$this->assertCount( 1, $this->stack );
		$this->assertCount( 1, $this->stack );
		$this->assertCount( 3, $this->stack->countFor( 'key' ) );
		$this->assertSame( 'second', $this->stack->get( 'key', 1 ) );
	}

	public function testCollectionStackWithNumericAndStringIndex(): void {
		$this->stack->set( 'name', 'john', 'one' );
		$this->stack->set( 'name', 'doe' );
		$this->stack->set( 'name', 'Lorem' );
		$this->stack->set( 'name', 'Ipsum', 'what' );
		$this->stack->set( 'name', 'whatever' );
		$this->stack->set( 'name', 'withNumeric' );
		$this->stack->set( 'name', 'withString', 'final' );

		$this->assertFalse( $this->stack->has( 'undefined' ) );

		$expectation = array(
			'one'   => 'john',
			0       => 'doe',
			1       => 'Lorem',
			'what'  => 'Ipsum',
			2       => 'whatever',
			3       => 'withNumeric',
			'final' => 'withString',
		);

		$this->assertSame(
			expected: array_keys( $expectation ),
			actual: array_keys( $this->stack->get( 'name' ) ?? array() )
		);

		foreach ( $expectation as $index => $expectedValue ) {
			$this->assertTrue( $this->stack->has( 'name', $index ) );
			$this->assertSame( $expectedValue, $this->stack->get( 'name', $index ) );
		}

		$this->assertCount( 7, $this->stack->countFor( 'name' ) );

		$this->assertTrue( $this->stack->remove( 'name', 'what' ) );
		$this->assertArrayNotHasKey( 'what', $this->stack->get( 'name' ) ?? array() );
		$this->assertFalse( $this->stack->remove( 'name', 'what' ) );

		$this->stack->set( 'job', 'developer' );

		$this->assertCount( 2, $this->stack );

		$this->stack->reset( 'job' );

		$this->assertIsArray( $this->stack->get( 'job' ) );
		$this->assertEmpty( $this->stack->get( 'job' ) );
		$this->assertTrue( $this->stack->remove( 'job' ) );
		$this->assertFalse( $this->stack->remove( 'job' ) );

		$this->assertCount( 1, $this->stack );

		$this->stack->reset();

		$this->assertCount( 0, $this->stack );
		$this->assertCount( 0, $this->stack->countFor( 'name' ) );
	}

	public function testCollectionStackWithCompiledArray(): void {
		$compiledData = array(
			'name'   => array(
				'writer'    => 'Lorem Ipsum',
				'developer' => 'John Doe',
			),
			'flower' => array(
				'land'  => 'lily',
				'water' => 'lotus',
			),
		);

		$stack = CollectionStack::fromCompiledArray( $compiledData );

		$this->assertTrue( $stack->has( 'name', 'developer' ) );
		$this->assertSame( 'lily', $stack->get( 'flower', 'land' ) );
	}

	public function testCollectionStackWithCompiledFile(): void {
		$stack = CollectionStack::fromCompiledFile( path: __DIR__ . '/File/compiledForCollectionStack.php' );

		$this->assertTrue( $stack->has( 'name', 'developer' ) );
		$this->assertSame( 'lily', $stack->get( 'flower', 'land' ) );
	}
}
