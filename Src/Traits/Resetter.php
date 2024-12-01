<?php
/**
 * Resets based on whether collection ID is explicitly passed by the user.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Traits;

use TheWebSolver\Codegarage\Lib\Container\Interfaces\Resettable;

trait Resetter {
	public function reset( ?string $collectionId = null ): void {
		$userHasProvidedCollectionId = array_key_exists( key: 0, array: func_get_args() );

		foreach ( $this->getResettable() as $resetter ) {
			if ( ! $resetter instanceof Resettable ) {
				continue;
			}

			if ( $userHasProvidedCollectionId ) {
				$resetter->reset( $collectionId );
			} else {
				$this->resetWithoutUserProvidedId( $resetter );
			}
		}
	}

	protected function resetWithoutUserProvidedId( Resettable $resetter ): void {
		$resetter->reset();
	}

	abstract protected function getResettable(): iterable;
}
