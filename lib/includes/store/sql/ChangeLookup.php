<?php

namespace Wikibase\Lib\Store;

use DBAccessBase;
use ResultWrapper;
use Wikibase\Change;
use Wikibase\ChunkAccess;
use Wikimedia\Assert\Assert;

/**
 * Allows accessing changes stored in a database.
 *
 * @since 0.5
 *
 * @license GNU GPL v2+
 * @author Marius Hoch
 */
class ChangeLookup extends DBAccessBase implements ChunkAccess {

	/**
	 * Flag to indicate that we need to query a master database.
	 */
	const FROM_MASTER = 'master';

	const FROM_SLAVE = 'slave';

	/**
	 * @var string[]
	 */
	private $changeHandlers;

	/**
	 * @param array $changeHandlers Value of the "changeHandlers" setting (change type to class map)
	 * @param string|bool $wiki The target wiki's name. This must be an ID
	 * that LBFactory can understand.
	 */
	public function __construct( array $changeHandlers, $wiki = false ) {
		Assert::parameterElementType( 'string', $changeHandlers, '$changeHandlers' );

		parent::__construct( $wiki );
		$this->changeHandlers = $changeHandlers;
	}

	/**
	 * Returns the sequential ID of the given Change.
	 *
	 * @param Change $rec
	 *
	 * @return int
	 */
	public function getRecordId( $rec ) {
		Assert::parameterType( 'Wikibase\Change', $rec, '$rec' );

		/* @var Change $rec */
		return $rec->getId();
	}

	/**
	 * @param int $start
	 * @param int $size
	 *
	 * @return Change[]
	 */
	public function loadChunk( $start, $size ) {
		Assert::parameterType( 'integer', $start, '$start' );
		Assert::parameterType( 'integer', $size, '$size' );

		return $this->loadChanges(
			array( 'change_id >= ' . (int)$start ),
			array(
				'ORDER BY' => 'change_id ASC',
				'LIMIT' => $size
			),
			__METHOD__
		);
	}

	/**
	 * @param int[] $ids
	 *
	 * @return Change[]
	 */
	public function loadByChangeIds( $ids ) {
		Assert::parameterElementType( 'integer', $ids, '$ids' );

		return $this->loadChanges(
			array( 'change_id' => $ids ),
			array(),
			__METHOD__
		);
	}

	/**
	 * @param int $revisionId
	 * @param string $mode (ChangeLookup::FROM_SLAVE or ChangeLookup::FROM_MASTER)
	 *
	 * @return Change|null
	 */
	public function loadByRevisionId( $revisionId, $mode = ChangeLookup::FROM_SLAVE ) {
		Assert::parameterType( 'integer', $revisionId, '$revisionId' );

		$change = $this->loadChanges(
			array( 'change_revision_id' => $revisionId ),
			array(
				'LIMIT' => 1
			),
			__METHOD__,
			$mode === ChangeLookup::FROM_MASTER ? DB_MASTER : DB_SLAVE
		);

		if ( isset( $change[0] ) ) {
			return $change[0];
		} else {
			return null;
		}
	}

	/**
	 * @param array $where
	 * @param array $options
	 * @param string $method
	 * @param int $mode (DB_SLAVE or DB_MASTER)
	 *
	 * @return Change[]
	 */
	private function loadChanges( array $where, array $options, $method, $mode = DB_SLAVE ) {
		$dbr = $this->getConnection( $mode );

		$rows = $dbr->select(
			'wb_changes',
			array(
				'change_id', 'change_type', 'change_time', 'change_object_id',
				'change_revision_id', 'change_user_id', 'change_info'
			),
			$where,
			$method,
			$options
		);

		return $this->changesFromRows( $rows );
	}

	private function changesFromRows( ResultWrapper $rows ) {
		$changes = array();
		foreach ( $rows as $row ) {
			$class = $this->getClassForType( $row->change_type );
			$data = array(
				'id' => (int)$row->change_id,
				'type' => $row->change_type,
				'time' => $row->change_time,
				'info' => $row->change_info,
				'object_id' => $row->change_object_id,
				'user_id' => $row->change_user_id,
				'revision_id' => $row->change_revision_id,
			);

			$changes[] = new $class( null, $data, false );
		}

		return $changes;
	}

	/**
	 * Returns the name of a class that can handle changes of the provided type.
	 *
	 * @param string $type
	 *
	 * @return string
	 */
	private function getClassForType( $type ) {
		if ( array_key_exists( $type, $this->changeHandlers ) ) {
			return $this->changeHandlers[$type];
		} else {
			return 'Wikibase\ChangeRow';
		}
	}

}
