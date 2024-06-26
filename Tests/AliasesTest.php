<?php
/**
 * Entry aliasing test.
 *
 * @package TheWebSolver\Codegarage\Test
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Lib\Container\Data\Aliases;

class AliasesTest extends TestCase {
	private ?Aliases $aliases;

	/** @var array<string,string> */
	private array $entryAliasMap = array(
		'entry1'    => 'alias1',
		'entry2'    => 'alias2',
		'entry3'    => 'alias3',
		self::class => 'testingAliases',
	);

	protected function setUp(): void {
		$this->aliases = new Aliases();
	}

	protected function tearDown(): void {
		$this->aliases = null;
	}

	public function testAddingAlias(): Aliases {
		foreach ( $this->entryAliasMap as $entry => $alias ) {
			$this->aliases->set( $entry, $alias );

			$this->assertSame( $entry, $this->aliases->get( $alias ) );
			$this->assertSame( $alias, $this->aliases->get( $entry, asEntry: true )[0] );
		}

		return $this->aliases;
	}

	/**
	 * @return array{0:int,1:Aliases}
	 * @depends testAddingAlias
	 */
	public function testRemovingAliases( Aliases $aliases ): array {
		$aliases->remove( 'alias1' );

		$this->assertFalse( $aliases->has( 'alias1' ) );
		$this->assertFalse( $aliases->has( 'entry1', asEntry: true ) );

		return array( 1, $aliases );
	}

	/**
	 * @param array{0:int,1:Aliases} $data
	 * @depends testRemovingAliases
	 */
	public function testFlushingAliases( array $data ): void {
		[ $noOfTestToSkip, $aliases ] = $data;

		$existing = array_splice( $this->entryAliasMap, $noOfTestToSkip );

		foreach ( $existing as $entry => $alias ) {
			$this->assertTrue( $aliases->has( $alias ) );
			$this->assertTrue( $aliases->has( $entry, asEntry: true ) );
		}

		$aliases->flush();

		foreach ( $existing as $entry => $alias ) {
			$this->assertFalse( $aliases->has( $alias ) );
			$this->assertFalse( $aliases->has( $entry, asEntry: true ) );
		}
	}

	public function testSameEntryAndAliasThrowsException(): void {
		$this->expectException( LogicException::class );

		$this->aliases->set( entry: self::class, alias: self::class );
	}
}
