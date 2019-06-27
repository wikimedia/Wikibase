<?php

namespace Wikibase\Lib\Store\Sql;

use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wikimedia\Rdbms\DBQueryError;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Allows acquiring ids of records in database table,
 * by inspecting a given read-only replica database to initially
 * find existing records with their ids, and insert non-existing
 * records into a read-write master databas and getting those
 * ids as well from the master database after insertion.
 *
 * @license GPL-2.0-or-later
 */
class ReplicaMasterAwareRecordIdsAcquirer implements RecordIdsAcquirer {

	/**
	 * This flag changes this object's behavior so that it always queries
	 * master database to find existing items, bypassing replica database
	 * completely.
	 */
	const FLAG_IGNORE_REPLICA = 0x1;

	/**
	 * @var ILoadBalancer
	 */
	private $loadBalancer;

	/**
	 * @var IDatabase master database to insert non-existing records into
	 */
	private $dbMaster = null;

	/**
	 * @var IDatabase replica database to initially query existing records in
	 */
	private $dbReplica = null;

	/**
	 * @var string
	 */
	private $table;

	/**
	 * @var string
	 */
	private $idColumn;

	/**
	 * @var LoggerInterface|null
	 */
	private $logger;

	/**
	 * @var int
	 */
	private $flags;

	/**
	 * @param ILoadBalancer $loadBalancer database connection accessor
	 * @param string $table the name of the table this acquirer is for
	 * @param string $idColumn the name of the column that contains the desired ids
	 * @param LoggerInterface|null $logger
	 * @param int $flags {@see self::FLAG_IGNORE_REPLICA}
	 */
	public function __construct(
		ILoadBalancer $loadBalancer,
		$table,
		$idColumn,
		LoggerInterface $logger = null,
		$flags = 0x0
	) {
		$this->loadBalancer = $loadBalancer;
		$this->table = $table;
		$this->idColumn = $idColumn;
		$this->logger = $logger ?? new NullLogger();
		$this->flags = $flags;
	}

	/**
	 * Note 1: this function assumes that all records given in $neededRecords specify
	 * the same columns. If some records specify less, more or different columns than
	 * the first one does, the behavior is not defined. The first element keys will be
	 * used as the set of columns to select in database and to provide back in the returned array.
	 *
	 * Note 2: this function assumes that all records given in $neededRecords have
	 * their values as strings. If some values are of different type (e.g. integer ids)
	 * this can cause a false mismatch in identifying records selected in
	 * database with their corresponding needed records.
	 */
	public function acquireIds(
		array $neededRecords,
		$recordsToInsertDecoratorCallback = null
	) {
		$dbr = $this->isIgnoringReplica() ? $this->getDbMaster() : $this->getDbReplica();
		$existingRecords = $this->findExistingRecords( $dbr, $neededRecords );
		$neededRecords = $this->filterNonExistingRecords( $neededRecords, $existingRecords );

		while ( !empty( $neededRecords ) ) {
			if ( is_callable( $recordsToInsertDecoratorCallback ) ) {
				$neededRecords = $recordsToInsertDecoratorCallback( $neededRecords );
			}

			$neededRecordsCount = count( $neededRecords );

			$this->insertNonExistingRecordsIntoMaster( $neededRecords );

			$existingRecords = array_merge(
				$existingRecords,
				$this->findExistingRecords( $this->getDbMaster(), $neededRecords )
			);
			$neededRecords = $this->filterNonExistingRecords( $neededRecords, $existingRecords );

			if ( count( $neededRecords ) === $neededRecordsCount ) {
				// This is a fail-safe capture in order to avoid an infinite loop when insertion
				// fails due to duplication, but selection in the next loop iteration still
				// cannot detect those existing records for any reason.
				// This has one caveat that failures due to other reasons other than duplication
				// constraint violation will also result in a failure to this function entirely.
				$exception = new Exception(
					'Fail-safe exception. Avoiding infinite loop due to possibily undetectable'
					. " existing records in master.\n"
					. ' It may be due to encoding incompatibility'
					. ' between database values and values passed in $neededRecords parameter.'
				);

				$this->logger->warning(
					'{method}: Acquiring record ids failed: {exception}',
					[
						'method' => __METHOD__,
						'exception' => $exception,
						'table' => $this->table,
						'neededRecords' => $neededRecords,
						'existingRecords' => $existingRecords,
					]
				);

				throw $exception;
			}
		}

		return $existingRecords;
	}

	private function getDbReplica() {
		if ( $this->dbReplica === null ) {
			$this->dbReplica = $this->loadBalancer->getConnection( ILoadBalancer::DB_REPLICA );
		}

		return $this->dbReplica;
	}

	private function getDbMaster() {
		if ( $this->dbMaster === null ) {
			$this->dbMaster = $this->loadBalancer->getConnection( ILoadBalancer::DB_MASTER );
		}

		return $this->dbMaster;
	}

	private function findExistingRecords( IDatabase $db, array $neededRecords ): array {
		$recordsSelectConditions = array_map( function ( $record ) use ( $db ) {
			return $db->makeList( $record, IDatabase::LIST_AND );
		}, $neededRecords );

		/*
		 * Todo, related to Note 1 on self::acquireIds():
		 * this class can allow for specifying a different set of columns to select
		 * and return back from self::acquireIds(). This set of columns can be added as
		 * an optional argument to self::acquireIds() for instance, the current solution
		 * in here can be a fallback when that isn't given.
		 */
		$selectColumns = array_keys( $neededRecords[0] );
		$selectColumns[] = $this->idColumn;

		$existingRows = $db->select(
			$this->table,
			$selectColumns,
			$db->makeList( $recordsSelectConditions, IDatabase::LIST_OR )
		);

		$existingRecords = [];
		foreach ( $existingRows as $row ) {
			$existingRecord = [];
			foreach ( $selectColumns as $column ) {
				$existingRecord[$column] = $row->$column;
			}
			$existingRecords[] = $existingRecord;
		}

		return $existingRecords;
	}

	/**
	 * @param array $neededRecords
	 * @suppress SecurityCheck-SQLInjection
	 */
	private function insertNonExistingRecordsIntoMaster( array $neededRecords ) {
		try {
			$this->getDbMaster()->insert( $this->table, $neededRecords );
		} catch ( DBQueryError $dbError ) {
			$this->logger->info(
				'{method}: Inserting records into {table} failed: {exception}',
				[
					'method' => __METHOD__,
					'exception' => $dbError,
					'table' => $this->table,
					'records' => $neededRecords
				]
			);
		}
	}

	private function filterNonExistingRecords( $neededRecords, $existingRecords ): array {
		$existingRecordsHashes = [];
		foreach ( $existingRecords as $record ) {
			unset( $record[$this->idColumn] );
			$recordHash = $this->calcRecordHash( $record );
			$existingRecordsHashes[$recordHash] = true;
		}

		$nonExistingRecords = [];
		foreach ( $neededRecords as $record ) {
			unset( $record[$this->idColumn] );
			$recordHash = $this->calcRecordHash( $record );

			if ( !isset( $existingRecordsHashes[$recordHash] ) ) {
				$nonExistingRecords[] = $record;
			}
		}

		return $nonExistingRecords;
	}

	private function calcRecordHash( array $record ) {
		ksort( $record );
		return md5( serialize( $record ) );
	}

	private function isIgnoringReplica() {
		return ( $this->flags & self::FLAG_IGNORE_REPLICA ) !== 0x0;
	}

}
