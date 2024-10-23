<?php
/**
 * Attribute for Event Listener when app is resolving the target parameter.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Attribute;

use Attribute;

#[Attribute( flags: Attribute::TARGET_PARAMETER )]
final readonly class ListenTo {
	/**
	 * @param string|array{0:string,1:string} $listener Accepts `BuildingEvent` as argument.
	 * @param bool                            $isFinal  Whether Attribute Event Listener should be considered final.
	 *                                                  If it is final, user-defined listeners will be ignored.
	 */
	public function __construct( public string|array $listener, public bool $isFinal = false ) {}
}
