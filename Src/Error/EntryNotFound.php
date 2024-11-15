<?php
/**
 * Exception for entries not found in the container.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Error;

use Throwable;
use Psr\Container\NotFoundExceptionInterface;

class EntryNotFound extends ContainerError implements NotFoundExceptionInterface {
	public static function for( string $id, ?Throwable $previous ): self {
		return new self(
			message: "Unable to find entry for the given id: \"{$id}\".",
			previous: $previous
		);
	}

	public static function forRebound( string $id ): self {
		return new self(
			"Unable to find entry for the given id: \"{$id}\" when possible rebinding was expected." .
			' The entry must be bound with Container::bind() method before expecting resolved' .
			' instance for the given entry ID.'
		);
	}
}
