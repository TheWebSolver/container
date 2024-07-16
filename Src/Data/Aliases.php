<?php
/**
 * Aliases for the container entry.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Data;

use LogicException;
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;
use TheWebSolver\Codegarage\Lib\Container\Traits\KeyStack;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\Resettable;

class Aliases implements Resettable {
	use KeyStack {
		KeyStack::remove as remover;
	}

	// phpcs:ignore Squiz.Commenting.FunctionComment.ParamNameNoMatch, Squiz.Commenting.FunctionComment.SpacingAfterParamType
	/** @param Stack&ArrayAccess<string,array<int,string>> $entryStack */
	public function __construct( private readonly Stack $entryStack = new Stack() ) {
		$this->entryStack->asCollection();
	}

	/** @throws LogicException When entry ID and alias is same. */
	public function set( string $entry, string $alias ): void {
		if ( $alias === $entry ) {
			throw new LogicException( "[{$entry}] cannot be aliased by same name." );
		}

		$this->stack[ $alias ] = $entry;

		$this->entryStack->set( key: $entry, value: $alias );
	}

	public function has( string $id, bool $asEntry = false ): bool {
		return $asEntry ? $this->entryStack->has( key: $id ) : isset( $this->stack[ $id ] );
	}

	/**
	 * @return string|array<int,string>
	 * @phpstan-return ($asEntry is true ? array<int,string> : string)
	 */
	public function get( string $id, bool $asEntry = false ): string|array {
		return $asEntry ? ( $this->entryStack[ $id ] ?? array() ) : ( $this->stack[ $id ] ?? $id );
	}

	public function remove( string $id ): bool {
		$this->removeEntryAlias( $id );

		return $this->remover( $id );
	}

	public function reset(): void {
		$this->stack = array();

		$this->entryStack->reset();
	}

	private function removeEntryAlias( string $id ): void {
		if ( ! $this->has( $id ) ) {
			return;
		}

		foreach ( $this->entryStack->getItems() as $entry => $aliases ) {
			$this->removeFromEntryStack( $aliases, $entry, given: $id );
		}
	}

	/** @param string[] $aliases */
	private function removeFromEntryStack( array $aliases, string $entry, string $given ): void {
		foreach ( $aliases as $index => $storedAlias ) {
			if ( $storedAlias === $given ) {
				$this->entryStack->remove( Stack::keyFrom( $entry, (string) $index ) );
			}
		}
	}
}
