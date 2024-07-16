<?php
/**
 * Parameter Resolver for either class or method during auto-wiring.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Helper;

use ReflectionNamedType;
use ReflectionParameter;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Pool\Param;
use TheWebSolver\Codegarage\Lib\Container\Pool\IndexStack;
use Psr\Container\ContainerExceptionInterface as ContainerError;
use TheWebSolver\Codegarage\Lib\Container\Error\BadResolverArgument;

class ParamResolver {
	public function __construct(
		private readonly Container $app,
		private readonly Param $pool,
		private readonly Event $event,
		private readonly IndexStack $result = new IndexStack(),
	) {}

	/**
	 * @param ReflectionParameter[] $dependencies
	 * @return mixed[]
	 * @throws ContainerError When resolving dependencies fails.
	 */
	public function resolve( array $dependencies ): array {
		foreach ( $dependencies as $param ) {
			$result = match ( true ) {
				$this->pool->has( $param->name )               => $this->pool->getFrom( $param->name ),
				null !== ( $val = $this->fromEvent( $param ) ) => $val,
				default                                        => $param
			};

			$this->for( $param, result: $param === $result ? $this->from( $result ) : $result );
		}

		$this->pool->pull();

		return $this->result->getItems();
	}

	/** @throws ContainerError When resolving dependencies fails. */
	public function fromUntypedOrBuiltin( ReflectionParameter $param ): mixed {
		$value = $this->app->getContextualFor( context: "\${$param->getName()}" );

		return null === $value
			? static::defaultFrom( $param, error: BadResolverArgument::noParam( ref: $param ) )
			: Unwrap::andInvoke( $value, $this->app );
	}

	/** @throws ContainerError When resolving dependencies fails. */
	public function fromTyped( ReflectionParameter $param, string $type ): mixed {
		$value = $this->app->getContextualFor( context: $type );

		try {
			return $value ? Unwrap::andInvoke( $value, $this->app ) : $this->app->get( id: $type );
		} catch ( ContainerError $unresolvable ) {
			return static::defaultFrom( $param, error: $unresolvable );
		}
	}

	protected function for( ReflectionParameter $param, mixed $result ): void {
		match ( true ) {
			$param->isVariadic() => $this->result->restackWith( newValue: $result, mergeArray: true ),
			default              => $this->result->set( value: $result )
		};
	}

	protected function from( ReflectionParameter $param ): mixed {
		$type = Unwrap::paramTypeFrom( reflection: $param );

		return $type ? $this->fromTyped( $param, $type ) : $this->fromUntypedOrBuiltin( $param );
	}

	protected function fromEvent( ReflectionParameter $param ): mixed {
		return ( $type = $param->getType() ) instanceof ReflectionNamedType
			? $this->event->fireDuringBuild( $type->getName(), $param->getName() )?->concrete
			: null;
	}

	protected static function defaultFrom( ReflectionParameter $param, ContainerError $error ): mixed {
		return match ( true ) {
			$param->isDefaultValueAvailable() => $param->getDefaultValue(),
			$param->isVariadic()              => array(),
			default                           => throw $error,
		};
	}
}
