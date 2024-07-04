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
use TheWebSolver\Codegarage\Lib\Container\Pool\IndexStack;
use TheWebSolver\Codegarage\Lib\Container\Error\InvalidParam;

readonly class ParamResolver {
	public function __construct(
		private Container $app,
		private Param $dependencyInjection,
		private Event $event,
		private IndexStack $result = new IndexStack(),
	) {}

	/**
	 * @param ReflectionParameter[] $dependencies
	 * @throws ContainerExceptionInterface When dependencies resolving failed.
	 */
	public function resolve( array $dependencies ): array {
		foreach ( $dependencies as $param ) {
			if ( $this->dependencyInjection->has( $param->name ) ) {
				$this->setFor( $param, result: $this->dependencyInjection->getFrom( $param->name ) );

				continue;
			}

			if ( $result = $this->getResultFromEvent( $param ) ) {
				$this->setFor( $param, $result );

				continue;
			}

			$type   = Unwrap::paramTypeFrom( $param );
			$result = ! $type ? $this->fromUntyped( $param ) : $this->fromType( $param, $type );

			$this->setFor( $param, $result );
		}

		$this->dependencyInjection->pull();

		return $this->result->getItems();
	}

	public function fromUntyped( ReflectionParameter $param ): mixed {
		$value = $this->app->getContextualFor( context: '$' . $param->getName() );

		return match ( true ) {
			null !== $value => Unwrap::andInvoke( $value, $this->app ),
			default         => self::coerce( $param, exception: InvalidParam::for( $param ) )
		};
	}

	public function fromType( ReflectionParameter $param, string $type ): mixed {
		$id    = $this->app->getEntryFrom( alias: $type );
		$value = $this->app->getContextualFor( context: $id );

		try {
			return $value ? Unwrap::andInvoke( $value, $this->app ) : $this->app->get( $id );
		} catch ( ContainerExceptionInterface $unresolvable ) {
			return self::coerce( $param, exception: $unresolvable );
		}
	}

	protected function setFor( ReflectionParameter $param, mixed $result ): void {
		match ( true ) {
			$param->isVariadic() => $this->result->restackWith( newValue: $result, mergeArray: true ),
			default              => $this->result->set( value: $result )
		};
	}

	protected function getResultFromEvent( ReflectionParameter $param ): mixed {
		return ( $type = $param->getType() ) instanceof ReflectionNamedType
			? $this->event->fireDuringBuild( $type->getName(), $param->getName() )?->concrete
			: null;
	}

	protected static function coerce(
		ReflectionParameter $param,
		ContainerExceptionInterface $exception
	): mixed {
		return match ( true ) {
			$param->isDefaultValueAvailable() => $param->getDefaultValue(),
			$param->isVariadic()              => array(),
			default                           => throw $exception,
		};
	}
}
