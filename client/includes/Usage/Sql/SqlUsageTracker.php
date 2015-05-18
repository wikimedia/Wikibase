<?php

namespace Wikibase\Client\Usage\Sql;

use ArrayIterator;
use DatabaseBase;
use DBError;
use Exception;
use InvalidArgumentException;
use Iterator;
use Wikibase\Client\Store\Sql\ConsistentReadConnectionManager;
use Wikibase\Client\Usage\EntityUsage;
use Wikibase\Client\Usage\PageEntityUsages;
use Wikibase\Client\Usage\UsageLookup;
use Wikibase\Client\Usage\UsageTracker;
use Wikibase\Client\Usage\UsageTrackerException;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;

/**
 * An SQL based usage tracker implementation.
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class SqlUsageTracker implements UsageTracker, UsageLookup {

	/**
	 * @var EntityIdParser
	 */
	private $idParser;

	/**
	 * @var ConsistentReadConnectionManager
	 */
	private $connectionManager;

	/**
	 * @var int
	 */
	private $batchSize = 1000;

	/**
	 * @param EntityIdParser $idParser
	 * @param ConsistentReadConnectionManager $connectionManager
	 */
	public function __construct( EntityIdParser $idParser, ConsistentReadConnectionManager $connectionManager ) {
		$this->idParser = $idParser;
		$this->connectionManager = $connectionManager;
	}

	/**
	 * @param DatabaseBase $db
	 *
	 * @return UsageTableUpdater
	 */
	private function newTableUpdater( DatabaseBase $db ) {
		return new UsageTableUpdater( $db, 'wbc_entity_usage', $this->batchSize );
	}

	/**
	 * Sets the query batch size.
	 *
	 * @param int $batchSize
	 *
	 * @throws InvalidArgumentException
	 */
	public function setBatchSize( $batchSize ) {
		if ( !is_int( $batchSize ) || $batchSize < 1 ) {
			throw new InvalidArgumentException( '$batchSize must be an integer >= 1' );
		}

		$this->batchSize = $batchSize;
	}

	/**
	 * Returns the current query batch size.
	 *
	 * @return int
	 */
	public function getBatchSize() {
		return $this->batchSize;
	}

	/**
	 * @param EntityId[] $entityIds
	 *
	 * @return string[]
	 */
	private function getEntityIdStrings( array $entityIds ) {
		return array_map( function( EntityId $entityId ) {
			return $entityId->getSerialization();
		}, $entityIds );
	}

	/**
	 * @see UsageTracker::trackUsedEntities
	 *
	 * @param int $pageId
	 * @param EntityUsage[] $usages
	 * @param string $touched
	 *
	 * @throws Exception
	 * @throws UsageTrackerException
	 */
	public function trackUsedEntities( $pageId, array $usages, $touched ) {
		if ( !is_int( $pageId ) ) {
			throw new InvalidArgumentException( '$pageId must be an int.' );
		}

		if ( !is_string( $touched ) || $touched === '' ) {
			throw new InvalidArgumentException( '$touched must be a timestamp string.' );
		}

		if ( empty( $usages ) ) {
			return;
		}

		$db = $this->connectionManager->beginAtomicSection( __METHOD__ );

		try {
			$oldUsages = $this->queryUsagesForPage( $db, $pageId, '00000000000000' );
			$tableUpdater = $this->newTableUpdater( $db );
			$tableUpdater->updateUsage( $pageId, $oldUsages, $usages, $touched );

			$this->connectionManager->commitAtomicSection( $db, __METHOD__ );
		} catch ( Exception $ex ) {
			$this->connectionManager->rollbackAtomicSection( $db, __METHOD__ );

			if ( $ex instanceof DBError ) {
				throw new UsageTrackerException( $ex->getMessage(), $ex->getCode(), $ex );
			} else {
				throw $ex;
			}
		}
	}

	/**
	 * @see UsageTracker::pruneStaleUsages
	 *
	 * @param int $pageId
	 * @param string $lastUpdatedBefore timestamp
	 *
	 * @throws Exception
	 * @throws UsageTrackerException
	 */
	public function pruneStaleUsages( $pageId, $lastUpdatedBefore ) {
		if ( empty( $lastUpdatedBefore ) ) {
			return;
		}

		$db = $this->connectionManager->beginAtomicSection( __METHOD__ );

		try {
			$tableUpdater = $this->newTableUpdater( $db );
			$tableUpdater->pruneStaleUsages( $pageId, $lastUpdatedBefore );

			$this->connectionManager->commitAtomicSection( $db, __METHOD__ );
		} catch ( Exception $ex ) {
			$this->connectionManager->rollbackAtomicSection( $db, __METHOD__ );

			if ( $ex instanceof DBError ) {
				throw new UsageTrackerException( $ex->getMessage(), $ex->getCode(), $ex );
			} else {
				throw $ex;
			}
		}
	}

	/**
	 * @see UsageTracker::removeEntities
	 *
	 * @param EntityId[] $entityIds
	 *
	 * @throws UsageTrackerException
	 * @throws Exception
	 */
	public function removeEntities( array $entityIds ) {
		if ( empty( $entityIds ) ) {
			return;
		}

		$idStrings = $this->getEntityIdStrings( $entityIds );

		$db = $this->connectionManager->beginAtomicSection( __METHOD__ );

		try {
			$tableUpdater = $this->newTableUpdater( $db );
			$tableUpdater->removeEntities( $idStrings );

			$this->connectionManager->commitAtomicSection( $db, __METHOD__ );
		} catch ( Exception $ex ) {
			$this->connectionManager->rollbackAtomicSection( $db, __METHOD__ );

			if ( $ex instanceof DBError ) {
				throw new UsageTrackerException( $ex->getMessage(), $ex->getCode(), $ex );
			} else {
				throw $ex;
			}
		}
	}

	/**
	 * @see UsageLookup::getUsagesForPage
	 *
	 * @param int $pageId
	 * @param string $touchedSince timestamp (default: "since ever")
	 *
	 * @return EntityUsage[]
	 */
	public function getUsagesForPage( $pageId, $touchedSince = '00000000000000' ) {
		$db = $this->connectionManager->getReadConnection();

		$usages = $this->queryUsagesForPage( $db, $pageId, $touchedSince );

		$this->connectionManager->releaseConnection( $db );

		return $usages;
	}

	/**
	 * @param DatabaseBase $db
	 * @param int $pageId
	 * @param string $touchedSince
	 *
	 * @return EntityUsage[]
	 */
	private function queryUsagesForPage( DatabaseBase $db, $pageId, $touchedSince ) {
		if ( !is_int( $pageId ) ) {
			throw new InvalidArgumentException( '$pageId must be an int.' );
		}

		if ( !is_string( $touchedSince ) || $touchedSince === '' ) {
			throw new InvalidArgumentException( '$touchedSince must be a timestamp string.' );
		}

		$touchedSince = wfTimestamp( TS_MW, $touchedSince );

		$res = $db->select(
			'wbc_entity_usage',
			array( 'eu_aspect', 'eu_entity_id' ),
			array(
				'eu_page_id' => $pageId,
				'eu_touched >= ' . $db->addQuotes( $touchedSince )
			),
			__METHOD__
		);

		$usages = $this->convertRowsToUsages( $res );
		return $usages;
	}

	/**
	 * @param array|Iterator $rows
	 *
	 * @return EntityUsage[]
	 */
	private function convertRowsToUsages( $rows ) {
		$usages = array();

		foreach ( $rows as $object ) {
			$entityId = $this->idParser->parse( $object->eu_entity_id );
			$usage = new EntityUsage( $entityId, $object->eu_aspect );
			$key = $usage->getIdentityString();
			$usages[$key] = $usage;
		}

		return $usages;
	}

	/**
	 * @see UsageLookup::getPagesUsing
	 *
	 * @param EntityId[] $entityIds
	 * @param string[] $aspects
	 *
	 * @return Iterator<PageEntityUsages> An iterator over entity usages grouped by page
	 * @throws UsageTrackerException
	 */
	public function getPagesUsing( array $entityIds, array $aspects = array() ) {
		if ( empty( $entityIds ) ) {
			return new ArrayIterator();
		}

		$idStrings = $this->getEntityIdStrings( $entityIds );
		$where = array( 'eu_entity_id' => $idStrings );

		if ( !empty( $aspects ) ) {
			$where['eu_aspect'] = $aspects;
		}

		$db = $this->connectionManager->getReadConnection();

		//XXX: join against page to filter by page_touched?

		$res = $db->select(
			'wbc_entity_usage',
			array( 'eu_page_id', 'eu_entity_id', 'eu_aspect' ),
			$where,
			__METHOD__
		);

		$pages = $this->foldRowsIntoPageEntityUsages( $res );

		$this->connectionManager->releaseConnection( $db );

		//TODO: use paging for large page sets!
		return new ArrayIterator( $pages );
	}

	/**
	 * @param array|Iterator $rows
	 *
	 * @return PageEntityUsages[]
	 */
	private function foldRowsIntoPageEntityUsages( $rows ) {
		$usagesPerPage = array();

		foreach ( $rows as $row ) {
			$pageId = (int)$row->eu_page_id;

			if ( isset( $usagesPerPage[$pageId] ) ) {
				$pageEntityUsages = $usagesPerPage[$pageId];
			} else {
				$pageEntityUsages = new PageEntityUsages( $pageId );
			}

			$entityId = $this->idParser->parse( $row->eu_entity_id );
			$usage = new EntityUsage( $entityId, $row->eu_aspect );
			$pageEntityUsages->addUsages( array( $usage ) );

			$usagesPerPage[$pageId] = $pageEntityUsages;
		}

		return $usagesPerPage;
	}

	/**
	 * @see UsageLookup::getUnusedEntities
	 *
	 * @param EntityId[] $entityIds
	 *
	 * @return EntityId[]
	 * @throws UsageTrackerException
	 */
	public function getUnusedEntities( array $entityIds ) {
		if ( empty( $entityIds ) ) {
			return array();
		}

		$entityIdMap = array();

		foreach ( $entityIds as $entityId ) {
			$idString = $entityId->getSerialization();
			$entityIdMap[$idString] = $entityId;
		}

		$usedIdStrings = $this->getUsedEntityIdStrings( array_keys( $entityIdMap ) );

		return array_diff_key( $entityIdMap, array_flip( $usedIdStrings ) );
	}

	/**
	 * Returns those entity ids which are used from a given set of entity ids.
	 *
	 * @param string[] $idStrings
	 *
	 * @return string[]
	 */
	private function getUsedEntityIdStrings( array $idStrings ) {
		$where = array( 'eu_entity_id' => $idStrings );

		$db = $this->connectionManager->getReadConnection();

		$res = $db->select(
			'wbc_entity_usage',
			array( 'eu_entity_id' ),
			$where,
			__METHOD__
		);

		$this->connectionManager->releaseConnection( $db );

		return $this->extractProperty( $res, 'eu_entity_id' );
	}

	/**
	 * Returns an array of values extracted from the $key property from each object.
	 *
	 * @param array|Iterator $objects
	 * @param string $key
	 *
	 * @return array
	 */
	private function extractProperty( $objects, $key ) {
		$array = array();

		foreach ( $objects as $object ) {
			$array[] = $object->$key;
		}

		return $array;
	}

}
