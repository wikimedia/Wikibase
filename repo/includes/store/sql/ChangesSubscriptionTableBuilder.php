<?php

namespace Wikibase\Repo\Store\Sql;

use DatabaseBase;
use InvalidArgumentException;
use LoadBalancer;
use ResultWrapper;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Reporting\ExceptionHandler;
use Wikibase\Lib\Reporting\LogWarningExceptionHandler;
use Wikibase\Lib\Reporting\MessageReporter;
use Wikibase\Lib\Reporting\NullMessageReporter;

/**
 * Implements initial population (priming) for the wb_changes_subscription table,
 * based on the wb_items_per_site. Any wiki linked via the wb_items_per_site table
 * will be considered a subscriber.
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class ChangesSubscriptionTableBuilder {

	/**
	 * @var LoadBalancer
	 */
	private $loadBalancer;

	/**
	 * @var int
	 */
	private $batchSize;

	/**
	 * @var ExceptionHandler
	 */
	private $exceptionHandler;

	/**
	 * @var MessageReporter
	 */
	private $progressReporter;

	/**
	 * @param LoadBalancer $loadBalancer
	 * @param string $tableName
	 * @param int $batchSize
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( LoadBalancer $loadBalancer, $tableName, $batchSize = 1000 ) {
		if ( !is_string( $tableName ) ) {
			throw new InvalidArgumentException( '$tableName must be a string' );
		}

		if ( !is_int( $batchSize ) || $batchSize < 1 ) {
			throw new InvalidArgumentException( '$batchSize must be an integer >= 1' );
		}

		$this->loadBalancer = $loadBalancer;
		$this->tableName = $tableName;
		$this->batchSize = $batchSize;

		$this->exceptionHandler = new LogWarningExceptionHandler();
		$this->progressReporter = new NullMessageReporter();
	}

	/**
	 * @param MessageReporter $progressReporter
	 */
	public function setProgressReporter( MessageReporter $progressReporter ) {
		$this->progressReporter = $progressReporter;
	}

	/**
	 * @return MessageReporter
	 */
	public function getProgressReporter() {
		return $this->progressReporter;
	}

	/**
	 * @param ExceptionHandler $exceptionHandler
	 */
	public function setExceptionHandler( ExceptionHandler $exceptionHandler ) {
		$this->exceptionHandler = $exceptionHandler;
	}

	/**
	 * @return ExceptionHandler
	 */
	public function getExceptionHandler() {
		return $this->exceptionHandler;
	}

	/**
	 * Fill the subscription table with rows based on entries in page_props.
	 *
	 * @param ItemId $startItem The item to start with.
	 */
	public function fillSubscriptionTable( ItemId $startItem = null ) {
		$continuation = $startItem === null ? null : array( $startItem->getNumericId(), '' );

		while ( true ) {
			$count = $this->processSubscriptionBatch( $continuation );

			if ( $count > 0 ) {
				$this->progressReporter->reportMessage( "Populating subscription table: inserted $count subscriptions, continuing at item #{$continuation[0]}." );
			} else {
				break;
			}
		};
	}

	/**
	 * @param array &$continuation
	 *
	 * @return int The number of subscriptions inserted.
	 */
	private function processSubscriptionBatch( &$continuation = array() ) {
		$db = $this->loadBalancer->getConnection( DB_MASTER );

		$entityPerPage = $this->getSubscriptionBatch( $db, $continuation );

		if ( empty( $entityPerPage ) ) {
			return 0;
		}

		$count = $this->insertSubscriptionBatch( $db, $entityPerPage );

		$this->loadBalancer->reuseConnection( $db );

		return $count;
	}

	/**
	 * @param DatabaseBase $db
	 * @param array[] $subscriptionsPerItem
	 *
	 * @return int The number of rows inserted.
	 */
	private function insertSubscriptionBatch( DatabaseBase $db, array $subscriptionsPerItem ) {
		$db->startAtomic( __METHOD__ );

		$c = 0;
		foreach ( $subscriptionsPerItem as $itemId => $subscribers ) {
			$rows = $this->makeSubscriptionRows( $itemId, $subscribers );

			$db->insert(
				$this->tableName,
				$rows,
				__METHOD__,
				array(
					'IGNORE'
				)
			);

			$c+= count( $rows );
		}

		$db->endAtomic( __METHOD__ );
		return $c;
	}

	/**
	 * @param DatabaseBase $db
	 * @param array &$continuation
	 *
	 * @return array[] An associative array mapping item IDs to lists of site IDs.
	 */
	private function getSubscriptionBatch( DatabaseBase $db, &$continuation = array() ) {

		if ( empty( $continuation ) ) {
			$continuationCondition = '1';
		} else {
			list( $fromItemId, $fromSiteId ) = $continuation;
			$continuationCondition = 'ips_item_id > ' . (int)$fromItemId
				. ' OR ( '
					. 'ips_item_id = ' . (int)$fromItemId
					. ' AND '
					. 'ips_site_id > ' . $db->addQuotes( $fromSiteId )
				. ' )';
		}

		$res = $db->select(
			'wb_items_per_site',
			array( 'ips_item_id', 'ips_site_id' ),
			$continuationCondition,
			__METHOD__,
			array(
				'LIMIT' => $this->batchSize,
				'ORDER BY ips_item_id, ips_site_id'
			)
		);

		return $this->slurpSubscriptions( $res, $continuation );
	}

	/**
	 * @param ResultWrapper $res A result set with the ips_item_id and ips_site_id fields
	 *        set for each row.
	 * @param array &$continuation
	 *
	 * @return array[] An associative array mapping item IDs to lists of site IDs.
	 */
	private function slurpSubscriptions( ResultWrapper $res, &$continuation = array() ) {
		$entityPerPage = array();

		$currentId = 0;
		$key = null;

		foreach ( $res as $row ) {
			if ( $row->ips_item_id != $currentId ) {
				$currentId = $row->ips_item_id;
				$key = ItemId::newFromNumber( $currentId )->getSerialization();
			}

			$entityPerPage[$key][] = $row->ips_site_id;
			$continuation = array( $currentId, $row->ips_site_id );
		}

		return $entityPerPage;
	}

	/**
	 * Returns a list of rows for insertion, using DatabaseBase's multi-row insert mechanism.
	 * Each row is represented as array( $itemId, $subscriber ).
	 *
	 * @param string $itemId
	 * @param string[] $subscribers
	 *
	 * @return array[] rows
	 */
	private function makeSubscriptionRows( $itemId, array $subscribers ) {
		$rows = array();

		foreach ( $subscribers as $subscriber ) {
			$rows[] = array(
				'cs_entity_id' => $itemId,
				'cs_subscriber_id' => $subscriber
			);
		}

		return $rows;
	}

}
