<?php
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed,

declare( strict_types = 1 );

use TheWebSolver\Codegarage\Container\Event\AfterBuildEvent;

function customerDetailsEventListener( AfterBuildEvent $event ): void {
	$event->app()->when( MerchCustomerDetails::class )
		->needs( ArrayAccess::class )
		->give( static fn(): ArrayAccess => new ArrayObject( array( 'zip_code' => '44600' ) ) );

	$event
		->decorateWith( MerchCustomerDetails::class )
		->update(
			static function ( Customer $customer ): void {
				$personalInfo = $customer->getPersonalInfo();

				$personalInfo['first_name'] = 'John';
				$personalInfo['last_name']  = 'Doe';
				$personalInfo['age']        = '41';

				$customer->setPersonalInfo( $personalInfo );
			}
		);
}

interface Customer {
	public function setPersonalInfo( ArrayAccess $details ): void;
	public function getPersonalInfo(): ArrayAccess;
	public function getAddress(): ArrayAccess;

	/** @return array{firstName:string,lastName:string,age:int} */
	public function personalInfoToArray(): array;

	/** @return array{state:string,country:string,zipCode:int} */
	public function addressToArray(): array;
}

class CustomerDetails implements Customer {
	public function __construct(
		private ArrayAccess $personalInfo,
		private ArrayAccess $address,
	) {
		$address['state']    = 'Bagmati';
		$address['country']  = 'Nepal';
		$address['zip_code'] = '44811';
	}

	public function setPersonalInfo( ArrayAccess $details ): void {
		$this->personalInfo = $details;
	}

	public function getPersonalInfo(): ArrayAccess {
		return $this->personalInfo;
	}

	public function getAddress(): ArrayAccess {
		return $this->address;
	}

	public function personalInfoToArray(): array {
		return array(
			'firstName' => $this->personalInfo['first_name'],
			'lastName'  => $this->personalInfo['last_name'],
			'age'       => (int) $this->personalInfo['age'],
		);
	}

	public function addressToArray(): array {
		return array(
			'state'   => $this->address['state'],
			'country' => $this->address['country'],
			'zipCode' => (int) $this->address['zip_code'],
		);
	}
}

class MerchCustomerDetails implements Customer {
	public function __construct(
		private Customer $customer,
		private ?ArrayAccess $shippingAddress = null,
	) {}

	public function setPersonalInfo( ArrayAccess $details ): void {
		$this->customer->setPersonalInfo( $details );
	}

	public function getPersonalInfo(): ArrayAccess {
		return $this->customer->getPersonalInfo();
	}

	public function getAddress(): ArrayAccess {
		return $this->customer->getAddress();
	}

	public function personalInfoToArray(): array {
		return $this->customer->personalInfoToArray();
	}

	public function addressToArray(): array {
		$address = $this->customer->addressToArray();

		if ( null === $this->shippingAddress ) {
			return $address;
		}

		return array(
			'state'   => $this->shippingAddress['state'] ?? $address['state'],
			'country' => $this->shippingAddress['country'] ?? $address['country'],
			'zipCode' => (int) ( $this->shippingAddress['zip_code'] ?? $address['zip_code'] ),
		);
	}
}
