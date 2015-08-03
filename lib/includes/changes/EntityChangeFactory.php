<?php

namespace Wikibase\Lib\Changes;

use InvalidArgumentException;
use MWException;
use Wikibase\ChangesTable;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Services\Diff\EntityDiffer;
use Wikibase\EntityChange;
use Wikibase\EntityFactory;

/**
 * Factory for EntityChange objects
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class EntityChangeFactory {

	/**
	 * @var array maps entity type IDs to subclasses of EntityChange
	 */
	private $changeClasses;

	/**
	 * @var ChangesTable
	 */
	private $changesTable;

	/**
	 * @var EntityFactory
	 */
	private $entityFactory;

	/**
	 * @var EntityDiffer
	 */
	private $entityDiffer;

	/**
	 * @param ChangesTable $changesTable
	 * @param EntityFactory $entityFactory
	 * @param EntityDiffer $entityDiffer
	 * @param array $changeClasses maps entity type IDs to subclasses of EntityChange.
	 * Entity types not mapped explicitly are assumed to use EntityChange itself.
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct( ChangesTable $changesTable, EntityFactory $entityFactory,
		EntityDiffer $entityDiffer, array $changeClasses = array() ) {

		$this->changeClasses = $changeClasses;
		$this->changesTable = $changesTable;
		$this->entityFactory = $entityFactory;
		$this->entityDiffer = $entityDiffer;
	}

	/**
	 * @since 0.5
	 *
	 * @param string $action The action name
	 * @param EntityId $entityId
	 * @param array $fields additional fields to set
	 *
	 * @return EntityChange
	 */
	public function newForEntity( $action, EntityId $entityId, array $fields = null ) {
		$entityType = $entityId->getEntityType();

		if ( isset( $this->changeClasses[ $entityType ] ) ) {
			$class = $this->changeClasses[$entityType];
		} else {
			$class = '\Wikibase\EntityChange';
		}

		/** @var EntityChange $instance  */
		$instance = new $class(
			$this->changesTable,
			$fields,
			true
		);

		if ( !$instance->hasField( 'object_id' ) ) {
			$instance->setField( 'object_id', $entityId->getSerialization() );
		}

		if ( !$instance->hasField( 'info' ) ) {
			$info = array();
			$instance->setField( 'info', $info );
		}

		// Note: the change type determines how the client will
		// instantiate and handle the change
		$type = 'wikibase-' . $entityId->getEntityType() . '~' . $action;
		$instance->setField( 'type', $type );

		return $instance;
	}

	/**
	 * Constructs an EntityChange from the given old and new Entity.
	 *
	 * @since 0.5
	 *
	 * @param string      $action The action name
	 * @param EntityDocument|null $oldEntity
	 * @param EntityDocument|null $newEntity
	 * @param array|null  $fields additional fields to set
	 *
	 * @return EntityChange
	 * @throws MWException
	 */
	public function newFromUpdate( $action, EntityDocument $oldEntity = null, EntityDocument $newEntity = null, array $fields = null ) {
		if ( $oldEntity === null && $newEntity === null ) {
			throw new MWException( 'Either $oldEntity or $newEntity must be give.' );
		}

		if ( $oldEntity === null ) {
			$oldEntity = $this->entityFactory->newEmpty( $newEntity->getType() );
			$theEntity = $newEntity;
		} elseif ( $newEntity === null ) {
			$newEntity = $this->entityFactory->newEmpty( $oldEntity->getType() );
			$theEntity = $oldEntity;
		} elseif ( $oldEntity->getType() !== $newEntity->getType() ) {
			throw new MWException( 'Entity type mismatch' );
		} else {
			$theEntity = $newEntity;
		}

		$diff = $this->entityDiffer->diffEntities( $oldEntity, $newEntity );

		/**
		 * @var EntityChange $instance
		 */
		$instance = self::newForEntity( $action, $theEntity->getId(), $fields );
		$instance->setDiff( $diff );

		return $instance;
	}

}
