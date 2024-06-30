<?php
/**
 * Generator that yields object built by the container.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Helper;

use Closure;
use Countable;
use Traversable;
use IteratorAggregate;
use TheWebSolver\Codegarage\Lib\Container\Container;

/**
 * Generator that yields object built by the container.
 *
 * @template TKey int
 * @template TValue object
 * @implements IteratorAggregate<TKey, TValue>
 */
class Generator implements Countable, IteratorAggregate {
	private Closure $generator;
	private Closure|int $count;

	public function __construct( Closure $generator, Closure|int $count ) {
		$this->count     = $count;
		$this->generator = $generator;
	}

	/** @return Traversable<mixed> */
	public function getIterator(): Traversable {
		return ( $this->generator )();
	}

	public function count(): int {
		$count = $this->count;

		return $this->count = is_callable( $count ) ? $count() : $count;
	}

	/** @param array<string|object> $concretes The concretes to resolve or already resolved. */
	public static function generate( array $concretes, Container $container ): \Generator {
		foreach ( $concretes as $concrete ) {
			yield ! is_object( $concrete ) ? $container->get( $concrete ) : $concrete;
		}
	}

	public static function generateClosure( string $id, string $concrete ): Closure {
		return static fn( Container $app, $params = array() ): mixed => $id === $concrete
			? $app->build( $concrete )
			: $app->withoutEvents( id: $concrete, params: $params );
	}
}
