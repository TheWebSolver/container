<?php
/**
 * Exception for various resolvers.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Error;

use Exception;
use ReflectionParameter;
use Psr\Container\ContainerExceptionInterface;

class BadResolverArgument extends Exception implements ContainerExceptionInterface {
	public static function noMethod( string $class ): self {
		return new self( "Unable to find method for class: {$class}." );
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
}
