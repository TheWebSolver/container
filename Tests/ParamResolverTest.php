<?php
/**
 * Param Resolver test.
 *
 * @package TheWebSolver\Codegarage\Test
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Tests;

use Closure;
use Exception;
use ReflectionFunction;
use ReflectionParameter;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerExceptionInterface;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Pool\Param;
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Event\BuildingEvent;
use TheWebSolver\Codegarage\Lib\Container\Helper\ParamResolver;
use TheWebSolver\Codegarage\Lib\Container\Event\EventDispatcher;

class ParamResolverTest extends TestCase {
	private ?ParamResolver $resolve;

	/** @var array{0:Container&MockObject,1:Param&MockObject,2:EventDispatcher&MockObject} */
	private ?array $mockedArgs;

	protected function setUp(): void {
		$this->mockedArgs = array(
			$this->createMock( Container::class ),
			$this->createMock( Param::class ),
			$this->createMock( EventDispatcher::class ),
		);

		$this->resolve = new ParamResolver( ...$this->mockedArgs );
	}

	protected function tearDown(): void {
		$this->resolve    = null;
		$this->mockedArgs = null;
	}

	public function testParamResolveForUntypedParam(): void {
		[ $app ]    = $this->mockedArgs;
		$resolve    = $this->resolve;
		$param      = new ReflectionParameter( static function ( string $value = 'test' ) {}, param: 0 );
		$contextual = static function ( $app ): string {
			self::assertInstanceOf( Container::class, actual: $app );

			return 'from closure';
		};

		$variadicContextual = static fn (): array => array( 'one', 'two' );

		$app->expects( $this->exactly( 6 ) )
			->method( 'getContextualFor' )
			->with( '$value' )
			->willReturn( 'paramVal', $contextual, null, $variadicContextual, null, null );

		$this->assertSame( expected: 'paramVal', actual: $resolve->fromUntypedOrBuiltin( $param ) );
		$this->assertSame( expected: 'from closure', actual: $resolve->fromUntypedOrBuiltin( $param ) );
		$this->assertSame( expected: 'test', actual: $resolve->fromUntypedOrBuiltin( $param ) );

		$param = new ReflectionParameter( function: static function ( ...$value ) {}, param: 0 );

		$this->assertSame( array( 'one', 'two' ), actual: $resolve->fromUntypedOrBuiltin( $param ) );
		$this->assertSame( expected: array(), actual: $resolve->fromUntypedOrBuiltin( $param ) );

		$param = new ReflectionParameter( function: function ( $value ) {}, param: 0 );

		$this->expectException( ContainerExceptionInterface::class );
		$this->expectExceptionMessage(
			"Unable to resolve dependency parameter: {$param} in class: " . self::class . '.'
		);

		$resolve->fromUntypedOrBuiltin( $param );
	}

	/** @dataProvider provideVariousTypeHintedFunction */
	public function testParamResolverForTypeHintedParam(
		Closure $fn,
		Closure|string|null $contextual,
		mixed $expectedValue
	): void {
		[ $app ] = $this->mockedArgs;
		$param   = new ReflectionParameter( function: $fn, param: 0 );
		$type    = $param->getType()->getName();
		$error   = null === $contextual && null === $expectedValue
			? new class() extends Exception implements ContainerExceptionInterface {}
			: null;

		$app->expects( $this->once() )
			->method( 'getContextualFor' )
			->with( $type )
			->willReturn( $contextual );

		if ( null === $contextual ) {
			$appMocker = $app->expects( $this->once() )
				->method( 'get' )
				->with( $type );

			if ( $error ) {
				$appMocker->willThrowException( $error );

				$expectedValue = match ( true ) {
					$param->isDefaultValueAvailable() => $param->getDefaultValue(),
					$param->isVariadic()              => array(),
					default                           => $appMocker,
				};

				if ( $appMocker === $expectedValue ) {
					$this->expectException( ContainerExceptionInterface::class );
				}
			} else {
				$appMocker->willReturn( $expectedValue );
			}
		}//end if

		$this->assertSame( $expectedValue, actual: $this->resolve->fromTyped( $param, $type ) );
	}

	/** @return array<array{0:Closure,1:?string,2:mixed}> */
	public function provideVariousTypeHintedFunction(): array {
		$test = new self();

		return array(
			array( static function ( string $context ) {}, static fn() => 'from context', 'from context', null ),
			array( static function ( int $number ) {}, static fn () => 2 + 3, 5, null ),
			array( static function ( ParamResolverTest $typeHinted ) {}, null, $test ),
			array( static function ( string $defaultStr = 'val' ) {}, null, null ),
			array( static function ( array $defaultArr = array() ) {}, null, null ),
			array( static function ( string $noDefaultStr ) {}, null, null ),
			array( static function ( ?string $nullable = null ) {}, null, null ),
			array( static function ( ParamResolverTest ...$variadicDefaultAsArr ) {}, null, null ),
			array( static function ( int ...$no ) {}, static fn() => array( 1, 2 ), array( 1, 2 ), null ),
			array( static function ( ParamResolverTest ...$inst ) {}, static fn() => $test, $test, null ),
		);
	}

	public function testResolveWithTypedOrUntyped() {
		[ $app, $p, $dispatcher ] = $this->mockedArgs;
		$testFn                   = static function ( TestCase $class, ?string $text, ...$other ) {};
		$ref                      = new ReflectionFunction( $testFn );
		$pool                     = new Param();

		$pool->push(
			value: array(
				'text'  => 'injected value',
				'class' => $this->createStub( self::class ),
			)
		);

		$app->expects( $this->exactly( 0 ) )->method( 'get' );
		$app->expects( $this->once() )
			->method( 'getContextualFor' )
			->with( '$other' )
			->willReturn( null );

		$eventWithStringValue = $this->createMock( BuildingEvent::class );
		$eventWithStringValue
			->expects( $this->once() )
			->method( 'getBinding' )
			->willReturn( new Binding( 'event' ) );

		$eventWithArrayValue = $this->createMock( BuildingEvent::class );
		$eventWithArrayValue
			->expects( $this->once() )
			->method( 'getBinding' )
			->willReturn( new Binding( array( 2, array( 3 ), array( 4 ) ) ) );

		$dispatcher->expects( $this->exactly( 2 ) )
			->method( 'hasListeners' )
			->willReturn( true, true );

		$dispatcher
			->expects( $this->exactly( 2 ) )
			->method( 'dispatch' )
			->willReturn( $eventWithStringValue, $eventWithArrayValue );

		$resolved = ( new ParamResolver( $app, $pool, $dispatcher ) )->resolve( $ref->getParameters() );

		$this->assertCount( expectedCount: 3, haystack: $resolved );
		$this->assertInstanceOf( expected: self::class, actual: $resolved['class'] );
		$this->assertSame( expected: 'injected value', actual: $resolved['text'] );

		$testFn2 = static function ( TestCase $class, string $text, int ...$other ) {};
		$ref2    = new ReflectionFunction( $testFn2 );

		$pool->push( value: array( 'class' => $this->createStub( self::class ) ) );

		$resolved2 = ( new ParamResolver( $app, $pool, $dispatcher ) )->resolve( $ref2->getParameters() );

		$this->assertCount( expectedCount: 3, haystack: $resolved2 ); // Variadic is an array.
		$this->assertInstanceOf( expected: self::class, actual: $resolved2['class'] );
		$this->assertSame( expected: 'event', actual: $resolved2['text'] );
		$this->assertSame( expected: array( 2, array( 3 ), array( 4 ) ), actual: $resolved2['other'] );
	}

	public function testResolveContainerItself(): void {
		$expected    = $this->mockedArgs[0];
		$asInterface = static function ( ContainerInterface $app ) {};
		$param       = new ReflectionParameter( function: $asInterface, param: 0 );
		$actual      = $this->resolve->fromTyped( $param, type: $param->getType()->getName() );

		$this->assertSame( $expected, $actual );

		$asConcrete = static function ( Container $app ) {};
		$param      = new ReflectionParameter( function: $asConcrete, param: 0 );
		$actual     = $this->resolve->fromTyped( $param, type: $param->getType()->getName() );

		$this->assertSame( $expected, $actual );
	}
}
