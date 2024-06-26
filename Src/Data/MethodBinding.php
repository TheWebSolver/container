<?php
/**
 * The binding data.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Data;

use Closure;

readonly class MethodBinding {
	public function __construct( public Closure $concrete ) {}
}
