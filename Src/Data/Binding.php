<?php
/**
 * The binding data.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Data;

readonly class Binding {
	public function __construct(
		public object $concrete,
		public bool $singleton = false,
		public bool $instance = false
	) {}

	public function isInstance(): bool {
		return $this->instance && ! $this->singleton;
	}

	public function isSingleton(): bool {
		return $this->singleton && ! $this->instance;
	}
}
