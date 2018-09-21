<?php

namespace Wikibase\Lib\Store\Sql;

use DBAccessBase;
use InvalidArgumentException;
use MediaWiki\Storage\NameTableAccessException;
use MediaWiki\Storage\NameTableStore;
use stdClass;
use Wikibase\DataModel\Assert\RepositoryNameAssert;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\DBQueryError;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * Service for looking up meta data about one or more entities as needed for
 * loading entities from WikiPages (via Revision) or to verify an entity against
 * page.page_latest.
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 * @author Marius Hoch < hoo@online.de >
 */
class WikiPageEntityMetaDataLookup extends DBAccessBase implements WikiPageEntityMetaDataAccessor {

	/**
	 * @var EntityNamespaceLookup
	 */
	private $entityNamespaceLookup;

	/**
	 * @var EntityTitleLookup
	 */
	private $entityTitleLookup;

	/**
	 * @var NameTableStore
	 */
	private $slotRoleStore;

	/**
	 * @var string
	 */
	private $repositoryName;

	/**
	 * @param EntityNamespaceLookup $entityNamespaceLookup
	 * @param EntityTitleLookup $entityTitleLookup
	 * @param NameTableStore $slotRoleStore
	 * @param string|bool $wiki The name of the wiki database to use (use false for the local wiki)
	 * @param string $repositoryName The name of the repository to lookup from (use an empty string for the local repository)
	 */
	public function __construct(
		EntityNamespaceLookup $entityNamespaceLookup,
		EntityTitleLookup $entityTitleLookup,
		NameTableStore $slotRoleStore,
		$wiki = false,
		$repositoryName = ''
	) {
		RepositoryNameAssert::assertParameterIsValidRepositoryName( $repositoryName, '$repositoryName' );
		parent::__construct( $wiki );
		$this->entityNamespaceLookup = $entityNamespaceLookup;
		$this->entityTitleLookup = $entityTitleLookup;
		$this->slotRoleStore = $slotRoleStore;
		$this->repositoryName = $repositoryName;
	}

	/**
	 * @param EntityId[] $entityIds
	 * @param string $mode (EntityRevisionLookup::LATEST_FROM_REPLICA,
	 *     EntityRevisionLookup::LATEST_FROM_REPLICA_WITH_FALLBACK or
	 *     EntityRevisionLookup::LATEST_FROM_MASTER)
	 *
	 * @throws DBQueryError
	 * @throws InvalidArgumentException When some of $entityIds does not belong the repository of this lookup
	 *
	 * @return (stdClass|bool)[] Array mapping entity ID serializations to either objects or false if an entity
	 *  could not be found.
	 */
	public function loadRevisionInformation( array $entityIds, $mode ) {
		$rows = [];

		$this->assertEntityIdsFromRightRepository( $entityIds );

		if ( $mode !== EntityRevisionLookup::LATEST_FROM_MASTER ) {
			$rows = $this->selectRevisionInformationMultiple( $entityIds, DB_REPLICA );
		}

		if ( $mode !== EntityRevisionLookup::LATEST_FROM_REPLICA ) {
			// Attempt to load (missing) rows from master if the caller asked for that.
			$loadFromMaster = [];
			/** @var EntityId $entityId */
			foreach ( $entityIds as $entityId ) {
				if ( !isset( $rows[$entityId->getSerialization()] ) || !$rows[$entityId->getSerialization()] ) {
					$loadFromMaster[] = $entityId;
				}
			}

			if ( $loadFromMaster ) {
				$rows = array_merge(
					$rows,
					$this->selectRevisionInformationMultiple( $loadFromMaster, DB_MASTER )
				);
			}
		}

		return $rows;
	}

	/**
	 * @param EntityId $entityId
	 * @param int $revisionId
	 * @param string $mode (WikiPageEntityMetaDataAccessor::LATEST_FROM_REPLICA,
	 *     WikiPageEntityMetaDataAccessor::LATEST_FROM_REPLICA_WITH_FALLBACK or
	 *     WikiPageEntityMetaDataAccessor::LATEST_FROM_MASTER)
	 *
	 * @throws DBQueryError
	 * @throws InvalidArgumentException When $entityId does not belong the repository of this lookup
	 *
	 * @return stdClass|bool
	 */
	public function loadRevisionInformationByRevisionId(
		EntityId $entityId,
		$revisionId,
		$mode = EntityRevisionLookup::LATEST_FROM_MASTER
	) {
		$this->assertEntityIdFromRightRepository( $entityId );

		$row = $this->selectRevisionInformationById( $entityId, $revisionId, DB_REPLICA );

		if ( !$row && $mode !== EntityRevisionLookup::LATEST_FROM_REPLICA ) {
			// Try loading from master, unless the caller only wants replica data.
			wfDebugLog( __CLASS__, __FUNCTION__ . ': try to load ' . $entityId
				. " with $revisionId from DB_MASTER." );

			$row = $this->selectRevisionInformationById( $entityId, $revisionId, DB_MASTER );
		}

		return $row;
	}

	/**
	 * @param EntityId[] $entityIds
	 * @param string $mode (EntityRevisionLookup::LATEST_FROM_REPLICA,
	 *     EntityRevisionLookup::LATEST_FROM_REPLICA_WITH_FALLBACK or
	 *     EntityRevisionLookup::LATEST_FROM_MASTER)
	 *
	 * @throws DBQueryError
	 * @throws InvalidArgumentException When some of $entityIds does not belong the repository of this lookup
	 *
	 * @return (int|bool)[] Array mapping entity ID serializations to revision IDs
	 * or false if an entity could not be found (including if the page is a redirect).
	 */
	public function loadLatestRevisionIds( array $entityIds, $mode ) {
		$revisionIds = [];

		$this->assertEntityIdsFromRightRepository( $entityIds );

		if ( $mode !== EntityRevisionLookup::LATEST_FROM_MASTER ) {
			$revisionIds = $this->selectLatestRevisionIdsMultiple( $entityIds, DB_REPLICA );
		}

		if ( $mode !== EntityRevisionLookup::LATEST_FROM_REPLICA ) {
			// Attempt to load (missing) rows from master if the caller asked for that.
			$loadFromMaster = [];
			/** @var EntityId $entityId */
			foreach ( $entityIds as $entityId ) {
				if ( !isset( $revisionIds[$entityId->getSerialization()] ) || !$revisionIds[$entityId->getSerialization()] ) {
					$loadFromMaster[] = $entityId;
				}
			}

			if ( $loadFromMaster ) {
				$revisionIds = array_merge(
					$revisionIds,
					$this->selectLatestRevisionIdsMultiple( $loadFromMaster, DB_MASTER )
				);
			}
		}

		return $revisionIds;
	}

	/**
	 * @param EntityId $entityId
	 *
	 * @return bool
	 */
	private function isEntityIdFromRightRepository( EntityId $entityId ) {
		return $entityId->getRepositoryName() === $this->repositoryName;
	}

	/**
	 * @param EntityId $entityId
	 *
	 * @throws InvalidArgumentException When $entityId does not belong the repository of this lookup
	 */
	private function assertEntityIdFromRightRepository( EntityId $entityId ) {
		if ( !$this->isEntityIdFromRightRepository( $entityId ) ) {
			throw new InvalidArgumentException(
				'Could not load data from the database of repository: ' .
				$entityId->getRepositoryName()
			);
		}
	}

	/**
	 * @param EntityId[] $entityIds
	 *
	 * @throws InvalidArgumentException When some of $entityIds does not belong the repository of this lookup
	 */
	private function assertEntityIdsFromRightRepository( array $entityIds ) {
		foreach ( $entityIds as $entityId ) {
			$this->assertEntityIdFromRightRepository( $entityId );
		}
	}

	/**
	 * Fields we need to select to load a revision
	 *
	 * @return string[]
	 */
	private function selectFields() {
		 // XXX: This could just call RevisionStore::getQueryInfo and
		//  use the list of fields from there.
		return [
			'rev_id',
			'rev_timestamp',
			'page_latest',
			'page_is_redirect',
		];
	}

	/**
	 * Selects revision information from the page and revision tables.
	 *
	 * @param EntityId $entityId The entity to query the DB for.
	 * @param int $revisionId The desired revision id
	 * @param int $connType DB_REPLICA or DB_MASTER
	 *
	 * @throws DBQueryError If the query fails.
	 * @return stdClass|bool a raw database row object, or false if no such entity revision exists.
	 */
	private function selectRevisionInformationById( EntityId $entityId, $revisionId, $connType ) {
		$db = $this->getConnection( $connType );

		$join = [];
		$join['page'] = [ 'INNER JOIN', 'rev_page=page_id' ];

		wfDebugLog( __CLASS__, __FUNCTION__ . ": Looking up revision $revisionId of " . $entityId );

		$fields = $this->selectFields();

		// Attach the appropriate role name.
		// This could as well come from the database, if the query was written accordingly.
		$roleName = $this->entityNamespaceLookup->getEntitySlotRole(
			$entityId->getEntityType()
		);
		$fields['role_name'] = $db->addQuotes( $roleName );

		$row = $db->selectRow(
			[ 'revision', 'page' ],
			$fields,
			[ 'rev_id' => $revisionId ],
			__METHOD__,
			[],
			$join
		);

		$this->releaseConnection( $db );

		return $row;
	}

	/**
	 * Selects revision information from the page and revision tables.
	 * Returns an array like entityid -> object or false (if not found).
	 *
	 * @param EntityId[] $entityIds The entities to query the DB for.
	 * @param int $connType DB_REPLICA or DB_MASTER
	 *
	 * @throws DBQueryError If the query fails.
	 * @return array Array mapping entity ID serializations to either objects or false if an entity
	 *  could not be found.
	 */
	private function selectRevisionInformationMultiple( array $entityIds, $connType ) {
		$db = $this->getConnection( $connType );

		$join = [];
		$fields = $this->selectFields();
		// To be able to link rows with entity ids
		$fields[] = 'page_title';

		$tables = [ 'page', 'revision' ];

		// pick latest revision via page_latest
		$join['revision'] = [ 'INNER JOIN', 'page_latest=rev_id' ];

		list( $where, $slotJoinConds ) = $this->getWhere( $entityIds, $db );
		$join = array_merge( $join, $slotJoinConds );

		$res = $db->select(
			array_merge( $tables, $slotJoinConds ? [ 'slots' ] : [] ),
			$fields,
			$where,
			__METHOD__,
			[],
			$join
		);

		$this->releaseConnection( $db );

		return $this->indexResultByEntityId( $entityIds, $res );
	}

	/**
	 * Selects page_latest information from the page table.
	 * Returns an array like entityid -> int or false (if not found).
	 *
	 * @param EntityId[] $entityIds The entities to query the DB for.
	 * @param int $connType DB_REPLICA or DB_MASTER
	 *
	 * @throws DBQueryError If the query fails.
	 * @return array Array mapping entity ID serializations to either ints
	 * or false if an entity could not be found (including if the page is a redirect).
	 */
	private function selectLatestRevisionIdsMultiple( array $entityIds, $connType ) {
		$db = $this->getConnection( $connType );

		list( $where, $slotJoinConds ) = $this->getWhere( $entityIds, $db );

		$res = $db->select(
			array_merge( [ 'page'], $slotJoinConds ? [ 'slots' ] : [] ),
			[ 'page_title', 'page_latest', 'page_is_redirect' ],
			$where,
			__METHOD__,
			[],
			$slotJoinConds
		);

		$this->releaseConnection( $db );

		return array_map(
			function ( $revisionInformation ) {
				if ( !is_object( $revisionInformation ) ) {
					return $revisionInformation;
				}

				if ( $revisionInformation->page_is_redirect ) {
					return false;
				}

				return $revisionInformation->page_latest;
			},
			$this->indexResultByEntityId( $entityIds, $res )
		);
	}

	/**
	 * Takes a ResultWrapper and indexes the returned rows based on the serialized
	 * entity id of the entities they refer to.
	 *
	 * @param EntityId[] $entityIds
	 * @param IResultWrapper $res
	 *
	 * @return array Array mapping entity ID serializations to either objects or false if an entity
	 *  is not present in $res.
	 */
	private function indexResultByEntityId( array $entityIds, IResultWrapper $res ) {
		$rows = [];
		// Create a key based map from the rows just returned to reduce
		// the complexity below.
		foreach ( $res as $row ) {
			$rows[$row->page_title] = $row;
		}

		$result = [];
		foreach ( $entityIds as $entityId ) {
			// $rows is indexed by page titles without repository prefix but we want to keep prefixes
			// in the results returned by the lookup to match the input $entityIds
			$serializedId = $entityId->getSerialization();
			$idLocalPart = $entityId->getLocalPart();

			$result[$serializedId] = false;

			if ( isset( $rows[$idLocalPart] ) ) {
				$row = $rows[$idLocalPart];

				// Attach the appropriate role name.
				// This could as well come from the database, if the query was written accordingly.
				$row->role_name = $this->entityNamespaceLookup->getEntitySlotRole(
					$entityId->getEntityType()
				);

				$result[$serializedId] = $row;
			}
		}

		return $result;
	}

	/**
	 * @param EntityId[] $entityIds
	 * @param IDatabase $db
	 *
	 * @return array [ string $whereString, array $extraTables ]
	 */
	private function getWhere( array $entityIds, IDatabase $db ) {
		$where = [];
		$slotJoinConds = [];

		foreach ( $entityIds as $entityId ) {
			$title = $this->entityTitleLookup->getTitleForId( $entityId );
			if ( $title === null ) {
				// If we don't lookup the title, there is no chance we'll find a page.
				wfWarn(
					__METHOD__ . ': no title found for entity '
					. $entityId->getSerialization(),
					4
				);
				continue;
			}

			$slotRole = $this->entityNamespaceLookup->getEntitySlotRole( $entityId->getEntityType() );

			$conditions = [
				'page_title' => $title->getDBkey(),
				'page_namespace' => $title->getNamespace(),
			];

			/**
			 * Only check against the slot role when we are not using the main slot.
			 * If we are using the main slot, then we only need to check that the page
			 * exists rather than a specific slot within the page.
			 */
			if ( $slotRole !== 'main' ) {
				try{
					$slotRoleId = $this->slotRoleStore->getId( $slotRole );
				} catch ( NameTableAccessException $e ) {
					// The slot role is not yet saved, nothing to retrieve..
					continue;
				}

				$conditions['slot_role_id'] = $slotRoleId;
				$slotJoinConds = [ 'slots' => [ 'INNER JOIN', 'page_latest=slot_revision_id' ] ];
			}

			$where[] = $db->makeList(
				$conditions,
				LIST_AND
			);
		}

		if ( empty( $where ) ) {
			// If we skipped all entity IDs, select nothing, not everything.
			return [ '0=1', [] ];
		}

		return [ $db->makeList( $where, LIST_OR ), $slotJoinConds ];
	}

}
