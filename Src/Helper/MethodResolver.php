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
		$this->bindings->set( key: static::keyFrom( $id ), value: new Binding( concrete: $cb ) );
	}

	public function hasBinding( Closure|string $id ): bool {
		return $this->bindings->has( key: static::keyFrom( $id ) );
	}

	public function fromBinding( string $id, object $resolvedObject ): mixed {
		return ( $this->bindings[ $id ]->concrete )( $resolvedObject, $this->app );
	}

	/**
	 * @param array<string,mixed> $params The method's injected parameters.
	 * @throws BadResolverArgument When method cannot be resolved or no `$default`.
	 */
	public function resolve( callable|string $cb, ?string $default, array $params = array() ): mixed {
		if ( is_string( $cb ) ) {
			return $this->instantiateFrom( $cb, $default, $params );
		}

		[ $class, $method ] = Unwrap::callback( $cb, asArray: true );
		$id                 = $method ? Unwrap::asString( $class, $method ) : $class;

		return ! is_string( $class ) // The $class is never expected to be a string. Just in case...
			? $this->resolveFrom( $id, $cb, obj: $class, params: $params, args: array( $class, $method ) )
			: $this->instantiateFrom( $class, $method, $params );
	}

	public static function getArtefact( callable|string $from ): string {
		return ! static::isInstantiatedClass( $name = static::keyFrom( id: $from ) )
			? Unwrap::partsFrom( string: $name )[0]
			: $name;
	}

	protected static function keyFrom( callable|string $id ): string {
		return is_string( value: $id ) ? $id : Unwrap::callback( cb: $id );
	}

	/**
	 * @param array<string,mixed>         $params
	 * @param ?array<object|string,string $args
	 */
	protected function resolveFrom(
		string $id,
		callable $cb,
		?object $obj,
		array $params,
		?array $args = null
	): mixed {
		return $this->hasBinding( $id ) && null !== $obj
			? $this->fromBinding( $id, resolvedObject: $obj )
			: Unwrap::andInvoke( $cb, ...$this->dependenciesFrom( $params, cb: $args ?? $cb ) );
	}

	/**
	 * @param array<string,mixed> $params The method's injected parameters.
	 * @throws BadResolverArgument When neither method nor entry is resolvable.
	 */
	protected function instantiateFrom( string $cb, ?string $method, array $params ): mixed {
		$parts    = Unwrap::partsFrom( string: $cb );
		$method ??= method_exists( $cb, method: '__invoke' ) ? '__invoke' : ( $parts[1] ?? null );
		$callable = $this->instantiatedClassFrom( class: $parts[0], method: $method );
		$id       = Unwrap::asString( object: $parts[0], methodName: $method );

		return $this->resolveFrom( $id, cb: $callable, obj: $callable[0], params: $params );
	}

	// phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.Missing
	/** @return array{0:object,1:string} */
	protected function instantiatedClassFrom( string $class, ?string $method ): array {
		if ( null === $method ) {
			throw BadResolverArgument::noMethod( class: $class );
		} elseif ( $ins = static::isInstantiatedClass( name: $class ) ) {
			throw BadResolverArgument::instantiatedBeforehand( $this->app->getEntryFrom( $ins ), $method );
		} elseif ( ! is_callable( $value = array( $this->app->get( id: $class ), $method ) ) ) {
			throw BadResolverArgument::nonInstantiableEntry( id: $this->app->getEntryFrom( $class ) );
		}

		return $value;
	}

	/**
	 * @param array<string,mixed> $params
	 * @return mixed[]
	 */
	protected function dependenciesFrom( array $params, callable $cb ): array {
		$resolver = new ParamResolver( $this->app, $pool = new Param(), $this->event );

		$pool->push( $params );

		return $resolver->resolve( dependencies: static::reflectorFrom( $cb )->getParameters() );
	}

	/**
	 * @return ReflectionFunctionAbstract
	 * @throws ReflectionException When the class or method does not exist.
	 *                             If `$cb` is function, when the function does not exist.
	 */
	protected static function reflectorFrom( callable $cb ): ReflectionFunctionAbstract {
		return is_array( $cb ) ? new ReflectionMethod( ...$cb ) : new ReflectionFunction( $cb );
	}

	protected static function isNormalized( string $cb ): bool {
		return str_contains( haystack: $cb, needle: '::' );
	}

	protected static function isInstantiatedClass( string $name ): ?string {
		$parts = Unwrap::partsFrom( string: $name, separator: '@' );

		return isset( $parts[1] ) && is_numeric( value: Unwrap::partsFrom( $parts[1] )[0] ?? false )
			? $parts[0]
			: null;
	}
}
