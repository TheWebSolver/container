<?php
/**
 * Eventual binding for the container during the entry's build process.
 *
 * @package TheWebSolver\Codegarage\Container
 *
 * @phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch -- Closure type-hint OK.
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Helper;

use Closure;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;

readonly class EventBuilder {
	private string $concrete;

	public function __construct( private Event $event, private string $paramName ) {}

	public function for( string $concrete ): self {
		$this->concrete = $concrete;

		return $this;
	}

	/** @param Binding|Closure(string $paramName, Container $app):Binding $implementation */
	public function give( Binding|Closure $implementation ): void {
		$this->event->subscribeDuringBuild(
			id: $this->concrete,
			dependencyName: $this->paramName,
			implementation: $implementation
		);
	}
}
