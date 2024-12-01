<?php
/**
 * Registers complied data to Stack.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Traits;

use RuntimeException;

/**
 * @template TKey
 * @template TValue
 */
trait StackCompiler {
	/** @var array<TKey,TValue> */
	private array $stack;

	/** @param array<TKey,TValue> $data */
	public static function fromCompiledArray( array $data ): static {
		$self        = new static();
		$self->stack = $data;

		return $self;
	}

	public static function fromCompiledFile( string $path ): static {
		return ( $realpath = realpath( $path ) )
			? static::fromCompiledArray( require $realpath )
			: throw new RuntimeException( "Could not find compiled data from filepath {$path}." );
	}
}
