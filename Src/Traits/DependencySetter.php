<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Traits;

use ArrayAccess;
use ReflectionParameter;

trait DependencySetter {
	/** @var array<string,mixed>|ArrayAccess<object|string,mixed> */
	private array|ArrayAccess $dependencies = array();
	/** @var ReflectionParameter[] */
	private array $reflections = array();

	/**
	 * @param array<string,mixed>|ArrayAccess<object|string,mixed> $args
	 * @param ReflectionParameter[]                                $reflections
	 */
	public function withParameter( array|ArrayAccess $args, array $reflections = array() ): static {
		$this->dependencies = $args;
		$this->reflections  = $reflections;

		return $this;
	}
}
