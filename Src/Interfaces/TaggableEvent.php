<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Interfaces;

interface TaggableEvent {
	/**
	 * Gets the entry name.
	 *
	 * The entry name may be any immutable string literal (alias) or a fully qualified classname.
	 */
	public function getEntry(): string;
}
