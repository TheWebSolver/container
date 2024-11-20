<?php
/**
 * Event Dispatcher Builder Pattern API.
 *
 * @package TheWebSolver\Codegarage\Container
 *
 * @phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint -- Closure type-hint OK.
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Helper;

use Closure;
use TheWebSolver\Codegarage\Lib\Container\Container;
use TheWebSolver\Codegarage\Lib\Container\Event\EventType;
use TheWebSolver\Codegarage\Lib\Container\Error\LogicalError;
use TheWebSolver\Codegarage\Lib\Container\Event\Manager\EventManager;
use TheWebSolver\Codegarage\Lib\Container\Interfaces\ListenerRegistry;

final class EventBuilder {
	private string $key;

	public function __construct(
		private Container $app,
		private EventType $type,
		private ListenerRegistry $registry
	) {}

	public static function create( EventType $type, Container $app, EventManager $manager ): self {
		return ( $registry = $manager->getDispatcher( $type ) )
			? new self( $app, $type, $registry )
			: throw LogicalError::unsupportedEventType( $type );
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
	 * @throws LogicalError When this method is invoked before setting id and/or param name.
	 */
	public function listenTo( Closure $listener, int $priority = ListenerRegistry::DEFAULT_PRIORITY ): void {
		$forEntry = $this->key ?? throw LogicalError::eventListenerEntryNotProvidedWith( method: 'for' );

		$this->registry->addListener( $listener, $forEntry, $priority );
	}
}
