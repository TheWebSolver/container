<?php
/**
 * Parameter Resolver.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Helper;

use ReflectionNamedType;
use ReflectionParameter;
use Psr\Container\ContainerExceptionInterface;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Pool\Param;

class ParamResolver {
	public function __construct(
		private Container $container,
		private Param $paramPool,
		private array $during_resolving = array(),
	) {}

	/**
	 * @param ReflectionParameter[] $dependencies
	 * @throws ContainerExceptionInterface When dependencies resolving failed.
	 */
	public function resolve( array $dependencies ): array {
		$results = array();

		foreach ( $dependencies as $dependency ) {
			$paramName = $dependency->getName();
			$type      = $dependency->getType();
			$type      = $type instanceof ReflectionNamedType ? $type->getName() : null;

			// If we have something that is directly resolved by external service, we'll use it.
			if ( $resolver = ( $this->during_resolving[ $type ][ $paramName ] ?? false ) ) {
				$results[] = $resolver( $dependency->getName() );

				unset( $this->during_resolving[ $type ][ $paramName ] );

				continue;
			}

			if ( $this->paramPool->hasLatest( $dependency ) ) {
				$results[] = $this->paramPool->getLatest( $dependency );

				continue;
			}

			$result = null === Unwrap::paramTypeFrom( $dependency )
				? $this->resolvePrimitive( $dependency )
				: $this->resolveClass( $dependency );

			if ( $dependency->isVariadic() ) {
				$results = array( ...$results, $result );
			} else {
				$results[] = $result;
			}
		}//end foreach

		return $results;
	}

	public function resolvePrimitive( ReflectionParameter $param ): mixed {
		if ( null !== ( $concrete = $this->container->getContextual( id: '$' . $param->getName() ) ) ) {
			return Unwrap::andInvoke( $concrete, $this->container );
		}

		if ( $param->isDefaultValueAvailable() ) {
			return $param->getDefaultValue();
		}

		if ( $param->isVariadic() ) {
			return array();
		}

		// FIXME: Add appropriate exception handler.
		throw new class(
			sprintf(
				'Unable to resolve dependency parameter: %1$s in class: %2$s',
				$param,
				( $class = $param->getDeclaringClass() ) ? $class->getName() : ''
			)
		) implements ContainerExceptionInterface {};
	}

	protected function resolveClass( ReflectionParameter $parameter ): mixed {
		try {
			return $parameter->isVariadic()
				? $this->resolveVariadicClass( $parameter )
				: $this->container->make( Unwrap::paramTypeFrom( $parameter ) ?? '' );
		} catch ( ContainerExceptionInterface $e ) {
			if ( $parameter->isDefaultValueAvailable() ) {
				$this->paramPool->pull();

				return $parameter->getDefaultValue();
			}

			if ( $parameter->isVariadic() ) {
				$this->paramPool->pull();

				return array();
			}

			throw $e;
		}
	}

	/**
	 * Resolves a class based variadic dependency from the container.
	 *
	 * @param  ReflectionParameter $parameter The method parameter.
	 * @return mixed
	 * @throws ContainerExceptionInterface When classname can't be extracted from parameter.
	 * @since  1.0
	 */
	protected function resolveVariadicClass( ReflectionParameter $parameter ): mixed {
		$class_name = Unwrap::paramTypeFrom( $parameter );

		if ( ! $class_name ) {
			// FIXME: Add appropriate exception handler.
			throw new class(
				'Unable to resolve classname for given parameter.'
			) implements ContainerExceptionInterface {};
		}

		$abstract = $this->container->getEntryFrom( $class_name );
		$concrete = $concrete = $this->container->getContextual( id: $abstract );

		return is_array( $concrete )
			? array_map( static fn ( $abstract ) => $this->container->make( $abstract ), $concrete )
			: $this->container->make( $class_name );
	}
}
