<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Interfaces;

use RuntimeException;

/**
 * @template TKey
 * @template TValue
 */
interface Compilable {
	/**
	 * Gets an instance with the compiled data.
	 *
	 * @param array<TKey,TValue> $data
	 */
	public static function fromCompiledArray( array $data ): static;

	/**
	 * Gets an instance using a filepath that returns the compiled data.
	 *
	 * @throws RuntimeException When given $path is not a valid path.
	 */
	public static function fromCompiledFile( string $path ): static;
}
