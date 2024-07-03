<?php
/**
 * Parameter Resolver.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Error;

use ReflectionParameter;
use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;

class InvalidParam extends InvalidArgumentException implements ContainerExceptionInterface {
	public static function for( ReflectionParameter $param ) {
		$msg = "Unable to resolve dependency parameter: {$param}";

		if ( $class = $param->getDeclaringClass() ) {
			$msg .= " in class: {$class->getName()}";
		}

		return new self( "{$msg}." );
	}
}
