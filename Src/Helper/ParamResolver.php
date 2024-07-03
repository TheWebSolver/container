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
use TheWebSolver\Codegarage\Lib\Container\Container as App;
use TheWebSolver\Codegarage\Lib\Container\Pool\Param as Pool;
use TheWebSolver\Codegarage\Lib\Container\Error\InvalidParam;

readonly class ParamResolver {
	public function __construct( private App $app, private Pool $pool, private Event $event ) {}

	/**
	 * @param ReflectionParameter[] $dependencies
	 * @throws InvalidParam&ContainerExceptionInterface When dependencies resolving failed.
	 */
	public function resolve( array $dependencies ): array {
		$results = array();

		foreach ( $dependencies as $param ) {
			if ( $this->pool->hasLatest( dependency: $param ) ) {
				$results[] = $this->pool->getLatest( dependency: $param );

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
			default                           => throw InvalidParam::for( $param )
		};
	}

	public function fromType( ReflectionParameter $param, string $type ): mixed {
		$id    = $this->app->getEntryFrom( alias: $type );
		$value = $this->app->getContextualFor( context: $id );

		try {
			return $value ? Unwrap::andInvoke( $value, $this->app ) : $this->app->get( $id );
		} catch ( ContainerExceptionInterface $e ) {
			if ( $param->isDefaultValueAvailable() ) {
				$this->pool->pull();

				return $param->getDefaultValue();
			}

			if ( $param->isVariadic() ) {
				$this->pool->pull();

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
