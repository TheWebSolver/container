<?php
/**
 * Contextual bindings for the container during the entry's build process.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Helper;

use Closure;
use Generator;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;
use TheWebSolver\Codegarage\Lib\Container\Helper\Generator as AppGenerator;

readonly class ContextBuilder {
	protected string $toBeResolved;

	/** @param string[] $for */
	public function __construct(
		private array $for,
		private Container $app,
		private Stack $contextual
	) {}

	public function needs( string $requirement ): self {
		$this->toBeResolved = $requirement;

		return $this;
	}

	public function give( Closure|string $value ): void {
		foreach ( $this->for as $id ) {
			$this->contextual->set(
				key: Stack::keyFrom( id: $this->app->getEntryFrom( alias: $id ), name: $this->toBeResolved ),
				value: $value
			);
		}
	}

	public function giveTagged( string $tag ): void {
		$this->give(
			static fn( Container $app ) => is_array( $tagged = $app->tagged( $tag ) )
				? $tagged
				: iterator_to_array( $tagged )
		);
	}

	/**
	 * Resolves the given concretes without registering to the container.
	 *
	 * @param array<class-string|object> $entries The concretes to resolve or already resolved.
	 */
	public function giveOnce( array $entries ): void {
		$this->give(
			static fn () => iterator_to_array(
				new AppGenerator(
					generator: static fn(): Generator => AppGenerator::generate( $entries, $this->app ),
					count: count( $entries )
				)
			)
		);
	}
}
