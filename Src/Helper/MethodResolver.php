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
use TheWebSolver\Codegarage\Lib\Container\Error\BadResolverArgument;

readonly class MethodResolver {
	/** @var array{0:object|string,1:string} */
	private array $cb;

	public function __construct(
		private Container $app,
		private Event $event,
		private Param $pool = new Param()
	) {}

	public function with( object|string $classOrInstance, string $method ): static {
		$this->cb = func_get_args();

		return $this;
	}

	/** @return ?array{0:object|string,1:string} */
	public function unwrappedCallback(): ?array {
		return $this->cb ?? null;
	}

	/**
	 * @param array<string,mixed> $params The method's injected parameters.
	 * @throws BadResolverArgument When method cannot be resolved or no `$default`.
	 */
	public function resolve( callable|string $cb, ?string $default, array $params = array() ): mixed {
		$this->pool->push( value: $params );

		if ( is_string( $cb ) ) {
			return $this->instantiateFrom( $cb, $default );
		}

		[ $cls, $method ] = $this->cb ??= Unwrap::callback( $cb, asArray: true );
		$resolved         = ! is_string( $cls ) // The "$cls" is string at this point. Checking just in case...
			? $this->resolveFrom( id: $method ? Unwrap::asString( ...$this->cb ) : $cls, cb: $cb, obj: $cls )
			: $this->instantiateFrom( $cls, $method );

		$this->pool->pull();

		return $resolved;
	}

	public static function getArtefact( callable|string $from ): string {
		return ! static::isInstantiatedClass( $name = static::keyFrom( id: $from ) )
			? Unwrap::partsFrom( string: $name )[0]
			: $name;
	}

	public static function keyFrom( callable|string $id ): string {
		return is_string( value: $id ) ? $id : Unwrap::callback( cb: $id );
	}

	protected function resolveFrom( string $id, callable $cb, ?object $obj = null ): mixed {
		return $this->app->hasBinding( $id )
			? ( $this->app->getBinding( $id )->concrete )( $obj ?? $cb[0], $this->app )
			: Unwrap::andInvoke( $cb, ...$this->dependenciesFrom( cb: $this->cb ?? $cb ) );
	}

	protected function instantiateFrom( string $cb, ?string $method ): mixed {
		$parts  = Unwrap::partsFrom( string: $cb );
		$method = $parts[1] ?? $method ?? ( method_exists( $cb, '__invoke' ) ? '__invoke' : null );

		if ( null === $method ) {
			throw BadResolverArgument::noMethod( class: $parts[0] );
		} elseif ( $class = static::isInstantiatedClass( name: $parts[0] ) ) {
			throw BadResolverArgument::instantiatedBeforehand( $class, $method );
		} elseif ( ! is_callable( $om = array( $this->app->get( id: $parts[0] ), $method ) ) ) {
			throw BadResolverArgument::nonInstantiableEntry( id: $parts[0] );
		}

		return $this->resolveFrom( id: Unwrap::asString( $parts[0], $method ), cb: $om );
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
		$parts = Unwrap::partsFrom( string: $name, separator: '#' );

		return isset( $parts[1] ) // $parts[0] => classname, $parts[1] => ?spl_object_id()::methodName.
			&& is_numeric( value: Unwrap::partsFrom( $parts[1] )[0] ) ? $parts[0] : null;
	}
}
