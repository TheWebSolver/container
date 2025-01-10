<?php
use PHPUnit\Framework\TestCase;

return array(
	'listeners' => array(
		10 => array( TestCase::assertTrue( ... ), array( TestCase::class, 'assertTrue' ), TestCase::class . '::assertNull' ),
		20 => array( TestCase::assertContains( ... ) ),
	),
	'listenersForEntry' => array(
		'test' => array(
			5  => array( TestCase::assertFalse( ... ), array( TestCase::class, 'assertContains' ), TestCase::class . '::assertTrue' ),
			15 => array( TestCase::assertCount( ... ) ),
		),
	),
);
