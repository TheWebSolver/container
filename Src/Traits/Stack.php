<?php
/**
 * The data stack.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Traits;

/**
 * @template TKey
 * @template TValue
*/
trait Stack {
	/** @param array<TKey,TValue> $stack */
	final public function __construct( private array $stack = array() ) {}

	/** @return array<TKey,TValue> */
	public function getItems(): array {
		return $this->stack;
	}

	public function hasItems(): bool {
		return ! empty( $this->stack );
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	public function reset( ?string $collectionId = null ): void {
		$this->stack = array();
	}
}
