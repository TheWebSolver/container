<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Error;

use ReflectionParameter;
use Psr\Container\ContainerExceptionInterface;

class BadResolverArgument extends ContainerError implements ContainerExceptionInterface {
	public static function noMethod( string $class ): self {
		return new self( "Unable to find method for class: {$class}." );
	}

	public static function nonInstantiableEntry( string $id ): self {
		return new self( "Unable to instantiate entry: {$id}." );
	}

	public static function noParam( ReflectionParameter $ref ): self {
		$msg = "Unable to resolve dependency parameter: {$ref}" . (
			( $class = $ref->getDeclaringClass() ) ? " in class: {$class->getName()}" : ''
		);

		return new self( "{$msg}." );
	}

	public static function instantiatedBeforehand( string $class, string $method ): self {
		return new self(
			"Cannot resolve instantiated class method \"{$class}::{$method}()\". To resolve method, " .
			"Pass [\$objectInstance, '{$method}'] as callback argument."
		);
	}

	public static function forBuildingParam( string $type, string $name, string $id ): self {
		return new self(
			sprintf(
				'The bound instance from Event Listener is not a valid type. Expected an instance of'
				. ' "%1$s" for parameter "$%2$s" while building "%3$s".',
				$type,
				$name,
				$id
			)
		);
	}
}
