<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Tests;

use ReflectionMethod;
use ReflectionParameter;
use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Container\Pool\Param;

class ParamPoolTest extends TestCase {
	private ?Param $paramPool;

	protected function setUp(): void {
		$this->paramPool = new Param();
	}

	protected function tearDown(): void {
		$this->paramPool = null;
	}

	public function testPushPull( string $param1 = 'value1', bool $param2 = true ): void {
		$reflection   = new ReflectionMethod( $this, __FUNCTION__ );
		$dependencies = array(
			'param1' => 'value1',
			'param2' => true,
		);

		$this->paramPool->push( value: $dependencies );

		$this->assertTrue( $this->paramPool->hasItems() );

		foreach ( $reflection->getParameters() as $param ) {
			$this->assertTrue( $this->paramPool->has( $param->name ) );
			$this->assertSame(
				expected: $param->getDefaultValue(),
				actual: $this->paramPool->get( $param->name )
			);
		}

		$custom = new ReflectionParameter( fn( int $custom = 12345 ): int => $custom, param: 0 );

		$this->paramPool->push( value: array( 'custom' => 12345 ) );

		$this->assertSame(
			actual: $this->paramPool->getItems(),
			expected: array(
				array(
					'param1' => 'value1',
					'param2' => true,
				),
				array( 'custom' => 12345 ),
			),
		);

		$this->assertTrue( $this->paramPool->has( $custom->name ) );
		$this->assertSame( expected: 12345, actual: $this->paramPool->get( $custom->name ) );
		$this->assertSame( expected: array( 'custom' => 12345 ), actual: $this->paramPool->latest() );

		$this->paramPool->pull();

		$this->assertSame(
			actual: $this->paramPool->latest(),
			expected: array(
				'param1' => 'value1',
				'param2' => true,
			),
		);

		$this->assertSame(
			actual: $this->paramPool->pull(),
			expected: array(
				'param1' => 'value1',
				'param2' => true,
			)
		);

		$this->assertFalse( $this->paramPool->has( $custom->name ) );
		$this->assertSame( expected: null, actual: $this->paramPool->latest() );
	}
}
