<?php // phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Tests;

use Closure;
use WeakMap;
use stdClass;
use LogicException;
use ReflectionException;
use ReflectionParameter;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Container\Container;
use TheWebSolver\Codegarage\Container\Helper\Unwrap;
use TheWebSolver\Codegarage\Container\Error\LogicalError;

class UnwrapTest extends TestCase {
	#[ DataProvider( 'provideDataForArrayConversion' ) ]
	public function testArrayConversion( array $expected, mixed $toConvert ): void {
		$this->assertSame( $expected, actual: Unwrap::asArray( thing: $toConvert ) );
	}

	public static function provideDataForArrayConversion(): array {
		return array(
			array( array( 123 ), 123 ),
			array( array( '4' ), '4' ),
			array( array( true ), true ),
			array( array( null ), null ),
			array( array( self::class ), self::class ),
		);
	}

	#[ DataProvider( 'provideDataForStringConversion' ) ]
	public function testStringConversion( object $object, string $method, string $expected ): void {
		$this->assertSame( $expected, actual: Unwrap::asString( $object, $method ) );
	}

	public static function provideDataForStringConversion(): array {
		$class   = new class() {};
		$closure = self::assertContains( ... );
		$self    = new self( '' );

		return array(
			array( $class, '', self::_gIdSpl( $class ), $class ),
			array( $self, 'assertTrue', self::_gIdSpl( $self ) . 'assertTrue' ),
			array( $closure, '', self::_gIdSpl( $closure ) ),
		);
	}

	/** @param string|string[] $expected */
	#[ DataProvider( 'provideDataForMethodBinding' ) ]
	public function testForBinding(
		string|array $expected,
		object|string $object,
		string $methodName,
		?LogicalError $exception
	): void {
		if ( $exception ) {
			$this->expectExceptionMessage( $exception->getMessage() );
		}

		$this->assertSame(
			actual: Unwrap::forBinding( $object, $methodName, asArray: is_array( $expected ) ),
			expected: $expected
		);
	}

	public static function provideDataForMethodBinding(): array {
		$self = new self( '' );

		return array(
			array( self::class . '::assertTrue', self::class, 'assertTrue', null ),
			array( self::class, self::class, '', LogicalError::noMethodNameForBinding( self::class ) ),
			array( 'lambda method is invalid', function () {}, '', LogicalError::nonBindableClosure() ),
			array( self::_gIdSpl( $self ) . 'assertTrue', $self, 'assertTrue', null ),
			array( array( $self, 'assertTrue' ), $self, 'assertTrue', null ),
			array( 'Undefined method given', $self, 'method-does-not-exist', LogicalError::noMethodNameForBinding( self::class ) ),
			array(
				'first-class of static method is invalid',
				$self->assertTrue( ... ),
				'',
				LogicalError::nonBindableClosure(),
			),
			array(
				self::_gIdSpl( $self ) . 'provideDataForMethodBinding',
				// If it was (scoped) $this->provideDataForMethodBinding( ... ), then no LogicalError.
				$self->provideDataForMethodBinding( ... ),
				'',
				LogicalError::nonBindableClosure(),
			),
			array(
				array( $self, 'provideDataForMethodBinding' ),
				// Same case as above.
				$self->provideDataForMethodBinding( ... ),
				'',
				LogicalError::nonBindableClosure(),
			),
			array(
				self::_gIdSpl( $self ) . 'testArrayConversion',
				$self->testArrayConversion( ... ),
				'',
				null,
			),
			array( 'lambda func is invalid', _wrapped__Lambda(), '', LogicalError::nonBindableClosure() ),
			array(
				'lambda static func is invalid',
				_wrapped__StaticLambda(),
				'',
				LogicalError::nonBindableClosure(),
			),
			array( 'named func is also invalid', strlen( ... ), '', LogicalError::nonBindableClosure() ),
		);
	}

	private static function _gIdSpl( object $object ): string { // phpcs:ignore
		return ( $object ? $object::class : self::class )
			. '#' . spl_object_id( $object ) . '::';
	}

	/** @param string|mixed[]|null $expected  */
	#[ DataProvider( 'provideDataForClosureUnwrap' ) ]
	public function testClosureUnwrap(
		string|array|null $expected,
		Closure $firstClass,
		bool $asArray
	): void {
		if ( null === $expected ) {
			$this->expectExceptionMessage( LogicalError::unwrappableClosure()->getMessage() );
		}

		$this->assertSame( $expected, actual: Unwrap::closure( $firstClass, $asArray ) );
	}

	public static function provideDataForClosureUnwrap(): array {
		$strlen  = strlen( ... );
		$staticM = self::assertTrue( ... );
		$staticF = static function () {};
		$func    = function () {};
		$self    = new self( '' );

		return array(
			array(
				self::class . '::testForBinding',
				$self->testForBinding( ... ),
				false,
			),
			array(
				array( $self, 'testForBinding' ),
				$self->testForBinding( ... ),
				true,
			),
			array( 'PHPUnit\Framework\Assert::assertTrue', self::assertTrue( ... ), false ),
			array( array( $staticM, 'assertTrue' ), $staticM, true ),
			array( self::class . '::' . __NAMESPACE__ . '\\{closure}', function () {}, false ),
			array( array( $func, __NAMESPACE__ . '\\{closure}' ), $func, true ),
			array( self::class . '::' . __NAMESPACE__ . '\\{closure}', static function () {}, false ),
			array( array( $staticF, __NAMESPACE__ . '\\{closure}' ), $staticF, true ),
			array( 'strlen', $strlen, false ),
			array( array( 'strlen' ), $strlen, true ),
			array( null, _wrapped__Lambda(), false ),
			array( null, _wrapped__staticLambda(), false ),
		);
	}

	#[ DataProvider( 'provideParamTypeFunc' ) ]
	public function testParamTypeFrom( ?string $expected, Closure $fn ): void {
		$reflection = new ReflectionParameter( $fn, param: 0 );

		$this->assertSame( $expected, actual: Unwrap::paramTypeFrom( $reflection ) );
	}

	public static function provideParamTypeFunc(): array {
		return array(
			array( self::class, static function ( self $_self ) {} ),
			array( TestCase::class, static function ( parent $_parent ) {} ),
			array( self::class, static function ( UnwrapTest $test ) {} ),
			array( TestCase::class, static function ( TestCase $_parent ) {} ),
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

	/** @param null|mixed[]|string $val */
	#[ DataProvider( 'provideCallbackData' ) ]
	public function testCallback( null|array|string $val, callable|string $cb, bool $asArray ): void {
		$this->assertSame( expected: $val ?? $cb, actual: Unwrap::callback( $cb, $asArray ) );
	}

	public static function provideCallbackData(): array {
		$class = new class() {
			public function __invoke() {}
		};

		$self = new self( '' );

		return array(
			array( null, self::class . '::assertTrue', false ),
			array( array( self::class . '::assertTrue', '' ), self::class . '::assertTrue', true ),
			array( self::_gIdSpl( $self ) . 'testCallback', $self->testCallback( ... ), false ),
			array( array( $self, 'testCallback' ), $self->testCallback( ... ), true ),
			array( "{$self->_gIdSpl( $class )}__invoke", $class, false ),
			array( array( $class, '__invoke' ), $class, true ),
			array( self::class . '::assertTrue', array( self::class, 'assertTrue' ), false ),
			array( array( self::class, 'assertTrue' ), array( self::class, 'assertTrue' ), true ),

		);
	}

	/** @param string[] $expected */
	#[ DataProvider( 'provideParts' ) ]
	public function testPartsFrom( array $expected, string $string, string $separator ): void {
		$this->assertSame( $expected, actual: Unwrap::partsFrom( $string, $separator ) );
	}

	/** @return mixed[] */
	public static function provideParts(): array {
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

	#[ DataProvider( 'provideVariousClasses' ) ]
	public function testClassReflectionUnwrap( string $classname, ?string $throws = null ): void {
		if ( $throws ) {
			$this->expectException( $throws );
		}

		$reflection = Unwrap::classReflection( $classname );

		$this->assertSame( $classname, $reflection->getName() );
	}

	public static function provideVariousClasses(): array {
		return array(
			array( stdClass::class ),
			array( WeakMap::class ),
			array( self::class ),
			array( 'Invalid\\Class', ReflectionException::class ),
			array( Unwrap::class, LogicException::class ),
		);
	}
}

function _wrapped__Lambda() {
	return function () {};
}

function _wrapped__staticLambda() {
	return static function () {};
}
