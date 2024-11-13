<?php
/**
 * Compiled data for Listener provider.
 *
 * @package TheWebSolver\Codegarage\Test
 */

use PHPUnit\Framework\TestCase;

return array(
	'listeners' => array(
		10 => array( TestCase::any( ... ), array( TestCase::class, 'assertTrue' ), TestCase::class . '::assertNull' ),
		20 => array( TestCase::assertContains( ... ) ),
	),
	'listenersForEntry' => array(
		'test' => array(
			5  => array( TestCase::exactly( ... ), array( TestCase::class, 'assertContains' ), TestCase::class . '::assertTrue' ),
			15 => array( TestCase::assertCount( ... ) ),
		),
	),
);
