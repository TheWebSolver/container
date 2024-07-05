<?php
/**
 * Contextual bindings for the container during the entry's build process.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Helper;

use Closure;
use TheWebSolver\Codegarage\Lib\Container\Container;

readonly class ContextBuilder {
	protected string $toBeResolved;

	/** @param string[] $for */
	public function __construct( private array $for, private Container $container ) {}

	public function needs( string $requirement ): self {
		$this->toBeResolved = $requirement;

		return $this;
	}

	public function give( Closure|string $value ): void {
		foreach ( $this->for as $entry ) {
			$this->container->addContextual( with: $value, for: $entry, id: $this->toBeResolved );
		}
	}

	public function giveTagged( string $tag ): void {
		$this->give(
			static fn( Container $container ) => is_array( $tagged = $container->tagged( $tag ) )
				? $tagged
				: iterator_to_array( $tagged )
		);
	}

	/** @param array<class-string|object> $entries The concretes to resolve or already resolved. */
	public function resolve( array $entries ): void {
		$this->give( static fn( Container $container ) => $container->resolveOnce( $entries ) );
	}
}
