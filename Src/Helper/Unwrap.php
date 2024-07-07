<?php
/**
 * Performs unwrapping for various container APIs.
 *
 * @package TheWebSolver\Codegarage\Container
 *
 * @phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber -- Exact number is vague.
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Helper;

use Closure;
use TypeError;
use LogicException;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;

class Unwrap {
	/** @return mixed[] */
	public static function asArray( mixed $thing ): array {
		return is_array( $thing ) ? $thing : array( $thing );
	}

	public static function asString( object|string $object, string $methodName = '' ): string {
		return self::toString(
			object: is_string( $object ) ? $object : $object::class . '@' . spl_object_id( $object ),
			methodName: $methodName
		);
	}

	/**
	 * @return string|array{0:object,1:string}
	 * @throws TypeError When static class member or a lambda function given as closure.
	 * @phpstan-return ($asArray is true ? array{0:object,1:string} : string)
	 */
	public static function closure( Closure $closure, bool $asArray = false ) {
		$source = new ReflectionFunction( $closure );

		if ( $result = self::asInstance( $source ) ) {
			return $asArray ? $result[0] : $result[1];
		}

		if ( $result = self::asStatic( $source, $closure ) ) {
			return $asArray ? $result[0] : $result[1];
		}

		if ( $result = self::asFirstClassFunc( $source, $closure ) ) {
			return $asArray ? $result[0] : $result[1];
		}

		throw new TypeError(
			'Cannot unwrap closure. Currently, only supports non-static class members/'
			. 'functions/methods and named functions.'
		);
	}

	/** @throws TypeError When static class member or a lambda function given as closure. */
	public static function closureAsString( Closure $closure ): string {
		return self::closure( $closure, asArray: false );
	}

	/**
	 * @throws LogicException When method name not given if `$object` is a class instance.
	 * @throws TypeError      When first-class callable was not created using non-static method.
	 */
	public static function forBinding( object|string $object, string $methodName = '' ): string {
		if ( is_string( $object ) ) {
			return self::asString( $object, $methodName );
		}

		if ( ! $object instanceof Closure ) {
			return method_exists( $object, $methodName )
				? self::asString( $object, $methodName ) // An instance and it's method name.
				: throw new LogicException(
					sprintf( 'Method name must be provided to create ID for class "%s".', $object::class )
				);
		}

		if ( $scoped = self::asInstance( source: new ReflectionFunction( $object ), binding: true ) ) {
			return self::asString( ...$scoped[0] );
		}

		throw new TypeError(
			'Method binding only accepts first-class callable of a named function or'
			. ' a non-static method. Alternatively, pass an instantiated object as'
			. ' param [#1] "$object" & its method name as param [#2] "$methodName".'
		);
	}

	public static function paramTypeFrom( ReflectionParameter $reflection ): ?string {
		$type = $reflection->getType();

		if ( ! $type instanceof ReflectionNamedType || $type->isBuiltin() ) {
			return null;
		}

		$name = $type->getName();

		return null === ( $class = $reflection->getDeclaringClass() ) ? $name : match ( $name ) {
			'self'   => $class->getName(),
			'parent' => $class->getParentClass()->getName(),
			default  => $name
		};
	}

	public static function andInvoke( mixed $value, mixed ...$args ): mixed {
		return $value instanceof Closure ? $value( ...$args ) : $value;
	}

	/**
	 * @param callable|string $callback Either a valid callback or a normalized
	 *                                  string using `Unwrap::asString()`.
	 */
	public static function callback( callable|string $callback ): string {
		return match ( true ) {
			default                      => $callback,
			is_array( $callback )        => self::forBinding( ...$callback ),
			$callback instanceof Closure => self::forBinding( $callback )
		};
	}

	/** @return ?array{0:array{0:object,1:string},1:string} */
	private static function asInstance( ReflectionFunction $source, bool $binding = false ): ?array {
		if ( ! $object = $source->getClosureThis() ) {
			return null;
		}

		$name = $source->getName();

		if ( $binding && str_contains( haystack: $name, needle: '{closure}' ) ) {
			return null;
		}

		return array( array( $object, $name ), self::toString( $object::class, $name ) );
	}

	private static function asStatic( ReflectionFunction $source, Closure $closure ): ?array {
		if ( ! $class = $source->getClosureScopeClass() ) {
			return null;
		}

		$method = $source->getName();

		return array( array( $closure, $method ), self::toString( $class->getName(), $method ) );
	}

	private static function asFirstClassFunc( ReflectionFunction $source, Closure $closure ): ?array {
		if ( '{closure}' === ( $name = $source->getShortName() ) ) {
			return null;
		}

		return array( array( $name ), $name );
	}

	private static function toString( string $object, string $methodName ): string {
		return "{$object}::{$methodName}";
	}

	/** @return string[] */
	public static function partsFrom( string $string, string $separator = '::' ): array {
		return explode( $separator, $string, limit: 2 );
	}

	private function __construct() {}
}
