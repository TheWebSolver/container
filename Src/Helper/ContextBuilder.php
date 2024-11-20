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
use TheWebSolver\Codegarage\Lib\Container\Error\LogicalError;
use TheWebSolver\Codegarage\Lib\Container\Pool\CollectionStack as Context;
use TheWebSolver\Codegarage\Lib\Container\Helper\Generator as AppGenerator;

final class ContextBuilder {
	protected string $constraint;

	/**
	 * @param string[]                             $ids
	 * @param Container                            $app
	 * @param Context<string,Closure|class-string> $contextual
	 */
	public function __construct(
		private readonly array $ids,
		private readonly Container $app,
		private readonly Context $contextual
	) {}

	/** @param string|string[]|Closure $concrete */
	public static function create( string|array|Closure $concrete, Container $app, Context $stack ): self {
		return new self(
			ids: Unwrap::asArray( $concrete instanceof Closure ? Unwrap::forBinding( $concrete ) : $concrete ),
			app: $app,
			contextual: $stack
		);
	}

	public function needs( string $constraint ): self {
		$this->constraint = $constraint;

		return $this;
	}

	/** @throws LogicalError When this method is invoked before setting $constraint. */
	public function give( Closure|string $value ): void {
		foreach ( $this->ids as $id ) {
			$this->contextual->set( $this->app->getEntryFrom( $id ), $value, index: $this->getConstraint() );
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
		return $this->constraint ?: throw LogicalError::contextualConstraintNotProvidedWith( method: 'needs' );
	}
}
