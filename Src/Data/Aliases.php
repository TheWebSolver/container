<?php
/**
 * The aliases for container entry.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Data;

use LogicException;

class Aliases {
	/** @var array<string,string> */
	private array $stack = array();

	/** @var array<string,string[]> */
	private array $entryStack = array();

	/** @throws LogicException When entry ID and alias is same. */
	public function add( string $entry, string $alias ): void {
		if ( $alias === $entry ) {
			throw new LogicException( "[{$entry}] cannot be aliased by same name." );
		}

		$this->stack[ $alias ]        = $entry;
		$this->entryStack[ $entry ][] = $alias;
	}

	public function exists( string $id, bool $asEntry = false ): bool {
		return $asEntry ? ! empty( $this->entryStack[ $id ] ) : isset( $this->stack[ $id ] );
	}

	/**
	 * @return string|string[]
	 * @phpstan-return ($asEntry is true ? string[] : string)
	 */
	public function get( string $id, bool $asEntry = false ): string|array {
		return $asEntry ? ( $this->entryStack[ $id ] ?? array() ) : ( $this->stack[ $id ] ?? $id );
	}

	public function remove( string $id ): void {
		$this->removeEntryAlias( $id );

		unset( $this->stack[ $id ] );
	}

	public function flush(): void {
		$this->stack      = array();
		$this->entryStack = array();
	}

	private function removeEntryAlias( string $id ): void {
		if ( ! $this->exists( $id ) ) {
			return;
		}

		foreach ( $this->entryStack as $entry => $aliases ) {
			$this->removeFromEntryStack( $aliases, $entry, given: $id );
		}
	}

	/** @param string[] $aliases */
	private function removeFromEntryStack( array $aliases, string $entry, string $given ): void {
		foreach ( $aliases as $index => $storedAlias ) {
			if ( $storedAlias !== $given ) {
				continue;
			}

			unset( $this->entryStack[ $entry ][ $index ] );
		}
	}
}
