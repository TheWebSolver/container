<?php
/**
 * Bound method.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Helper;

use Closure;
use ArrayAccess;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionException;
use ReflectionParameter;
use InvalidArgumentException;
use ReflectionFunctionAbstract;
use Psr\Container\ContainerExceptionInterface;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Pool\Param;
use TheWebSolver\Codegarage\Lib\Container\Pool\Stack;
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Error\BadResolverArgument;

class MethodResolver {
	// phpcs:ignore Squiz.Commenting.FunctionComment.SpacingAfterParamType, Squiz.Commenting.FunctionComment.ParamNameNoMatch
	/** @param Stack&ArrayAccess<string,Binding> $bindings */
	public function __construct( private Stack $bindings = new Stack() ) {}

	/**
	 * @throws LogicException When method name not given if `$object` is a class instance.
	 * @throws TypeError      When first-class callable was not created using non-static method.
	 */
	public function bind( Closure|string $abstract, Closure $callback ): void {
		$this->bindings->set(
			key: $abstract instanceof Closure ? Unwrap::forBinding( object: $abstract ) : $abstract,
			value: new Binding( $callback )
		);
	}

	public function hasBinding( string $id ): bool {
		return $this->bindings->has( key: $id );
	}

	/** @return mixed The method result, or false on error. */
	public function fromBinding( string $id, mixed $instance ): mixed {
		return ( $this->bindings[ $id ]->concrete )( $instance, $this );
	}

	/**
	 * @param Container                $app      The container instance.
	 * @param callable|callable-string $callback Closure, array, or pre-composed `Unwrap::toString()` value (*preferred*).
	 * @param array<string,mixed>      $params   The method/function parameters.
	 * @throws ReflectionException      When the class or method does not exist.
	 *                                  If `$callback` is function, when the function does not exist.
	 * @throws InvalidArgumentException When method cannot be resolved and passed $default is `null`.
	 */
	public function resolve(
		Container $app,
		callable|string $callback,
		array $params = array(),
		?string $default = null
	): mixed {
		if ( is_string( $callback ) ) {
			$default = ! $default && method_exists( $callback, method: '__invoke' ) ? '__invoke' : null;

			if ( static::isValid( $callback ) || $default ) {
				return $this->lazy( $params, $app, $callback, $default );
			}
		}

		$default = static fn() => $callback(
			...array_values( static::dependenciesFrom( $params, $app, $callback ) )
		);

		return $this->fromBindingOrDefault( $callback, $default );
	}

	/**
	 * @param array<string,mixed> $params The method/function parameters.
	 * @throws BadResolverArgument When neither method nor entry is resolvable.
	 */
	protected function lazy( array $params, Container $app, string $cb, ?string $method ): mixed {
		$parts = explode( '::', $cb );

		if ( null === ( $method = $parts[1] ?? $method ) ) {
			throw BadResolverArgument::noMethod( class: $parts[0] );
		}

		return ! is_callable( $callback = array( $app->get( $parts[0] ), $method ) )
			? BadResolverArgument::nonInstantiableEntry( id: $app->getEntryFrom( $parts[0] ) )
			: $this->resolve( $app, $callback, $params );
	}

	/**
	 * Calls the given class method and inject its dependencies using contextual binding.
	 *
	 * Beware that during contextual binding, the parameter position must
	 * be maintained and should be passed in the same order.
	 *
	 * Parameters that have default values must only come after required parameters.
	 * Parameter that has default value will be skipped if cannot
	 * be resolved from the contextual binding.
	 *
	 * @param array<string,mixed> $params
	 * @throws ContainerExceptionInterface When required param has no contextual binding value.
	 */
	public function resolveContextual(
		array $params,
		Container $app,
		callable $callback,
		Event $event,
	): mixed {
		$stack = array();
		$pool  = new Param();

		$pool->push( $params );

		// TODO: use this.
		$resolved = ( new ParamResolver( $app, $pool, $event ) )
			->resolve( static::reflector( $callback )->getParameters() );

		foreach ( static::reflector( $callback )->getParameters() as $param ) {
			$concrete = $app->getContextualFor( context: '$' . $param->getName() );
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

			$stack[] = is_callable( $concrete ) ? $concrete() : $app->get( $concrete );
		}//end foreach

		return $this->fromBindingOrDefault( $callback, default: static fn() => $callback( ...$stack ) );
	}

	protected function fromBindingOrDefault( callable $callback, Closure $default ): mixed {
		return ! is_array( $callback ) || ! $this->hasBinding( $id = Unwrap::callback( $callback ) )
			? Unwrap::andInvoke( value: $default )
			: $this->fromBinding( $id, instance: $callback[0] );
	}

	/**
	 * Gets all dependencies for a given method.
	 *
	 * @param mixed[] $params The method/function parameters.
	 * @return mixed[]
	 * @throws ReflectionException When the class or method does not exist.
	 *                             If `$cb` is function, when the function does not exist.
	 * @since 1.0
	 */
	protected static function dependenciesFrom( array $params, Container $app, $cb ): array {
		$deps = array();

		foreach ( static::reflector( $cb )->getParameters() as $param ) {
			static::walk_param( $app, $param, $params, $deps );
		}

		return array( ...$deps, ...array_values( $params ) );
	}

	/**
	 * @return ReflectionFunctionAbstract
	 * @throws ReflectionException When the class or method does not exist.
	 *                             If `$cb` is function, when the function does not exist.
	 */
	protected static function reflector( callable|string $cb ): ReflectionFunctionAbstract {
		if ( is_string( $cb ) && ( strpos( $cb, '::' ) !== false ) ) {
			$cb = explode( '::', $cb );
		} elseif ( is_object( $cb ) && ! $cb instanceof Closure ) {
			$cb = array( $cb, '__invoke' );
		}

		return is_array( $cb ) ? new ReflectionMethod( ...$cb ) : new ReflectionFunction( $cb );
	}

	/**
	 * Traverses over given call parameter to get its dependencies.
	 *
	 * @param Container           $container The container.
	 * @param ReflectionParameter $param     The method/function parameter.
	 * @param mixed[]             $params    The method/function parameters.
	 * @param mixed[]             $deps      The dependencies.
	 * @throws ContainerExceptionInterface When cannot resolve dependency in the given class method or function.
	 * @since 1.0
	 */
	protected static function walk_param(
		Container $container,
		ReflectionParameter $param,
		array &$params,
		array &$deps
	): void {
		if ( array_key_exists( $param_name = $param->getName(), $params ) ) {
			$deps[] = $params[ $param_name ];

			unset( $params[ $param_name ] );
		} elseif ( ! is_null( $class_name = Unwrap::paramTypeFrom( $param ) ) ) {
			if ( array_key_exists( $class_name, $params ) ) {
				$deps[] = $params[ $class_name ];

				unset( $params[ $class_name ] );
			} elseif ( $param->isVariadic() ) {
				$variadic = $container->get( $class_name );
				$deps     = array_merge( $deps, is_array( $variadic ) ? $variadic : array( $variadic ) );
			} else {
				$deps[] = $container->get( $class_name );
			}
		} elseif ( $param->isDefaultValueAvailable() ) {
			$deps[] = $param->getDefaultValue();
		} elseif (
			! $param->isOptional()
				&& ! array_key_exists( $param_name, $params )
				&& $class = $param->getDeclaringClass()
		) {
			$msg = sprintf(
				'Unable to resolve dependency parameter: %1$s in class: %2$s',
				$param,
				$class->getName()
			);

			// TODO: add exception class.
			throw new class( $msg ) implements ContainerExceptionInterface{};
		}//end if
	}

	protected static function isValid( string $callback ): bool {
		return str_contains( haystack: $callback, needle: '::' );
	}
}
