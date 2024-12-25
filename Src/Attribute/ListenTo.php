<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Attribute;

use Attribute;
use TheWebSolver\Codegarage\Container\Event\BuildingEvent;

#[Attribute( flags: Attribute::TARGET_PARAMETER )]
final class ListenTo {
	/** @var callable(BuildingEvent): void */
	public $listener;

	/**
	 * @param callable(BuildingEvent): void $listener
	 * @param bool                          $isFinal  Whether Attribute Event Listener should be considered final.
	 *                                                If it is final, user-defined listeners will be ignored.
	 */
	public function __construct( callable $listener, public readonly bool $isFinal = false ) {
		$this->listener = $listener;
	}
}
