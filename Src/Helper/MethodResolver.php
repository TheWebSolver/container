<?php
/**
 * Bound method.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Helper;

use Closure;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionException;
use ReflectionParameter;
use InvalidArgumentException;
use ReflectionFunctionAbstract;
use Psr\Container\ContainerExceptionInterface;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Pool\MethodBind;
use TheWebSolver\Codegarage\Lib\Container\Data\MethodBinding;

/**
 * Auto-wire binding method or function/closure to inject dependencies for the provided class.
 */
class MethodResolver {
	public function __construct( private MethodBind $bindings = new MethodBind() ) {}

	/**
	 * @throws LogicException When method name not given if `$object` is a class instance.
	 * @throws TypeError      When first-class callable was not created using non-static method.
	 */
	public function bind( Closure|string $abstract, Closure $callback ): void {
		$this->bindings->set(
			key: $abstract instanceof Closure ? Unwrap::forBinding( object: $abstract ) : $abstract,
			value: new MethodBinding( $callback )
		);
	}

	public function hasBinding( string $id ): bool {
		return $this->bindings->has( key: $id );
	}

	/** @return mixed The method result, or false on error. */
	public function getBinding( string $id, mixed $instance ): mixed {
		return $this->hasBinding( $id )
			? ( $this->bindings->get( key: $id )->concrete )( $instance, $this )
			: false;
	}

	/**
	 * Calls the given closure or class method and inject its dependencies.
	 *
	 * @param Container                $container The container instance.
	 * @param callable|callable-string $cb        The closure or string with `{$class}@{$method}` format.
	 * @param array<string, mixed>     $params    The method/function parameters.
	 * @param ?string                  $default   The fallback method/function.
	 * @return mixed
	 * @throws ReflectionException      When the class or method does not exist.
	 *                                  If `$cb` is function, when the function does not exist.
	 * @throws InvalidArgumentException When method cannot be resolved and passed $default is `null`.
	 * @since 1.0
	 */
	public function resolve(
		Container $container,
		$cb,
		array $params = array(),
		string $default = null
	) {
		if ( is_string( $cb ) && ! $default && method_exists( $cb, '__invoke' ) ) {
			$default = '__invoke';
		}

		if ( is_string( $cb ) && ( static::with_at_sign( $cb ) || $default ) ) {
			return $this->call_class( $container, $cb, $params, $default );
		}

		$default = static fn() => $cb(
			...array_values( static::get_dependencies( $container, $cb, $params ) )
		);

		return $this->call( $cb, $default );
	}

	/**
	 * Calls a string reference to a class using Class@method syntax.
	 *
	 * @param Container $container The container instance.
	 * @param string    $target    The string in a `{$class}@{$method}` format.
	 * @param mixed[]   $params    The method/function parameters.
	 * @param ?string   $default   The fallback method/function.
	 * @return mixed
	 * @throws InvalidArgumentException When method cannot be resolved and passed $default is `null`.
	 * @since 1.0
	 */
	protected function call_class(
		Container $container,
		string $target,
		array $params = array(),
		string $default = null
	) {
		$segments = explode( '@', $target );

		// We will assume an @ sign is used to delimit the class name from the method
		// name. We will split on this @ sign and then build a callable array that
		// we can pass right back into the "call" method for dependency binding.
		$method = count( $segments ) === 2 ? $segments[1] : $default;

		if ( is_null( $method ) ) {
			throw new InvalidArgumentException( 'Method not provided.' );
		}

		if ( ! is_callable( array( $container->make( $segments[0] ), $method ), false, $object ) ) {
			throw new InvalidArgumentException( 'Uninstantiable concrete provided.' );
		}

		return $this->call( $object, $params );
	}

	/**
	 * Calls the given class method and inject its dependencies using contextual bindings.
	 *
	 * Only resolves contextual bindings whose concrete is passed as "class@method".
	 * Beware that during contextual binding, the parameter position must
	 * be maintained and should be passed in the same order.
	 *
	 * Parameters that have default values must only come after required parameters.
	 * Parameter that has default value will be skipped if cannot
	 * be resolved from the contextual binding.
	 *
	 * @param Container                   $container The container instance.
	 * @param array<string|object,string> $cb        The object method as callback.
	 * @throws ContainerExceptionInterface When required param has no contextual binding value.
	 * @since 1.0
	 * @example usage
	 * ```
	 * app()->when( "someClass@invocableMethod" )
	 *  ->needs('$firstParam')
	 *  ->give('AnotherClassNameToResolve');
	 *
	 *  app()->when( "someClass@invocableMethod" )
	 *  ->needs('$secondParam')
	 *  ->give(static fn():array => array('An', 'Array', 'Value'));
	 *
	 * // ...
	 *
	 * app()->call(array($someClass, 'invocableMethod'));
	 * ```
	 */
	public function resolveContextual( Container $container, $cb ) {
		$stack = array();

		foreach ( static::reflector( $cb )->getParameters() as $param ) {
			$concrete = $container->getContextual( id: '$' . $param->getName() );
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

			$stack[] = is_callable( $concrete ) ? $concrete() : $container->make( $concrete );
		}//end foreach

		return $this->call( $cb, default: static fn() => $cb( ...$stack ) );
	}

	protected function call( callable $cb, mixed $default ): mixed {
		if ( ! is_array( $cb ) ) {
			return Unwrap::andInvoke( $default );
		}

		$method = Unwrap::callback( $cb );

		return $this->hasBinding( $method )
			? $this->getBinding( $method, $cb[0] )
			: Unwrap::andInvoke( $default );
	}

	/**
	 * Gets all dependencies for a given method.
	 *
	 * @param Container       $container The container instance.
	 * @param callable|string $cb        The callback either as `[$class, $method]` array or a function.
	 * @param mixed[]         $params    The method/function parameters.
	 * @return mixed[]
	 * @throws ReflectionException When the class or method does not exist.
	 *                             If `$cb` is function, when the function does not exist.
	 * @since 1.0
	 */
	protected static function get_dependencies(
		Container $container,
		$cb,
		array $params = array()
	): array {
		$deps = array();

		foreach ( static::reflector( $cb )->getParameters() as $param ) {
			static::walk_param( $container, $param, $params, $deps );
		}

		return array_merge( $deps, array_values( $params ) );
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
				$variadic = $container->make( $class_name );
				$deps     = array_merge( $deps, is_array( $variadic ) ? $variadic : array( $variadic ) );
			} else {
				$deps[] = $container->make( $class_name );
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

			throw new class( $msg ) implements ContainerExceptionInterface{};
		}//end if
	}

	/**
	 * Determines if the given string is in Class@method syntax.
	 *
	 * @param mixed $cb The callback.
	 * @return bool
	 * @since 1.0
	 */
	protected static function with_at_sign( $cb ): bool {
		return is_string( $cb ) && ( strpos( $cb, '@' ) !== false );
	}
}
