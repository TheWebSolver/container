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
		private readonly Container $container,
		private readonly Param $paramPool,
		private readonly Event $event
	) {}

	/**
	 * @param ReflectionParameter[] $dependencies
	 * @throws ContainerExceptionInterface When dependencies resolving failed.
	 */
	public function resolve( array $dependencies ): array {
		$results = array();

		foreach ( $dependencies as $dependency ) {
			if ( $this->paramPool->hasLatest( $dependency ) ) {
				$results[] = $this->paramPool->getLatest( $dependency );

				continue;
			}

			if ( $result = $this->getResultFromEvent( param: $dependency ) ) {
				$results[] = $result;

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
		if ( null !== ( $concrete = $this->container->getContextualFor( '$' . $param->getName() ) ) ) {
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
				: $this->container->get( Unwrap::paramTypeFrom( $parameter ) ?? '' );
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
		$concrete = $concrete = $this->container->getContextualFor( context: $abstract );

		return is_array( $concrete )
			? array_map( static fn ( $abstract ) => $this->container->get( $abstract ), $concrete )
			: $this->container->get( $class_name );
	}

	protected function getResultFromEvent( ReflectionParameter $param ): mixed {
		$type = $param->getType();

		return $type instanceof ReflectionNamedType
			? $this->event->fireDuringBuild( $type->getName(), $param->getName() )?->concrete
			: null;
	}
}
