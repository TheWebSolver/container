<?php
/**
 * Method resolver for DI & Auto-wiring method calls.
 *
 * @package TheWebSolver\Codegarage\Container
 *
 * @phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
 * @phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Helper;

use ReflectionMethod;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Pool\Param;
use TheWebSolver\Codegarage\Lib\Container\Pool\Artefact;
use TheWebSolver\Codegarage\Lib\Container\Error\BadResolverArgument;

class MethodResolver {
	protected string $context;

	public function __construct(
		protected readonly Container $app,
		protected readonly Event $event,
		protected readonly Artefact $artefact = new Artefact(),
		protected readonly Param $pool = new Param(),
	) {}

	/**
	 * @param array<string,mixed> $params The method's injected parameters.
	 * @throws BadResolverArgument When method cannot be resolved or no `$default`.
	 */
	public function resolve( callable|string $cb, ?string $default, array $params = array() ): mixed {
		$this->pool->push( value: $params );

		if ( is_string( $cb ) ) {
			$this->context = static::artefactFrom( $cb );

			return $this->instantiateFrom( $cb, method: $default );
		}

		$this->context    = static::artefactFrom( $unwrapped = Unwrap::callback( $cb, asArray: true ) );
		[ $cls, $method ] = $unwrapped;
		$resolved         = is_string( $cls ) // The $cls is obj: at this point. Ensure just in case...
			? $this->instantiateFrom( $cls, $method )
			: $this->resolveFrom( id: $this->context, cb: $unwrapped, obj: $cls );

		$this->pool->pull();

		return $resolved;
	}

	public static function keyFrom( callable|string $id ): string {
		return is_string( value: $id ) ? $id : Unwrap::callback( cb: $id );
	}

	protected function resolveFrom( string $id, callable $cb, ?object $obj = null ): mixed {
		return $this->app->hasBinding( $id )
			? ( $this->app->getBinding( $id )->concrete )( $obj ?? $cb[0], $this->app )
			: Unwrap::andInvoke( $cb, ...$this->dependenciesFrom( cb: $cb ) );
	}

	protected function instantiateFrom( string $cb, ?string $method ): mixed {
		$parts  = Unwrap::partsFrom( string: $cb );
		$method = $parts[1] ?? $method ?? ( method_exists( $cb, '__invoke' ) ? '__invoke' : null );

		if ( null === $method ) {
			throw BadResolverArgument::noMethod( class: $parts[0] );
		} elseif ( $class = static::instantiatedClass( name: $parts[0] ) ) {
			throw BadResolverArgument::instantiatedBeforehand( $class, $method );
		} elseif ( ! is_callable( $om = array( $this->app->get( id: $parts[0] ), $method ) ) ) {
			throw BadResolverArgument::nonInstantiableEntry( id: $parts[0] );
		}

		return $this->resolveFrom( id: Unwrap::asString( $parts[0], $method ), cb: $om );
	}

	/** @return mixed[] */
	protected function dependenciesFrom( callable $cb ): array {
		if ( ! $this->artefact->has( value: $this->context ) ) {
			$this->artefact->push( value: $this->context );
		}

		$resolved = ( new ParamResolver( app: $this->app, pool: $this->pool, event: $this->event ) )
			->resolve( dependencies: static::reflectorFrom( $cb )->getParameters() );

		if ( $this->artefact->has( value: $this->context ) ) {
			$this->artefact->pull();
		}

		return $resolved;
	}

	protected static function reflectorFrom( callable $cb ): ReflectionFunctionAbstract {
		return is_array( $cb ) ? new ReflectionMethod( ...$cb ) : new ReflectionFunction( $cb );
	}

	/** @param string|array{0:object|string,1:string} $cb */
	protected static function artefactFrom( string|array $cb ): string {
		if ( is_string( $cb ) ) {
			if ( str_contains( haystack: $cb, needle: '::' ) ) {
				return $cb;
			}

			$cb = array( $cb, '__invoke' );
		}

		return Unwrap::asString( ...$cb );
	}

	protected static function instantiatedClass( string $name ): ?string {
		$parts = Unwrap::partsFrom( string: $name, separator: '#' );

		return isset( $parts[1] ) // $parts[0] => classname, $parts[1] => ?spl_object_id()::methodName.
			&& is_numeric( value: Unwrap::partsFrom( $parts[1] )[0] ) ? $parts[0] : null;
	}
}
