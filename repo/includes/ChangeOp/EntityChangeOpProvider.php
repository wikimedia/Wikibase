<?php

namespace Wikibase\Repo\ChangeOp;

use Wikibase\Repo\ChangeOp\ChangeOp;
use Wikibase\Repo\ChangeOp\Deserialization\ChangeOpDeserializationException;
use Wikibase\Repo\ChangeOp\Deserialization\PermissionAwareChangeOpDeserializer;
use Wikimedia\Assert\Assert;

/**
 * Turns entity change request into ChangeOp objects based on change request deserialization
 * configured for the particular entity type.
 *
 * @license GPL-2.0+
 */
class EntityChangeOpProvider {

	/**
	 * @var callable[]
	 */
	private $changeOpDeserializerInstantiators;

	/**
	 * @var ChangeOpDeserializer[]
	 */
	private $changeOpDeserializers = [];

	/**
	 * @param callable[] $changeOpDeserializerInstantiators Associative array mapping entity types (strings)
	 * to callbacks instantiating ChangeOpDeserializer objects.
	 */
	public function __construct( array $changeOpDeserializerInstantiators ) {
		Assert::parameterElementType( 'callable', $changeOpDeserializerInstantiators, '$changeOpDeserializerInstantiators' );
		Assert::parameterElementType(
			'string',
			array_keys( $changeOpDeserializerInstantiators ),
			'array_keys( $changeOpDeserializerInstantiators )'
		);

		$this->changeOpDeserializerInstantiators = $changeOpDeserializerInstantiators;
	}

	/**
	 * @param string $entityType
	 * @param array $changeRequest Data of change to apply, @see docs/change-op-serializations.wiki for format specification
	 *
	 * @throws ChangeOpDeserializationException
	 *
	 * @return ChangeOp
	 */
	public function newEntityChangeOp( $entityType, array $changeRequest ) {
		$deserializer = $this->getDeserializerForEntityType( $entityType );

		return $deserializer->createEntityChangeOp( $changeRequest );
	}

	public function includesChangesToEntityTerms( $entityType, array $changeRequest ) {
		$deserializer = $this->getDeserializerForEntityType( $entityType );

		// TODO: make newDeserializerForEntityType assert that $deserializer is
		// a PermissionAwareChangeOpDeserializer?
		if ( ! $deserializer instanceof PermissionAwareChangeOpDeserializer ) {
			return false;
		}
		return $deserializer->includesChangesToEntityTerms( $changeRequest );
	}

	/**
	 * @param string $type
	 *
	 * @throws ChangeOpDeserializationException
	 *
	 * @return ChangeOpDeserializer
	 */
	private function getDeserializerForEntityType( $type ) {
		if ( !isset( $this->changeOpDeserializers[$type] ) ) {
			$this->changeOpDeserializers[$type] = $this->newDeserializerForEntityType( $type );
		}

		return $this->changeOpDeserializers[$type];
	}

	/**
	 * @param string $type
	 *
	 * @throws ChangeOpDeserializationException
	 *
	 * @return ChangeOpDeserializer
	 */
	private function newDeserializerForEntityType( $type ) {
		if ( !array_key_exists( $type, $this->changeOpDeserializerInstantiators ) ) {
			throw new ChangeOpDeserializationException(
				'Could not process change request for entity of type: ' . $type,
				'no-change-request-deserializer'
			);
		}

		$deserializer = call_user_func( $this->changeOpDeserializerInstantiators[$type] );
		Assert::postcondition(
			$deserializer instanceof ChangeOpDeserializer,
			'changeop-deserializer-callback defined for entity type: ' . $type . ' does not instantiate ChangeOpDeserializer'
		);

		return $deserializer;
	}

}
