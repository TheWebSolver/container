<?php
/**
 * Method resolver for DI & Auto-wiring method calls.
 *
 * @package TheWebSolver\Codegarage\Container
 *
 * phpcs:disable Squiz.Commenting.FunctionComment.SpacingAfterParamType
 * @phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
 * @phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Helper;

use Closure;
use ArrayAccess;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Pool\Param;
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Error\BadResolverArgument;

readonly class MethodResolver {
	/** @var ?array{0:object|string,1:string} */
	private readonly ?array $callable;

	/** @param Stack&ArrayAccess<string,Binding> $bindings */
	public function __construct(
		private Container $app,
		private Event $event,
		private Stack $bindings = new Stack(),
		private Param $pool = new Param()
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
		$this->pool->push( $params );

		if ( is_string( $cb ) ) {
			return $this->instantiateFrom( $cb, $default );
		}

		$this->callable     = Unwrap::callback( $cb, asArray: true );
		[ $class, $method ] = $this->callable;
		$id                 = $method ? Unwrap::asString( $class, $method ) : $class;

		return ! is_string( $class ) // The $class is never expected to be a string. Just in case...
			? $this->resolveFrom( $id, $cb, obj: $class )
			: $this->instantiateFrom( $class, $method );
	}

	public static function getArtefact( callable|string $from ): string {
		return ! static::isInstantiatedClass( $name = static::keyFrom( id: $from ) )
			? Unwrap::partsFrom( string: $name )[0]
			: $name;
	}

	protected static function keyFrom( callable|string $id ): string {
		return is_string( value: $id ) ? $id : Unwrap::callback( cb: $id );
	}

	/** @param ?array<object|string,string $args */
	protected function resolveFrom( string $id, callable $cb, ?object $obj ): mixed {
		return $this->hasBinding( $id ) && null !== $obj
			? $this->fromBinding( $id, resolvedObject: $obj )
			: Unwrap::andInvoke( $cb, ...$this->dependenciesFrom( cb: $this->callable ?? $cb ) );
	}

	/**
	 * @param array<string,mixed> $params The method's injected parameters.
	 * @throws BadResolverArgument When neither method nor entry is resolvable.
	 */
	protected function instantiateFrom( string $cb, ?string $method ): mixed {
		$parts    = Unwrap::partsFrom( string: $cb );
		$method ??= method_exists( $cb, method: '__invoke' ) ? '__invoke' : ( $parts[1] ?? null );

		if ( null === $method ) {
			throw BadResolverArgument::noMethod( class: $parts[0] );
		} elseif ( $ins = static::isInstantiatedClass( name: $parts[0] ) ) {
			throw BadResolverArgument::instantiatedBeforehand( $this->app->getEntryFrom( $ins ), $method );
		} elseif ( ! is_callable( $callable = array( $this->app->get( id: $parts[0] ), $method ) ) ) {
			throw BadResolverArgument::nonInstantiableEntry( id: $this->app->getEntryFrom( $parts[0] ) );
		}

		$id = Unwrap::asString( object: $parts[0], methodName: $method );

		return $this->resolveFrom( $id, cb: $callable, obj: $callable[0] );
	}

	/** @return mixed[] */
	protected function dependenciesFrom( callable $cb ): array {
		return ( new ParamResolver( app: $this->app, pool: $this->pool, event: $this->event ) )
			->resolve( dependencies: static::reflectorFrom( $cb )->getParameters() );
	}

	protected static function reflectorFrom( callable $cb ): ReflectionFunctionAbstract {
		return is_array( $cb ) ? new ReflectionMethod( ...$cb ) : new ReflectionFunction( $cb );
	}

	protected static function isNormalized( string $cb ): bool {
		return str_contains( haystack: $cb, needle: '::' );
	}

	protected static function isInstantiatedClass( string $name ): ?string {
		$parts = Unwrap::partsFrom( string: $name, separator: '@' );

		return isset( $parts[1] ) // $parts[0] => classname, $parts[1] => spl_object_id.
			&& is_numeric( value: Unwrap::partsFrom( $parts[1] )[0] ) ? $parts[0] : null;
	}
}
