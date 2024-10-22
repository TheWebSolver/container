<?php
/**
 * The event type.
 *
 * @package TheWebSolver\Codegarage\Container
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Container\Event;

enum EventType {
	case BeforeBuild;
	case Building;
	case AfterBuild;
}
