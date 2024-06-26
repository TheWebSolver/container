<?php
/**
 * The binding pool.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Pool;

use Closure;
use TheWebSolver\Codegarage\Lib\Container\Traits\KeyStack;

class Contextual {
	use KeyStack;

	/**
	 * @param string         $artefact       The context ID for whom contextual build is needed.
	 * @param string         $key            The key to contextually bind the resolved value.
	 * @param Closure|string $implementation The implementation that resolves the value.
	 */
	public function set( string $artefact, string $key, Closure|string $implementation ): void {
		$this->stack[ $artefact ][ $key ] = $implementation;
		// $this->stack[ $artefact ] = array(
		// ...( $this->stack[ $artefact ] ?? array() ),
		// $key => $implementation,
		// );
	}

	/** @return Closure|string|null `null` if cannot find any contextual data. */
	public function get( string $artefact, string $key ): Closure|string|null {
		return $this->stack[ $artefact ][ $key ] ?? null;
	}

	public function has( string $artefact ): bool {
		return isset( $this->stack[ $artefact ] );
	}

	/** @return array<string,array<string,Closure|string> */
	public function getItems(): array {
		return $this->stack;
	}
}
