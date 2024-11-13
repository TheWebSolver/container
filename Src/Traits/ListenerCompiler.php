<?php
/**
 * Registers compiled data to listener provider.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Traits;

use RuntimeException;

/** @template TEvent of object */
trait ListenerCompiler {
	/** @use EventListeners<TEvent> */
	use EventListeners;

	// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamName
	/**
	 * @param array{
	 *  listenersForEntry?: array<string,array<int,array<int,callable(TEvent $event): void>>>,
	 *  listeners?: array<int,array<int,callable(TEvent $event): void>>
	 * } $data
	 */
	// phpcs:enable
	public static function fromCompiledArray( array $data ): static {
		$provider = new static(
			listenersForEntry: $data['listenersForEntry'] ?? array(),
			listeners: $data['listeners'] ?? array()
		);

		return $provider;
	}

	public static function fromCompiledFile( string $path ): static {
		return ( $realpath = realpath( $path ) )
			? static::fromCompiledArray( require $realpath )
			: throw new RuntimeException( "Could not find compiled data from filepath {$path}." );
	}
}