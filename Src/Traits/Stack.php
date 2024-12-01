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
	/** @var array<TKey,TValue> */
	private array $stack;

	final public function __construct() {
		$this->reset();
	}

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
