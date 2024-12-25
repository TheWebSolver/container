<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Interfaces;

interface Resettable {
	/**
	 * Resets data.
	 *
	 * Data can be reset on its entirety or for a specific collection ID (if given).
	 */
	public function reset( ?string $collectionId = null ): void;
}
