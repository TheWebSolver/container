<?php
/**
 * Attribute for Event Listener when app is resolving the target parameter.
 *
 * @package TheWebSolver\Codegarage\Container
 *
 * @phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Attribute;

use Attribute;
use TheWebSolver\Codegarage\Lib\Container\Event\BuildingEvent;

#[Attribute( flags: Attribute::TARGET_PARAMETER )]
final class ListenTo {
	/** @var callable(BuildingEvent $event): void */
	public $listener;

	/**
	 * @param callable(BuildingEvent $event): void $listener
	 * @param bool                   $isFinal  Whether Attribute Event Listener should be considered final.
	 *                                         If it is final, user-defined listeners will be ignored.
	 */
	public function __construct( callable $listener, public readonly bool $isFinal = false ) {
		$this->listener = $listener;
	}
}
