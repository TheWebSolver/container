<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Helper;

use ReflectionMethod;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use TheWebSolver\Codegarage\Container\Container;
use TheWebSolver\Codegarage\Container\Pool\Artefact;
use TheWebSolver\Codegarage\Container\Event\BuildingEvent;
use TheWebSolver\Codegarage\Container\Traits\DependencySetter;
use TheWebSolver\Codegarage\Container\Error\BadResolverArgument;
use TheWebSolver\Codegarage\Container\Traits\EventDispatcherSetter;

class MethodResolver {
	/** @use EventDispatcherSetter<BuildingEvent> */
	use EventDispatcherSetter, DependencySetter;

	protected ?string $default;
	protected string $context;
	/** @var callable|string */
	protected $callback;

	public function __construct(
		protected readonly Container $app,
		protected readonly Artefact $artefact = new Artefact()
	) {}

	/**
	 * @param callable|string $callback      The callable or a string representation of invoked method.
	 * @param ?string         $defaultMethod The default method name. Defaults to `__invoke()`.
	 */
	public function withCallback( callable|string $callback, ?string $defaultMethod = null ): static {
		$this->callback = $callback;
		$this->default  = $defaultMethod;

		return $this;
	}

	/** @throws BadResolverArgument When method cannot be resolved or no `$default`. */
	public function resolve(): mixed {
		if ( is_string( $this->callback ) ) {
			$this->context = static::artefactFrom( $this->callback );

			return $this->instantiateFrom( $this->callback );
		}

		$unwrapped              = Unwrap::forBinding( ( $this->callback )( ... ), asArray: true );
		$this->context          = static::artefactFrom( $unwrapped );
		[ $cb, $this->default ] = $unwrapped;
		$resolved               = is_string( $cb )
			? $this->instantiateFrom( $cb )
			: $this->resolveFrom( cb: $unwrapped, obj: $cb ); // @phpstan-ignore-line

		return $resolved;
	}

	public static function keyFrom( callable|string $id ): string {
		return is_string( $key = Unwrap::callback( cb: $id ) ) ? $key : throw new BadResolverArgument(
			sprintf( 'Unable to generate key from "%s".', is_string( $id ) ? $id : 'Closure' )
		);
	}

	protected function resolveFrom( callable $cb, object $obj ): mixed {
		return ( $bound = $this->app->getBinding( $this->context )?->material )
			? Unwrap::andInvoke( $bound, $obj, $this->app )
			: Unwrap::andInvoke( $cb, ...$this->dependenciesFrom( $cb ) );
	}

	protected function instantiateFrom( string $cb ): mixed {
		$parts  = Unwrap::partsFrom( string: $cb );
		$method = $parts[1] ?? $this->default ?? ( method_exists( $cb, '__invoke' ) ? '__invoke' : null );

		if ( null === $method ) {
			throw BadResolverArgument::noMethod( class: $parts[0] );
		} elseif ( $instantiatedClassName = static::instantiatedClass( name: $parts[0] ) ) {
			throw BadResolverArgument::instantiatedBeforehand( $instantiatedClassName, $method );
		} elseif ( ! is_callable( $callable = $this->makeCallableFrom( $id = $parts[0], $method ) ) ) {
			throw BadResolverArgument::nonInstantiableEntry( id: $id );
		}

		$this->context = Unwrap::asString( $parts[0], $method );

		return $this->resolveFrom( $callable, obj: $callable[0] );
	}

	/** @return mixed[] */
	protected function dependenciesFrom( callable $cb ): array {
		if ( ! $this->artefact->has( $this->context ) ) {
			$this->artefact->push( $this->context );
		}

		$resolved = ( new ParamResolver( $this->app ) )
			->withParameter( $this->dependencies, static::reflector( of: $cb )->getParameters() )
			->usingEventDispatcher( $this->dispatcher )
			->resolve();

		if ( $this->artefact->has( $this->context ) ) {
			$this->artefact->pull();
		}

		return $resolved;
	}

	protected static function reflector( callable $of ): ReflectionFunctionAbstract {
		return is_array( $of ) ? new ReflectionMethod( ...$of ) : new ReflectionFunction( $of( ... ) );
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

	/**
	 * @return array{0:object,1:string}
	 * @throws NotFoundExceptionInterface When entry with given `$id` was not found in the container.
	 * @throws ContainerExceptionInterface When cannot resolve concrete from the given `$id`.
	 */
	private function makeCallableFrom( string $id, string $method ): array {
		/** @var array{0:object,1:string} */
		$array = array( $this->app->get( $id ), $method );

		return $array;
	}
}
