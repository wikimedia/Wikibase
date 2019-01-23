<?php

namespace Wikibase\DataAccess;

use Wikimedia\Assert\Assert;

/**
 * @license GPL-2.0-or-later
 */
class EntitySource {

	/**
	 * @var string
	 */
	private $sourceName;

	/**
	 * @var string|false
	 */
	private $databaseName;

	/**
	 * @var string[]
	 */
	private $entityTypes;

	/**
	 * @var int[]
	 */
	private $entityNamespaceIds;

	/**
	 * @var string[]
	 */
	private $entitySlots;

	/**
	 * @param string $name
	 * @param string|false $databaseName
	 * @param array $entityNamespaceIdsAndSlots
	 */
	public function __construct( $name, $databaseName, array $entityNamespaceIdsAndSlots ) {
		Assert::parameterType( 'string', $name, '$name' );
		Assert::parameter( is_string( $databaseName ) || $databaseName === false, '$databaseName', 'must be a string or false' );
		$this->assertEntityNamespaceIdsAndSlots( $entityNamespaceIdsAndSlots );
		$this->sourceName = $name;
		$this->databaseName = $databaseName;
		$this->setEntityTypeData( $entityNamespaceIdsAndSlots );
	}

	private function assertEntityNamespaceIdsAndSlots( array $entityNamespaceIdsAndSlots ) {
		foreach ( $entityNamespaceIdsAndSlots as $entityType => $namespaceIdAndSlot ) {
			if ( !is_string( $entityType ) ) {
				throw new \InvalidArgumentException( 'Entity type name not a string: ' . $entityType );
			}
			if ( !is_array( $namespaceIdAndSlot ) ) {
				throw new \InvalidArgumentException( 'Namespace and slot not defined for entity type: ' . $entityType );
			}
			if ( !array_key_exists( 'namespaceId', $namespaceIdAndSlot ) ) {
				throw new \InvalidArgumentException( 'Namespace ID not defined for entity type: ' . $entityType );
			}
			if ( !array_key_exists( 'slot', $namespaceIdAndSlot ) ) {
				throw new \InvalidArgumentException( 'Slot not defined for entity type: ' . $entityType );
			}
			if ( !is_int( $namespaceIdAndSlot['namespaceId'] ) ) {
				throw new \InvalidArgumentException( 'Namespace ID for entity type must be an integer: ' . $entityType );
			}
			if ( !is_string( $namespaceIdAndSlot['slot'] ) ) {
				throw new \InvalidArgumentException( 'Slot for entity type must be a string: ' . $entityType );
			}
		}
	}

	private function setEntityTypeData( array $entityNamespaceIdsAndSlots ) {
		$this->entityTypes = array_keys( $entityNamespaceIdsAndSlots );
		$this->entityNamespaceIds = array_map(
			function ( $x ) {
				return $x['namespaceId'];
			},
			$entityNamespaceIdsAndSlots
		);
		$this->entitySlots = array_map(
			function ( $x ) {
				return $x['slot'];
			},
			$entityNamespaceIdsAndSlots
		);
	}

	public function getDatabaseName() {
		return $this->databaseName;
	}

	public function getSourceName() {
		return $this->sourceName;
	}

	public function getEntityTypes() {
		return $this->entityTypes;
	}

	public function getEntityNamespaceIds() {
		return $this->entityNamespaceIds;
	}

	public function getEntitySlotNames() {
		return $this->entitySlots;
	}

}
