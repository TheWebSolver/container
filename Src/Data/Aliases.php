<?php
/**
 * Aliases for the container entry.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Data;

use LogicException;
use TheWebSolver\Codegarage\Lib\Container\Traits\KeyStack;
use TheWebSolver\Codegarage\Lib\Container\Pool\CollectionStack;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\Resettable;

class Aliases implements Resettable {
	/** @use KeyStack<string> */
	use KeyStack {
		KeyStack::remove as remover;
	}

	/** @param CollectionStack<string> $entryStack */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function __construct( private readonly CollectionStack $entryStack = new CollectionStack() ) {}

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

	/** @return ($asEntry is true ? string[] : string) */
	public function get( string $id, bool $asEntry = false ): string|array {
		return $asEntry ? ( $this->entryStack->get( $id ) ?? array() ) : ( $this->stack[ $id ] ?? $id );
	}

	public function remove( string $id ): bool {
		$this->removeEntryAlias( $id );

		return $this->remover( $id );
	}

	public function reset( ?string $collectionId = null ): void {
		$this->stack = array();

		$this->entryStack->reset( $collectionId );
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
				$this->entryStack->remove( $entry, $index );
			}
		}
	}
}
