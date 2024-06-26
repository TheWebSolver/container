<?php
/**
 * The aliases for container entry.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Data;

use LogicException;
use TheWebSolver\Codegarage\Lib\Container\Traits\KeyStack;

class Aliases {
	use KeyStack { KeyStack::remove as remover; }

	/** @var array<string,string[]> */
	private array $entryStack = array();

	/** @throws LogicException When entry ID and alias is same. */
	public function set( string $entry, string $alias ): void {
		if ( $alias === $entry ) {
			throw new LogicException( "[{$entry}] cannot be aliased by same name." );
		}

		$this->stack[ $alias ]        = $entry;
		$this->entryStack[ $entry ][] = $alias;
	}

	public function has( string $id, bool $asEntry = false ): bool {
		return $asEntry ? ! empty( $this->entryStack[ $id ] ) : isset( $this->stack[ $id ] );
	}

	/**
	 * @return string|string[]
	 * @phpstan-return ($asEntry is true ? string[] : string)
	 */
	public function get( string $id, bool $asEntry = false ): string|array {
		return $asEntry ? ( $this->entryStack[ $id ] ?? array() ) : ( $this->stack[ $id ] ?? $id );
	}

	public function remove( string $id ): bool {
		$this->removeEntryAlias( $id );

		return $this->remover( $id );
	}

	public function flush(): void {
		$this->stack      = array();
		$this->entryStack = array();
	}

	private function removeEntryAlias( string $id ): void {
		if ( ! $this->has( $id ) ) {
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
