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
	private Closure|Countable|int $count;
	private Closure $generator;

	public function __construct( Closure $generator, Closure|Countable|int $count ) {
		$this->count     = $count;
		$this->generator = $generator;
	}

	/** @return Traversable<mixed> */
	public function getIterator(): Traversable {
		return ( $this->generator )();
	}

	public function count(): int {
		$count = $this->count;

		return match ( true ) {
			$count instanceof Countable => count( $count ),
			$count instanceof Closure   => $count(),
			default                     => $count,
		};
	}

	/** @param array<string|object> $concretes The concretes to resolve or already resolved. */
	public static function generate( array $concretes, Container $app ): \Generator {
		foreach ( $concretes as $concrete ) {
			yield ! is_object( $concrete ) ? $app->get( $concrete ) : $concrete;
		}
	}

	public static function generateClosure( string $id, Closure|string $concrete ): Closure {
		if ( $concrete instanceof Closure ) {
			return $concrete;
		}

		return static fn( Container $app, array $params = array() ): mixed => $id !== $concrete
			? $app->withoutEvents( id: $concrete, params: $params )
			: $app->build( $id );
	}
}
