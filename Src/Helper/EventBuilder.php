<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Helper;

use Closure;
use TheWebSolver\Codegarage\Container\Container;
use TheWebSolver\Codegarage\Container\Event\EventType;
use TheWebSolver\Codegarage\Container\Error\LogicalError;
use TheWebSolver\Codegarage\Container\Interfaces\ListenerRegistry;

final class EventBuilder {
	private string $key;

	public function __construct( private Container $app, private EventType $type ) {}

	public static function create( EventType $type, Container $app ): self {
		return new self( $app, $type );
	}

	/**
	 * @throws LogicalError When param name not passed for `EventType::Building`.
	 *                      When concrete is a Closure for `EventType::AfterBuild`.
	 */
	public function for( string $id, ?string $paramName = null ): self {
		$this->key = $this->type->getKeyFrom( $this->app, $id, $paramName );

		return $this;
	}

	/**
	 * @param Closure(object): void $listener
	 * @throws LogicalError When no registry found or this method is invoked before setting id and/or param name.
	 */
	public function listenTo( Closure $listener, int $priority = ListenerRegistry::DEFAULT_PRIORITY ): void {
		$forEntry = $this->key ?? throw LogicalError::eventListenerEntryNotProvidedWith( method: 'for' );

		if ( ! $registry = $this->app->getListenerRegistry( $this->type ) ) {
			throw LogicalError::unsupportedEventType( $this->type );
		}

		$registry->addListener( $listener, $forEntry, $priority );
	}
}
