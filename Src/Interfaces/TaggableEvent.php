<?php
/**
 * The event that listens based on the given entry.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Interfaces;

interface TaggableEvent {
	/**
	 * Gets the entry name.
	 *
	 * The entry name may be any immutable string literal (alias) or a fully qualified classname.
	 */
	public function getEntry(): string;
}
