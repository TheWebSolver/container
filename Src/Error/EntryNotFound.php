<?php
/**
 * Exception for entries not found in the container.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Error;

use Exception;
use ReflectionParameter;
use Psr\Container\NotFoundExceptionInterface;

class EntryNotFound extends Exception implements NotFoundExceptionInterface {
	public static function forRebound( string $id ): self {
		return new self(
			"Unable to find entry for the given id: \"{$id}\" when possible rebinding was expected." .
			' The entry must be bound with Container::bind() method before expecting resolved' .
			' instance for the given entry ID.'
		);
	}

	public static function nonInstantiableEntry( string $id ): self {
		return new self( "Unable to instantiate entry: {$id}." );
	}

	public static function noParam( ReflectionParameter $ref ) {
		$msg = "Unable to resolve dependency parameter: {$ref}";

		if ( $class = $ref->getDeclaringClass() ) {
			$msg .= " in class: {$class->getName()}";
		}

		return new self( "{$msg}." );
	}

	public static function instantiatedBeforehand( string $class, string $method ): self {
		return new self(
			"Cannot resolve instantiated class method \"{$class}::{$method}()\". To resolve method, " .
			"Pass [\$objectInstance, '{$method}'] as callback argument."
		);
	}
}
