<?php
/**
 * Implementation to reset data.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Interfaces;

interface Resettable {
	/**
	 * Resets data.
	 *
	 * Data can be reset on its entirety or for a specific collection ID (if given).
	 */
	public function reset( ?string $collectionId = null ): void;
}
