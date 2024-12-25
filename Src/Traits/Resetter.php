<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Traits;

use TheWebSolver\Codegarage\Container\Interfaces\Resettable;

trait Resetter {
	public function reset( ?string $collectionId = null ): void {
		$userHasProvidedCollectionId = ! func_num_args();

		foreach ( $this->getResettable() as $resetter ) {
			if ( ! $resetter instanceof Resettable ) {
				continue;
			}

			if ( $userHasProvidedCollectionId ) {
				$resetter->reset( $collectionId );
			} else {
				$this->resetWhenCollectionIdNotProvided( $resetter );
			}
		}
	}

	protected function resetWhenCollectionIdNotProvided( Resettable $resetter ): void {
		$resetter->reset();
	}

	abstract protected function getResettable(): iterable;
}
