<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Tests\Feature;

use Customer;
use ArrayAccess;
use ArrayObject;
use CustomerDetails;
use MerchCustomerDetails;
use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Container\Container;
use TheWebSolver\Codegarage\Container\Event\EventType;

class WikiAfterBuildEventTest extends TestCase {
	public function testAfterBuildEventWithContainer(): void {
		require_once dirname( __DIR__ ) . '/Wiki/AfterBuildEvent.php';

		$app = new Container();

		$app->set( Customer::class, CustomerDetails::class );

		$app->when( CustomerDetails::class )
			->needs( ArrayAccess::class )
			->give( ArrayObject::class );

		$customer = $app->get( Customer::class );

		$this->assertInstanceOf( CustomerDetails::class, $customer );
		$this->assertEmpty( $customer->getPersonalInfo() );

		$app->when( EventType::AfterBuild )
			->for( Customer::class )
			->listenTo( customerDetailsEventListener( ... ) );

		$customer = $app->get( Customer::class );

		$this->assertInstanceOf( MerchCustomerDetails::class, $customer );
		$this->assertSame( 'John', $customer->personalInfoToArray()['firstName'] );
		$this->assertSame(
			actual: $customer->addressToArray(),
			expected: array(
				'state'   => 'Bagmati', // from CustomerDetails::$address.
				'country' => 'Nepal',   // from CustomerDetails::$address.
				'zipCode' => 44600,     // from MerchCustomerDetails::$shippingAddress.
			),
		);
	}
}
