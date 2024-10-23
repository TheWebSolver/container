<?php
/**
 * Implementation to reset data.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Interfaces;

interface Resettable {
	public function reset( ?string $collectionId = null ): void;
}
