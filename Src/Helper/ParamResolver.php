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
use TheWebSolver\Codegarage\Lib\Container\Error\InvalidParam;

class ParamResolver {
	public function __construct(
		private readonly Container $app,
		private readonly Param $paramPool,
		private readonly Event $event
	) {}

	/**
	 * @param ReflectionParameter[] $dependencies
	 * @throws InvalidParam&ContainerExceptionInterface When dependencies resolving failed.
	 */
	public function resolve( array $dependencies ): array {
		$results = array();

		foreach ( $dependencies as $param ) {
			if ( $this->paramPool->hasLatest( dependency: $param ) ) {
				$results[] = $this->paramPool->getLatest( dependency: $param );

				continue;
			}

			if ( $result = $this->getResultFromEvent( $param ) ) {
				$results[] = $result;

				continue;
			}

			$type   = Unwrap::paramTypeFrom( $param );
			$result = ! $type ? $this->fromUntyped( $param ) : $this->fromType( $param, $type );

			if ( $param->isVariadic() ) {
				$results = array( ...$results, $result );
			} else {
				$results[] = $result;
			}
		}//end foreach

		return $results;
	}

	public function fromUntyped( ReflectionParameter $param ): mixed {
		$value = $this->app->getContextualFor( '$' . $param->getName() );

		return match ( true ) {
			null !== $value                   => Unwrap::andInvoke( $value, $this->app ),
			$param->isDefaultValueAvailable() => $param->getDefaultValue(),
			$param->isVariadic()              => array(),
			default                           => throw InvalidParam::error( $param )
		};
	}

	public function fromType( ReflectionParameter $param, string $type ): mixed {
		$id    = $this->app->getEntryFrom( alias: $type );
		$value = $this->app->getContextualFor( context: $id );

		try {
			return $value ? Unwrap::andInvoke( $value, $this->app ) : $this->app->get( $id );
		} catch ( ContainerExceptionInterface $e ) {
			if ( $param->isDefaultValueAvailable() ) {
				$this->paramPool->pull();

				return $param->getDefaultValue();
			}

			if ( $param->isVariadic() ) {
				$this->paramPool->pull();

				return array();
			}

			throw $e;
		}
	}

	protected function getResultFromEvent( ReflectionParameter $param ): mixed {
		$type = $param->getType();

		return $type instanceof ReflectionNamedType
			? $this->event->fireDuringBuild( $type->getName(), $param->getName() )?->concrete
			: null;
	}
}
