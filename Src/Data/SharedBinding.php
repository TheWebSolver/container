<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Data;

readonly class SharedBinding {
	public function __construct( public object $material ) {}
}
