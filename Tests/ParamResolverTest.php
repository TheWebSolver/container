<?php
/**
 * Param Resolver test.
 *
 * @package TheWebSolver\Codegarage\Test
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerExceptionInterface;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Pool\Param;
use TheWebSolver\Codegarage\Lib\Container\Helper\Event;
use TheWebSolver\Codegarage\Lib\Container\Helper\Unwrap;
use TheWebSolver\Codegarage\Lib\Container\Helper\ParamResolver;

class ParamResolverTest extends TestCase {
	public function testParamResolveForUntypedParam(): void {
		$container = $this->createMock( Container::class );
		$event     = $this->createMock( Event::class );
		$paramPool = $this->createMock( Param::class );
		$resolver  = new ParamResolver( $container, $paramPool, $event );
		$param     = new ReflectionParameter(
			function: function ( string $value = 'defaultVal' ) {},
			param: 0
		);

		$container->expects( $this->exactly( 5 ) )
			->method( 'getContextualFor' )
			->with( '$value' )
			->willReturn( 'paramVal', static fn(): string => 'from closure', null, null, null );

			$this->assertSame( expected: 'paramVal', actual: $resolver->fromUntyped( $param ) );
			$this->assertSame( expected: 'from closure', actual: $resolver->fromUntyped( $param ) );

			$this->assertSame( expected: 'defaultVal', actual: $resolver->fromUntyped( $param ) );

		$param = new ReflectionParameter( function: function ( string ...$value ) {}, param: 0 );

		$this->assertSame( expected: array(), actual: $resolver->fromUntyped( $param ) );

		$param = new ReflectionParameter( function: function ( string $value ) {}, param: 0 );

		$this->expectException( ContainerExceptionInterface::class );
		$this->expectExceptionMessage(
			"Unable to resolve dependency parameter: {$param} in class: " . self::class . '.'
		);

		$resolver->fromUntyped( $param );
	}

	public function testParamResolverForTypeHintedParam(): void {
		$container = $this->createMock( Container::class );
		$paramPool = $this->createMock( Param::class );
		$event     = $this->createMock( Event::class );
		$resolver  = new ParamResolver( $container, $paramPool, $event );
		$param     = new ReflectionParameter(
			function: function ( ParamResolverTest $test ) {},
			param: 0
		);

		$container->expects( $this->exactly( 1 ) )
			->method( 'getEntryFrom' )
			->with( self::class )
			->willReturn( self::class );

		$container->expects( $this->exactly( 1 ) )
			->method( 'get' )
			->with( self::class )
			->willReturn( new self() );

		$this->assertInstanceOf(
			actual: $resolver->fromType( $param, type: Unwrap::paramTypeFrom( $param ) ),
			expected: self::class
		);
	}

	public function testParamResolverForTypeHintedParamAsVariadic(): void {
		$container = $this->createMock( Container::class );
		$paramPool = $this->createMock( Param::class );
		$event     = $this->createMock( Event::class );
		$resolver  = new ParamResolver( $container, $paramPool, $event );
		$param     = new ReflectionParameter(
			function: function ( ParamResolverTest ...$test ) {},
			param: 0
		);

		$container->expects( $this->exactly( 3 ) )
			->method( 'getEntryFrom' )
			->with( self::class )
			->willReturn( self::class );

		$container->expects( $this->exactly( 3 ) )
			->method( 'getContextualFor' )
			->with( self::class )
			->willReturn(
				null,
				function ( Container $app ) {
					$this->assertInstanceOf( expected: MockObject::class, actual: $app );

					return $app->get( self::class );
				},
				function ( Container $app ) {
					$this->assertInstanceOf( expected: MockObject::class, actual: $app );

					return new self();
				}
			);

		$container->expects( $this->exactly( 2 ) )
			->method( 'get' )
			->with( self::class )
			->willReturn( new self() );

		$resolver->fromType( $param, type: Unwrap::paramTypeFrom( $param ) );
		$resolver->fromType( $param, type: Unwrap::paramTypeFrom( $param ) );
		$resolver->fromType( $param, type: Unwrap::paramTypeFrom( $param ) );
	}

	public function testParamResolverForInvalidTypeHintedParamAsVariadic(): void {
		$container = $this->createMock( Container::class );
		$paramPool = $this->createMock( Param::class );
		$event     = $this->createMock( Event::class );
		$resolver  = new ParamResolver( $container, $paramPool, $event );
		$param     = new ReflectionParameter( function: function ( string ...$test ) {}, param: 0 );

		$container->expects( $this->exactly( 1 ) )
			->method( 'getEntryFrom' )
			->with( 'string' )
			->willReturn( 'string' );

		$container->expects( $this->exactly( 1 ) )
			->method( 'get' )
			->with( 'string' )
			->willThrowException(
				new class() extends Exception implements ContainerExceptionInterface {}
			);

		$container->expects( $this->exactly( 1 ) )
			->method( 'getContextualFor' )
			->with( 'string' )
			->willReturn( null );

		$this->assertSame(
			actual: $resolver->fromType( $param, type: 'string' ),
			expected: array()
		);
	}
}
