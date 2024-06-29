<?php
/**
 * Binding based on current dependency being resolved.
 *
 * @package TheWebSolver\Codegarage\Container
 *
 * @phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch -- Closure type-hint OK.
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Pool;

use Closure;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Traits\KeyStack;

class Eventual {
	use KeyStack;

	/**
	 * @param string                 $artefact       The context ID for whom eventual build is needed.
	 * @param string                 $dependency     The dependency name to bind the resolved value.
	 * @param Binding|Closure(string $param, Container $app): Binding $implementation The implementation that resolves the value.
	 */
	public function set(
		string $artefact,
		string $dependency,
		Binding|Closure $implementation
	): void {
		$this->stack[ $artefact ][ $dependency ] = $implementation;
	}

	/** @return Binding|Closure(string $param, Container $app): Binding `null` if cannot find any eventual data. */
	public function get( string $artefact, string $dependency ): Binding|Closure {
		return $this->stack[ $artefact ][ $dependency ];
	}

	public function has( string $artefact, string $dependency ): bool {
		return isset( $this->stack[ $artefact ][ $dependency ] );
	}

	public function remove( string $artefact, string $dependency ): bool {
		if ( ! $this->has( $artefact, $dependency ) ) {
			return false;
		}

		unset( $this->stack[ $artefact ][ $dependency ] );

		return true;
	}

	/** @return array<string,array<string,Binding|Closure(string $param, Container $app): Binding> */
	public function getItems(): array {
		return $this->stack;
	}
}
