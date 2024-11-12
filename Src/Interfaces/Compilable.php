<?php
/**
 * Interface for supporting compiled data.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Interfaces;

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
	 */
	public static function fromCompiledFile( string $path ): static;
}
