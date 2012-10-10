<?php

namespace Wikibase;
use MWException;

/**
 * Class representing the wb_changes table.
 * This class is intended to be used in later versions of
 * change handling stuff
 *
 * TODO: incorporate revision info in wb_changes and access
 * changes as a foreign wiki db table using the LoadBalancer stuff
 *
 * @since 0.2
 *
 * @file
 * @ingroup WikibaseClient
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @deprecated to be replaced with ORMTable and foreign wiki stuff
 */
class SharedRevision {

        public static function newFromChange( $change ) {
		$dbname = Settings::get( 'changesDB' );
		$db = self::getRepoDB( $dbname );
		$revid = $change->getField( 'revision_id' );
		$row = self::getRevision( $db, $revid );
		return $row;
	}

	/**
	 * Gets a row from the revisions table of the repo wiki
	 *
	 * @since 0.2
	 *
	 * @param \Database $db
	 * @param int $revid
	 *
	 * @return stdClass|bool
	 */
	protected static function getRevision( $db, $revid ) {
		$row = $db->selectRow(
			'revision',
			'*',
			array(
				'rev_id' => $revid
			)
		);
		return $row;
	}

	/**
	 * Gets a master (read/write) database connection to the wikidata database
	 *
	 * @return DatabaseBase
	 */
	public static function getRepoDB( $dbname ) {
		global $wgReadOnly;
		if ( $wgReadOnly === true ) {
			return false;
		}

		return wfGetLB( $dbname )->getConnection( DB_MASTER, array(), $dbname );
	}

	/**
	 * Gets a slave (readonly) database connection to the wikidata database
	 *
	 * @return DatabaseBase
	 */
	public static function getRepoSlaveDB( $dbname ) {
		return wfGetLB( $dbname )->getConnection( DB_SLAVE, 'wikidata', $dbname );
	}
}
