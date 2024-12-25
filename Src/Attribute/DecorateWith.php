<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Attribute;

use Attribute;
use TheWebSolver\Codegarage\Container\Event\AfterBuildEvent;

#[Attribute( flags: Attribute::TARGET_CLASS )]
final class DecorateWith {
	/** @var callable(AfterBuildEvent): void */
	public $listener;

	/** @param callable(AfterBuildEvent): void $listener */
	public function __construct( callable $listener, public readonly bool $isFinal = false ) {
		$this->listener = $listener;
	}
}
