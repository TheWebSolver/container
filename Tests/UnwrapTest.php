<?php
/**
 * Unwrap test.
 *
 * @package TheWebSolver\Codegarage\Test
 *
 * @phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- For test OK.
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
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
	public function testMethodBindingId(
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
			. '@' . spl_object_id( $object ?? $this ) . '::';
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
				self::class . '::testMethodBindingId',
				$this->testMethodBindingId( ... ),
				false,
			),
			array(
				array( $this, 'testMethodBindingId' ),
				$this->testMethodBindingId( ... ),
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

	/**
	 * @param string|string[] $expected
	 * @dataProvider provideClosureToBeConvertedAsString
	 */
	public function testClosureConvertedAsString(
		string|array|null $expected,
		Closure $firstClass
	): void {
		if ( null === $expected ) {
			$this->expectException( TypeError::class );
		}

		$this->assertSame( $expected, actual: Unwrap::closureAsString( $firstClass ) );
	}

	/** @return mixed[] */
	public function provideClosureToBeConvertedAsString(): array {
		return array_filter(
			array: $this->provideDataForClosureUnwrap(),
			callback: static fn( array $data ): bool => ! end( $data )
		);
	}
}

function _wrapped__Lambda() {
	return function () {};
};

function _wrapped__staticLambda() {
	return static function () {};
};
