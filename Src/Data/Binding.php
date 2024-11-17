<?php
/**
 * The binding data transfer object.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Data;

use Closure;

readonly class Binding {
	/** @param class-string|Closure $material */
	public function __construct( public string|Closure $material, public bool $isShared = false ) {}
}
