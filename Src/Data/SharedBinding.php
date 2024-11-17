<?php
/**
 * The shared binding data transfer object.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Data;

readonly class SharedBinding {
	public function __construct( public object $material ) {}
}
