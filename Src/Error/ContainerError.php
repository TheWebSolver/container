<?php
/**
 * Main Container Exception class.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Error;

use Exception;
use ReflectionException;
use Psr\Container\ContainerExceptionInterface;
use TheWebSolver\Codegarage\Lib\Container\Pool\Artefact;

class ContainerError extends Exception implements ContainerExceptionInterface {
	public static function unResolvableEntry( string $id, ReflectionException $previous ): self {
		return new self(
			message: "Unable to find the target class: \"{$id}\".",
			previous: $previous
		);
	}

	public static function unInstantiableEntry( string $id, Artefact $artefact ): self {
		return new self(
			"Unable to instantiate the target class: \"{$id}\"" . (
				$artefact->hasItems() ? " while building [$artefact]" : ''
			) . '.'
		);
	}
}
