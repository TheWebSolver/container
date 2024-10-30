<?php
/**
 * Attribute for Event Listener when entry is resolved and decorated value is returned.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Attribute;

use Attribute;
use TheWebSolver\Codegarage\Lib\Container\Event\AfterBuildEvent;

#[Attribute( flags: Attribute::TARGET_CLASS )]
final class DecorateWith {
	/** @var callable(AfterBuildEvent $event): void */
	public $listener;

	// phpcs:ignore Squiz.Commenting.FunctionComment.ParamNameNoMatch
	/** @param callable(AfterBuildEvent $event): void $listener */
	public function __construct( callable $listener, public readonly bool $isFinal = false ) {
		$this->listener = $listener;
	}
}
