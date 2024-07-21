<?php
/**
 * Unwrap test.
 *
 * @package TheWebSolver\Codegarage\Test
 *
 * @phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- For test OK.
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Reorderable;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use TheWebSolver\Codegarage\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Helper\Unwrap;

class UnwrapTest extends TestCase {
	/** @dataProvider provideDataForArrayConversion */
	public function testArrayConversion( array $expected, mixed $toConvert ): void {
		$this->assertSame( $expected, actual: Unwrap::asArray( thing: $toConvert ) );
	}

	/** @return mixed[] */
	public function provideDataForArrayConversion(): array {
		return array(
			array( array( 123 ), 123 ),
			array( array( '4' ), '4' ),
			array( array( true ), true ),
			array( array( null ), null ),
			array( array( self::class ), self::class ),
		);
	}

	/** @dataProvider provideDataForStringConversion */
	public function testStringConversion( object $object, string $method, string $expected ): void {
		$this->assertSame( $expected, actual: Unwrap::asString( $object, $method ) );
	}

	/** @return mixed[] */
	public function provideDataForStringConversion(): array {
		$class   = new class() {};
		$closure = $this->assertContains( ... );

		return array(
			array( $class, '', $this->_gIdSpl( $class ), $class ),
			array( $this, 'assertTrue', "{$this->_gIdSpl()}assertTrue" ),
			array( $closure, '', $this->_gIdSpl( $closure ) ),
		);
	}

	/**
	 * @param string|string[] $expected
	 * @dataProvider provideDataForMethodBinding
	 */
	public function testForBinding(
		string|array $expected,
		object|string $object,
		string $methodName,
		?string $exception
	): void {
		if ( $exception ) {
			$this->expectException( $exception );
		}

		$this->assertSame(
			actual: Unwrap::forBinding( $object, $methodName, asArray: is_array( $expected ) ),
			expected: $expected
		);
	}

	public function provideDataForMethodBinding(): array {
		return array(
			array( self::class . '::assertTrue', self::class, 'assertTrue', null ),
			array( 'lambda method is invalid', function () {}, '', TypeError::class ),
			array( "{$this->_gIdSpl()}assertTrue", $this, 'assertTrue', null ),
			array( array( $this, 'assertTrue' ), $this, 'assertTrue', null ),
			array( 'Undefined method given', $this, 'method-does-not-exist', LogicException::class ),
			array(
				'first-class of static method is invalid',
				$this->assertTrue( ... ),
				'',
				TypeError::class,
			),
			array(
				"{$this->_gIdSpl()}provideDataForMethodBinding",
				$this->provideDataForMethodBinding( ... ),
				'',
				null,
			),
			array(
				array( $this, 'provideDataForMethodBinding' ),
				$this->provideDataForMethodBinding( ... ),
				'',
				null,
			),
			array(
				"{$this->_gIdSpl()}testArrayConversion",
				$this->testArrayConversion( ... ),
				'',
				null,
			),
			array( 'lambda func is invalid', _wrapped__Lambda(), '', TypeError::class ),
			array(
				'lambda static func is invalid',
				_wrapped__StaticLambda(),
				'',
				TypeError::class,
			),
			array( 'named func is also invalid', phpinfo( ... ), '', TypeError::class ),
		);
	}

	private function _gIdSpl( ?object $object = null ): string {
		return ( $object ? $object::class : self::class )
			. '#' . spl_object_id( $object ?? $this ) . '::';
	}

	/**
	 * @param string|mixed[]|null $expected
	 * @dataProvider provideDataForClosureUnwrap
	 */
	public function testClosureUnwrap(
		string|array|null $expected,
		Closure $firstClass,
		bool $asArray
	): void {
		if ( null === $expected ) {
			$this->expectException( TypeError::class );
		}

		$this->assertSame( $expected, actual: Unwrap::closure( $firstClass, $asArray ) );
	}

	/** @return mixed[] */
	public function provideDataForClosureUnwrap(): array {
		$phpinfo = phpinfo( ... );
		$staticM = self::assertTrue( ... );
		$staticF = static function () {};

		return array(
			array(
				self::class . '::testForBinding',
				$this->testForBinding( ... ),
				false,
			),
			array(
				array( $this, 'testForBinding' ),
				$this->testForBinding( ... ),
				true,
			),
			array( 'PHPUnit\Framework\Assert::assertTrue', self::assertTrue( ... ), false ),
			array( array( $staticM, 'assertTrue' ), $staticM, true ),
			array( self::class . '::{closure}', function () {}, false ),
			array( array( $this, '{closure}' ), function () {}, true ),
			array( self::class . '::{closure}', static function () {}, false ),
			array( array( $staticF, '{closure}' ), $staticF, true ),
			array( 'phpinfo', $phpinfo, false ),
			array( array( 'phpinfo' ), $phpinfo, true ),
			array( null, _wrapped__Lambda(), false ),
			array( null, _wrapped__staticLambda(), false ),
		);
	}

	/** @dataProvider provideParamTypeFunc */
	public function testParamTypeFrom( ?string $expected, Closure $fn ): void {
		$reflection = new ReflectionParameter( $fn, param: 0 );

		$this->assertSame( $expected, actual: Unwrap::paramTypeFrom( $reflection ) );
	}

	public function provideParamTypeFunc(): array {
		return array(
			array( self::class, static function ( self $self ) {} ),
			array( TestCase::class, static function ( parent $parent ) {} ),
			array( self::class, static function ( UnwrapTest $test ) {} ),
			array( TestCase::class, static function ( TestCase $parent ) {} ),
			array( null, static function ( string $name ) {} ),
			array( null, static function ( int|float $currency ) {} ),
			array( null, static function ( Container&ContainerInterface $app ) {} ),
			array( Container::class, static function ( Container $app ) {} ),
		);
	}

	public function testInvokeCallableValue(): void {
		$this->assertSame( 'notCallable', Unwrap::andInvoke( 'notCallable', 'argsDoesNotMatter' ) );
		$this->assertSame( 'invoked', Unwrap::andInvoke( static fn() => 'invoked' ) );
		$this->assertSame(
			expected: 14.5,
			actual: Unwrap::andInvoke( static fn ( int $_5, float $_9_5 ) => $_5 + $_9_5, 5, 9.5 )
		);
		$this->assertCount(
			expectedCount: count( $this->provideParamTypeFunc() ),
			haystack: Unwrap::andInvoke( fn ( self $test ) => $test->provideParamTypeFunc(), $this )
		);
	}

	/**
	 * @param null|mixed[]|string $val
	 * @dataProvider provideCallbackData
	 */
	public function testCallback( null|array|string $val, callable|string $cb, bool $asArray ): void {
		$this->assertSame( expected: $val ?? $cb, actual: Unwrap::callback( $cb, $asArray ) );
	}

	/** @return mixed[] */
	public function provideCallbackData(): array {
		$class = new class() {
			public function __invoke() {}
		};

		return array(
			array( null, self::class . '::assertTrue', false ),
			array( array( self::class . '::assertTrue', '' ), self::class . '::assertTrue', true ),
			array( "{$this->_gIdSpl()}testCallback", $this->testCallback( ... ), false ),
			array( array( $this, 'testCallback' ), $this->testCallback( ... ), true ),
			array( "{$this->_gIdSpl( $class )}__invoke", $class, false ),
			array( array( $class, '__invoke' ), $class, true ),
			array( self::class . '::assertTrue', array( self::class, 'assertTrue' ), false ),
			array( array( self::class, 'assertTrue' ), array( self::class, 'assertTrue' ), true ),

		);
	}

	/**
	 * @param string[] $expected
	 * @dataProvider provideParts
	 */
	public function testPartsFrom( array $expected, string $string, string $separator ): void {
		$this->assertSame( $expected, actual: Unwrap::partsFrom( $string, $separator ) );
	}

	/** @return mixed[] */
	public function provideParts(): array {
		return array(
			array( array( 'one', 'two' ), 'one:two', ':' ),
			array( array( 'one', 'two' ), 'one//two', '//' ),
			array( array( 'one', 'two:three' ), 'one:two:three', ':' ),
			array( array( 'one', 'two' ), 'one@two', '@' ),
			array( array( 'one', '2' ), 'one#2', '#' ),
			array( array( 'one', 'two||three' ), 'one||two||three', '||' ),
			array( array( 'One', 'TwoAndThree&Four' ), 'OneAndTwoAndThree&Four', 'And' ),
			array( array( 'OneAndTwoAndThree', 'Four' ), 'OneAndTwoAndThree&Four', '&' ),
		);
	}
}

function _wrapped__Lambda() {
	return function () {};
};

function _wrapped__staticLambda() {
	return static function () {};
};
