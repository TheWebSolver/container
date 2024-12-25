<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Container\Error;

use LogicException;
use TheWebSolver\Codegarage\Container\Event\EventType;
use TheWebSolver\Codegarage\Container\Helper\EventBuilder;
use TheWebSolver\Codegarage\Container\Helper\ContextBuilder;

class LogicalError extends LogicException {
	public static function entryAndAliasIsSame( string $entry ): self {
		return new self( sprintf( '"%s" cannot be aliased by same name.', $entry ) );
	}

	public static function duringBuildEventNeedsParamName( string $entry ): self {
		return new self(
			sprintf( 'Parameter name is required when adding event listener during build for entry "%s".', $entry )
		);
	}

	public static function afterBuildEventEntryMustBeClassname( string $entry ): self {
		return new self(
			sprintf( 'The concrete must be a string when adding event listener after build for entry "%s".', $entry )
		);
	}

	public static function unsupportedEventType( EventType $type ): self {
		return new self( sprintf( 'Cannot add Event Listener for the "%s" Event Type.', $type->name ) );
	}

	public static function eventListenerEntryNotProvidedWith( string $method ): self {
		return new self(
			sprintf( 'Event entry must be provided using method "%1$s::%2$s()".', EventBuilder::class, $method )
		);
	}

	public static function contextualConstraintNotProvidedWith( string $method ): self {
		return new self(
			sprintf(
				'The dependency to be resolved must be provided using method "%1$s::%2$s()".',
				ContextBuilder::class,
				$method
			)
		);
	}

	public static function unwrappableClosure(): self {
		return new self(
			'Cannot unwrap closure. Currently, only supports non-static class members/functions/methods and named functions.'
		);
	}

	public static function noMethodNameForBinding( string $classname ): self {
		return new self( sprintf( 'Method name must be provided to create binding ID for class: "%s".', $classname ) );
	}

	public static function nonBindableClosure(): self {
		return new self(
			'Method binding only accepts first-class callable of a named function or'
			. ' a non-static method. Alternatively, pass an instantiated object as'
			. ' param [#1] "$object" & its method name as param [#2] "$methodName".'
		);
	}

	public static function nonInstantiableClass( string $classname ): self {
		return new self( sprintf( 'Non-instantiable class "%s".', $classname ) );
	}
}
