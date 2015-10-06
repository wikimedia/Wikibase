<?php

namespace Wikibase\Repo\Notifications;

use DatabaseBase;
use DBQueryError;
use Wikibase\Change;
use Wikibase\ChangeRow;

/**
 * Notification channel based on a database table.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 * @author Marius Hoch
 */
class DatabaseChangeTransmitter implements ChangeTransmitter {

	/**
	 * @var DatabaseBase
	 */
	private $connection;

	/**
	 * @param DatabaseBase $db
	 */
	public function __construct( DatabaseBase $db ) {
		$this->connection = $db;
	}

	/**
	 * @see ChangeNotificationChannel::sendChangeNotification()
	 *
	 * Saves the change to a database table.
	 *
	 * @note Only supports Change objects that are derived from ChangeRow.
	 *
	 * @param Change $change
	 *
	 * @throws DBQueryError
	 */
	public function transmitChange( Change $change ) {
		$this->connection->insert(
			'wb_changes',
			$this->getInsertValues( $change ),
			__METHOD__
		);

		$change->setField( 'id', $this->connection->insertId() );
	}

	/**
	 * @param ChangeRow $change
	 *
	 * @return array
	 */
	private function getInsertValues( ChangeRow $change ) {
		$fields = $change->getFields();

		$time = isset( $fields['time'] ) ? $fields['time'] : wfTimestampNow();
		$objectId = isset( $fields['object_id'] ) ? $fields['object_id'] : '';
		$revId = isset( $fields['revision_id'] ) ? $fields['revision_id'] : '0';
		$userId = isset( $fields['user_id'] ) ? $fields['user_id'] : '0';
		$info = $change->serializeInfo( $fields['info'] );

		return array(
			'change_type' => $fields['type'],
			'change_time' => $time,
			'change_object_id' => $objectId,
			'change_revision_id' => $revId,
			'change_user_id' => $userId,
			'change_info' => $info
		);
	}

}
