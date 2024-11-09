<?php
/**
 * Artefact test.
 *
 * @package TheWebSolver\Codegarage\Test
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Lib\Container\Pool\Artefact;

class ArtefactTest extends TestCase {
	private ?Artefact $contextPool;

	protected function setUp(): void {
		$this->contextPool = new Artefact();
	}

	protected function tearDown(): void {
		$this->contextPool = null;
	}

	public function testContextPool(): void {
		$this->contextPool->push( value: 'needsWhenBuilding' );
		$this->contextPool->push( value: 'theLastData' );

		$this->assertTrue( $this->contextPool->hasItems() );

		$this->assertSame(
			expected: array( 'needsWhenBuilding', 'theLastData' ),
			actual: $this->contextPool->getItems()
		);

		$this->assertTrue( $this->contextPool->has( value: 'theLastData' ) );
		$this->assertFalse( $this->contextPool->has( value: 'dataNotPushedYet' ) );

		$this->assertSame(
			expected: 'needsWhenBuilding, theLastData',
			actual: (string) $this->contextPool
		);

		$this->assertSame( expected: 'theLastData', actual: $this->contextPool->latest() );

		$this->contextPool->pull();

		$this->assertSame( expected: 'needsWhenBuilding', actual: $this->contextPool->latest() );
		$this->assertSame( expected: 'needsWhenBuilding', actual: $this->contextPool->pull() );
		$this->assertSame( expected: null, actual: $this->contextPool->latest() );
	}
}
