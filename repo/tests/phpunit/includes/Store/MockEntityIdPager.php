<?php

namespace Wikibase\Repo\Tests\Store;

use BadMethodCallException;
use InvalidArgumentException;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\Repo\Store\EntityPerPage;
use Wikibase\DataModel\Services\EntityId\EntityIdPager;

/**
 * @author Addshore
 */
class MockEntityIdPager implements EntityPerPage, EntityIdPager {

	/**
	 * @var EntityId[]
	 */
	private $pageIdToEntityId = array();

	/**
	 * @var string[]
	 */
	private $redirects = array();

	/**
	 * @var EntityId|null
	 */
	private $position = null;

	/**
	 * Adds a new link between an entity and a page
	 *
	 * @param EntityId $entityId
	 * @param int $pageId
	 *
	 * @throws InvalidArgumentException
	 */
	public function addEntityPage( EntityId $entityId, $pageId ) {
		$this->pageIdToEntityId[$pageId] = $entityId;
	}

	/**
	 * Adds a new link between an entity redirect and a page
	 *
	 * @param EntityId $entityId
	 * @param int $pageId
	 * @param EntityId $targetId
	 */
	public function addRedirectPage( EntityId $entityId, $pageId, EntityId $targetId ) {
		$this->addEntityPage( $entityId, $pageId );
		$this->redirects[$pageId] = $entityId->getSerialization();
	}

	/**
	 * @see EntityIdPager::fetchIds
	 *
	 * @param int $limit The maximum number of IDs to return.
	 *
	 * @return EntityId[] A list of EntityIds matching the given parameters. Will
	 * be empty if there are no more entities to list from the given offset.
	 */
	public function fetchIds( $limit ) {
		$ids = $this->listEntities( null, $limit, $this->position );

		if ( !empty( $ids ) ) {
			$this->position = end( $ids );
			reset( $ids );
		}

		return $ids;
	}

	/**
	 * Lists entities of the given type (optionally including redirects).
	 *
	 * @param null|string $entityType The entity type to look for.
	 * @param int $limit The maximum number of IDs to return.
	 * @param EntityId|null $after Only return entities with IDs greater than this.
	 *
	 * @return EntityId[]
	 */
	private function listEntities(
		$entityType,
		$limit,
		EntityId $after = null
	) {
		/** @var EntityId[] $entityIds */
		$entityIds = $this->pageIdToEntityId;
		$entityIds = array_values( $entityIds );

		// Act on $entityType
		if ( is_string( $entityType ) ) {
			foreach ( $entityIds as $key => $entityId ) {
				if ( $entityId->getEntityType() !== $entityType ) {
					unset( $entityIds[$key] );
				}
			}
		}

		// Act on $redirects
		foreach ( $entityIds as $key => $entityId ) {
			$entityIdString = $entityId->getSerialization();
			if ( in_array( $entityIdString, $this->redirects ) ) {
				unset( $entityIds[$key] );
			}
		}

		// Act on $after
		if ( $after !== null ) {
			foreach ( $entityIds as $key => $entityId ) {
				if ( $entityId->getSerialization() <= $after->getSerialization() ) {
					unset( $entityIds[$key] );
				}
			}
		}

		// Act on $limit
		$entityIds = array_slice( array_values( $entityIds ), 0, $limit );

		return array_values( $entityIds );
	}

	/**
	 * @throws BadMethodCallException always
	 */
	public function deleteEntityPage( EntityId $entityId, $pageId ) {
		throw new BadMethodCallException( 'Mock method not yet implemented' );
	}

	/**
	 * @throws BadMethodCallException always
	 */
	public function deleteEntity( EntityId $entityId ) {
		throw new BadMethodCallException( 'Mock method not yet implemented' );
	}

	/**
	 * @throws BadMethodCallException always
	 */
	public function clear() {
		throw new BadMethodCallException( 'Mock method not yet implemented' );
	}

}
