<?php
/**
 * Bound method.
 *
 * @package TheWebSolver\Codegarage\Container
 *
 * phpcs:disable Squiz.Commenting.FunctionComment.SpacingAfterParamType
 * @phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch
 * @phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Helper;

use Closure;
use ArrayAccess;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionException;
use ReflectionFunctionAbstract;
use Psr\Container\ContainerExceptionInterface;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Pool\Param;
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Error\BadResolverArgument;

readonly class MethodResolver {
	/** @param Stack&ArrayAccess<string,Binding> $bindings */
	public function __construct(
		private Container $app,
		private Event $event,
		private Stack $bindings = new Stack(),
	) {}

	public function bind( Closure|string $id, Closure $cb ): void {
		$this->bindings->set( key: static::normalize( $id ), value: new Binding( concrete: $cb ) );
	}

	public function hasBinding( Closure|string $id ): bool {
		return $this->bindings->has( key: static::normalize( $id ) );
	}

	public function fromBinding( string $id, object $resolvedObject ): mixed {
		return ( $this->bindings[ $id ]->concrete )( $resolvedObject, $this->app );
	}

	/**
	 * @param array<string,mixed> $params The method's injected parameters.
	 * @throws BadResolverArgument When method cannot be resolved or no `$default`.
	 */
	public function resolve( callable|string $cb, ?string $default, array $params = array() ): mixed {
		if ( ! is_string( $cb ) ) {
			return $this->defaultOrBound( $cb, default: fn() => $cb( ...$this->from( $cb, $params ) ) );
		}

		$default ??= method_exists( object_or_class: $cb, method: '__invoke' ) ? '__invoke' : null;

		return static::isNormalized( $cb ) || $default
			? $this->instantiateFrom( $cb, $default, $params )
			: throw BadResolverArgument::noMethod( class: $cb );
	}

	public function resolveContextual( callable $cb, Event $event, array $params ): mixed {
		$stack = array();
		$pool  = new Param();

		$pool->push( $params );

		// TODO: use this.
		$resolved = ( new ParamResolver( $this->app, $pool, $event ) )
			->resolve( static::reflector( $cb )->getParameters() );

		foreach ( static::reflector( $cb )->getParameters() as $param ) {
			$concrete = $this->app->getContextualFor( context: '$' . $param->getName() );
			$hasValue = null !== $concrete;

			if ( ! $param->isOptional() && ! $hasValue ) {
				// TODO: add exception class.
				$msg = sprintf(
					'The required "%s" during method call does not have contextual binding value.',
					(string) $param
				);

				throw new class( $msg ) implements ContainerExceptionInterface {};
			}

			if ( $param->isDefaultValueAvailable() && ! $hasValue ) {
				$stack[] = $param->getDefaultValue();

				continue;
			}

			$stack[] = is_callable( $concrete ) ? $concrete() : $this->app->get( $concrete );
		}//end foreach

		return $this->defaultOrBound( $cb, default: static fn() => $cb( ...$stack ) );
	}

	protected static function normalize( Closure|string $id ): string {
		return $id instanceof Closure ? Unwrap::forBinding( object: $id ) : $id;
	}

	protected function defaultOrBound( callable|string $cb, Closure $default ): mixed {
		return ! $this->hasBinding( $id = Unwrap::callback( $cb ) ) || ! is_array( $cb )
			? Unwrap::andInvoke( value: $default )
			: $this->fromBinding( $id, resolvedObject: $cb[0] );
	}

	/**
	 * @param array<string,mixed> $params The method's injected parameters.
	 * @throws BadResolverArgument When neither method nor entry is resolvable.
	 */
	protected function instantiateFrom( string $cb, ?string $method, array $params ): mixed {
		$parts = static::extractFrom( $cb );

		// We'll reach here if either $method or $parts[1] exists. Verifying just in case...
		if ( null === ( $method ??= $parts[1] ?? null ) ) {
			throw BadResolverArgument::noMethod( class: $parts[0] );
		} elseif ( $ins = static::isInstantiatedClass( name: $parts[0] ) ) {
			throw BadResolverArgument::instantiatedBeforehand( $this->app->getEntryFrom( $ins ), $method );
		}

		return ! is_callable( value: $cb = array( $this->app->get( id: $parts[0] ), $method ) )
			? throw BadResolverArgument::nonInstantiableEntry( id: $this->app->getEntryFrom( $parts[0] ) )
			: $this->defaultOrBound(
				cb: Unwrap::asString( object: $parts[0], methodName: $method ),
				default: fn() => $cb( ...$this->from( $cb, $params ) )
			);
	}

	/**
	 * @param array<string,mixed> $params The method's injected parameters.
	 * @return mixed[]
	 * @throws ReflectionException When the class or method does not exist. If `$cb` is
	 *                             a function, when the function does not exist.
	 */
	protected function from( callable|string $cb, array $params ): array {
		$resolver = new ParamResolver( $this->app, $pool = new Param(), $this->event );

		$pool->push( $params );

		return $resolver->resolve( dependencies: static::reflector( $cb )->getParameters() );
	}

	/**
	 * @return ReflectionFunctionAbstract
	 * @throws ReflectionException When the class or method does not exist.
	 *                             If `$cb` is function, when the function does not exist.
	 */
	protected static function reflector( callable|string $cb ): ReflectionFunctionAbstract {
		$args = match ( true ) {
			is_string( $cb ) && static::isNormalized( $cb ) => static::extractFrom( string: $cb ),
			is_object( $cb ) && ! $cb instanceof Closure    => array( $cb, '__invoke' ),
			default                                         => $cb
		};

		return is_array( $args ) ? new ReflectionMethod( ...$args ) : new ReflectionFunction( $args );
	}

	protected static function isNormalized( string $cb ): bool {
		return str_contains( haystack: $cb, needle: '::' );
	}

	/** @return string[] */
	protected static function extractFrom( string $string, string $separator = '::' ): array {
		return explode( $separator, $string, limit: 2 );
	}

	protected static function isInstantiatedClass( string $name ): ?string {
		$parts = static::extractFrom( string: $name, separator: '@' );

		return is_numeric( value: $parts[1] ?? false ) ? $parts[0] : null;
	}
}
