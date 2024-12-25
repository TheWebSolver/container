<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Data;

use TheWebSolver\Codegarage\Container\Traits\KeyStack;
use TheWebSolver\Codegarage\Container\Error\LogicalError;
use TheWebSolver\Codegarage\Container\Error\EntryNotFound;
use TheWebSolver\Codegarage\Container\Pool\CollectionStack;
use TheWebSolver\Codegarage\Container\Interfaces\Resettable;

class Aliases implements Resettable {
	/** @use KeyStack<class-string> */
	use KeyStack {
		KeyStack::remove as remover;
	}

	/** @var CollectionStack<int,string>*/
	private CollectionStack $entryStack;

	/** @param array<string,class-string> $stack */
	final public function __construct( private array $stack = array() ) {
		$this->entryStack = new CollectionStack();
	}

	/**
	 * @param class-string $entry
	 * @throws LogicalError When entry ID and alias is same.
	 */
	public function set( string $entry, string $alias ): void {
		if ( $alias === $entry ) {
			throw LogicalError::entryAndAliasIsSame( $entry );
		}

		$this->stack[ $alias ] = $entry;

		$this->entryStack->set( key: $entry, value: $alias );
	}

	/** @phpstan-assert-if-false =class-string $id */
	public function has( string $id, bool $asEntry = false ): bool {
		return $asEntry ? $this->entryStack->has( key: $id ) : isset( $this->stack[ $id ] );
	}

	/**
	 * @return ($asEntry is true ? array<int,string> : class-string)
	 * @throws EntryNotFound When could not find entry for the given $id.
	 * @phpstan-assert class-string $id
	 */
	public function get( string $id, bool $asEntry = false ): string|array {
		return $asEntry
			? ( $this->entryStack->get( $id ) ?? array() )
			: ( $this->stack[ $id ] ?? throw EntryNotFound::for( $id, previous: null ) );
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
