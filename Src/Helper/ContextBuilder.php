<?php
/**
 * Contextual bindings for the container during the entry's build process.
 *
 * @package TheWebSolver\Codegarage\Container
 *
 * @phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch -- Generics Type-hint OK.
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Helper;

use Closure;
use Generator;
use LogicException;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Pool\CollectionStack;
use TheWebSolver\Codegarage\Lib\Container\Helper\Generator as AppGenerator;

final class ContextBuilder {
	protected string $constraint;

	/**
	 * @param string[]                              $for
	 * @param CollectionStack<Closure|class-string> $contextual
	 */
	public function __construct(
		private readonly array $for,
		private readonly Container $app,
		private readonly CollectionStack $contextual
	) {}

	public function needs( string $constraint ): self {
		$this->constraint = $constraint;

		return $this;
	}

	public function give( Closure|string $value ): void {
		foreach ( $this->for as $id ) {
			$this->contextual->set(
				key: $this->app->getEntryFrom( alias: $id ),
				value: $value,
				index: $this->getConstraint()
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
			fn () => iterator_to_array(
				new AppGenerator( fn(): Generator => AppGenerator::generate( $entries, $this->app ), count( $entries ) )
			)
		);
	}

	private function getConstraint(): string {
		return $this->constraint ?: throw new LogicException(
			sprintf(
				'The dependency to be resolved must be provided for using method "%1$s".',
				self::class . '::needs()'
			)
		);
	}
}
