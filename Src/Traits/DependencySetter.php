<?php
/**
 * Sets Parameter Reflections & its arguments.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Traits;

use ReflectionParameter;
use TheWebSolver\Codegarage\Lib\Container\Pool\Param;

trait DependencySetter {
	/** @var ReflectionParameter[] */
	private array $dependencies;
	private Param $stack;

	/** @param ReflectionParameter[] $parameters */
	public function withReflectionParameters( array $parameters ): static {
		$this->dependencies = $parameters;

		return $this;
	}

	public function withParameterStack( Param $stack ): static {
		$this->stack = $stack;

		return $this;
	}
}
