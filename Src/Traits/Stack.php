<?php
/**
 * The data stack.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Traits;

trait Stack {
	/** @var mixed[] */
	private array $stack = array();

	/** @return mixed[] */
	public function getItems(): array {
		return $this->stack;
	}

	public function hasItems(): bool {
		return ! empty( $this->stack );
	}

	public function reset( ?string $collectionId = null ): void {
		$this->stack = array();
	}
}
