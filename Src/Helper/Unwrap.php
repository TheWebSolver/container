<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Helper;

use Closure;
use TypeError;
use ReflectionClass;
use ReflectionFunction;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use TheWebSolver\Codegarage\Container\Error\LogicalError;

class Unwrap {
	/**
	 * @param T|T[] $thing
	 * @return T[]
	 * @template T
	 */
	public static function asArray( mixed $thing ): array {
		return is_array( $thing ) ? $thing : array( $thing );
	}

	public static function asString( object|string $object, string $methodName ): string {
		return self::toString(
			object: is_string( $object ) ? $object : $object::class . '#' . spl_object_id( $object ),
			methodName: $methodName
		);
	}

	/**
	 * @return ($asArray is true ? array{0:object,1:string} : string)
	 * @throws LogicalError When static class member or a lambda function given as closure.
	 */
	public static function closure( Closure $closure, bool $asArray = false ) {
		$source = new ReflectionFunction( $closure );
		$result = match ( true ) {
			! is_null( $instance = self::asInstance( $source ) )       => $instance,
			! is_null( $class = self::asStatic( $source, $closure ) )  => $class,
			! is_null( $callable = self::asFirstClassFunc( $source ) ) => $callable,
			default                                                    => throw LogicalError::unwrappableClosure()
		};

		return $asArray ? $result[0] : $result[1]; // @phpstan-ignore-line -- array offset OK.
	}

	/**
	 * @param class-string|object $object
	 * @return ($asArray is true ? array{0:string|object,1:string} : string)
	 * @throws LogicalError When method name not given if `$object` is a classname or an instance.
	 *                       When first-class callable was not created using non-static method.
	 */
	public static function forBinding(
		object|string $object,
		string $methodName = '',
		bool $asArray = false
	): string|array {
		if ( is_string( $object ) ) {
			return $methodName
				? ( $asArray ? array( $object, $methodName ) : self::asString( $object, $methodName ) )
				: throw LogicalError::noMethodNameForBinding( $object );
		}

		if ( ! $object instanceof Closure ) {
			return method_exists( $object, $methodName )
				? ( $asArray ? array( $object, $methodName ) : self::asString( $object, $methodName ) )
				: throw LogicalError::noMethodNameForBinding( $object::class );
		}

		if ( $scoped = self::asInstance( source: new ReflectionFunction( $object ), binding: true ) ) {
			return $asArray ? $scoped[0] : self::asString( ...$scoped[0] );
		}

		throw LogicalError::nonBindableClosure();
	}

	public static function paramTypeFrom( ReflectionParameter $reflection, bool $checkBuiltIn = true ): ?string {
		$type = $reflection->getType();

		if ( ! $type instanceof ReflectionNamedType || ( $checkBuiltIn && $type->isBuiltin() ) ) {
			return null;
		}

		$name = $type->getName();

		return null === ( $class = $reflection->getDeclaringClass() ) ? $name : match ( $name ) {
			'self'   => $class->getName(),
			'parent' => ( $parentClass = $class->getParentClass() ) ? $parentClass->getName() : $name,
			default  => $name
		};
	}

	public static function andInvoke( mixed $value, mixed ...$args ): mixed {
		return is_callable( $value ) ? $value( ...$args ) : $value;
	}

	/**
	 * @param (callable(TItem):TReturn)|string $cb Either a valid callback or a normalized
	 *                                       string using `Unwrap::asString()`.
	 * @return ($asArray is true ? array{0:(callable(TItem):TReturn)|object|string,1:string} : string|(callable(TItem):TReturn))
	 * @throws TypeError When `$cb` is a first-class callable of a static method.
	 * @template TItem
	 * @template TReturn
	 */
	public static function callback( callable|string $cb, bool $asArray = false ): callable|string|array {
		return match ( true ) {
			default                => $asArray ? array( $cb, '' ) : $cb,
			$cb instanceof Closure => self::forBinding( $cb, asArray: $asArray ),
			is_array( $cb )        => self::forBinding( $cb[0], $cb[1] ?? '', $asArray ),
			is_object( $cb )       => self::forBinding( $cb, '__invoke', $asArray )
		};
	}

	/**
	 * @param string           $string
	 * @param non-empty-string $separator
	 * @return array{0:string,1?:string}
	 */
	public static function partsFrom( string $string, string $separator = '::' ): array {
		return explode( $separator, $string, limit: 2 );
	}

	/**
	 * @throws ReflectionException When `$classname` is not a class-string.
	 * @throws LogicalError         When non-instantiable `$classname` given.
	 */
	// phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.WrongNumber -- Actual number is vague.
	public static function classReflection( string $classname ): ReflectionClass {
		return ! ( $classReflector = new ReflectionClass( $classname ) )->isInstantiable()
			? throw LogicalError::nonInstantiableClass( $classname )
			: $classReflector;
	}

	/** @return ?array{0:array{0:object,1:string},1:string} */
	private static function asInstance( ReflectionFunction $source, bool $binding = false ): ?array {
		if ( ! $object = $source->getClosureThis() ) {
			return null;
		}

		$name = $source->getName();

		return $binding && str_contains( haystack: $name, needle: '{closure}' )
			? null
			: array( array( $object, $name ), self::toString( $object::class, $name ) );
	}

	/** @return ?array{0:array{0:Closure,1:string},1:string} */
	private static function asStatic( ReflectionFunction $source, Closure $closure ): ?array {
		if ( ! $class = $source->getClosureScopeClass() ) {
			return null;
		}

		$method = $source->getName();

		return array( array( $closure, $method ), self::toString( $class->getName(), $method ) );
	}

	/** @return ?array{0:array{0:string},1:string} */
	private static function asFirstClassFunc( ReflectionFunction $source ): ?array {
		return '{closure}' !== ( $name = $source->getShortName() )
			? array( array( $name ), $name )
			: null;
	}

	private static function toString( string $object, string $methodName ): string {
		return "{$object}::{$methodName}";
	}

	private function __construct() {}
}
