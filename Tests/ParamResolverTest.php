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
use TheWebSolver\Codegarage\Lib\Container\Data\Binding;
use TheWebSolver\Codegarage\Lib\Container\Helper\Event;
use TheWebSolver\Codegarage\Lib\Container\Helper\ParamResolver;

class ParamResolverTest extends TestCase {
	private ?ParamResolver $resolve;

	/** @var array{0:Container&MockObject,1:Param&MockObject,2:Event&MockObject} */
	private ?array $mockedArgs;

	protected function setUp(): void {
		$this->mockedArgs = array(
			$this->createMock( Container::class ),
			$this->createMock( Param::class ),
			$this->createMock( Event::class ),
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
		[ $app, $p, $event ] = $this->mockedArgs;
		$testFn              = static function ( TestCase $class, ?string $text, ...$other ) {};
		$ref                 = new ReflectionFunction( $testFn );
		$pool                = new Param();

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

		$event
			->expects( $this->exactly( 2 ) )
			->method( 'fireDuringBuild' )
			->willReturn( new Binding( concrete: 'event' ), new Binding( concrete: array( 2, 3, 4 ) ) );

		$resolved = ( new ParamResolver( $app, $pool, $event ) )->resolve( $ref->getParameters() );

		[ $class, $injectedText /* ...{$other} is an empty array, so not included */ ] = $resolved;

		$this->assertCount( expectedCount: 2, haystack: $resolved );
		$this->assertEmpty( actual: $pool->getItems() );
		$this->assertInstanceOf( expected: self::class, actual: $class );
		$this->assertSame( expected: 'injected value', actual: $injectedText );

		$testFn2 = static function ( TestCase $class, string $text, int ...$other ) {};
		$ref2    = new ReflectionFunction( $testFn2 );

		$pool->push( value: array( 'class' => $this->createStub( self::class ) ) );

		$resolved2 = ( new ParamResolver( $app, $pool, $event ) )->resolve( $ref2->getParameters() );

		[ $class, $eventText /* ...[2, 3, 4] => ...{$other} from event binding */ ] = $resolved2;

		$this->assertCount( expectedCount: 5, haystack: $resolved2 );
		$this->assertEmpty( actual: $pool->getItems() );
		$this->assertInstanceOf( expected: self::class, actual: $class );
		$this->assertSame( expected: 'event', actual: $eventText );
		$this->assertSame( expected: array( 2, 3, 4 ), actual: array_slice( $resolved2, offset: 2 ) );
	}
}
