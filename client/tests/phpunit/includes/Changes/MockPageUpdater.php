<?php

namespace Wikibase\Client\Tests\Changes;

use Title;
use Wikibase\Client\Changes\PageUpdater;
use Wikibase\EntityChange;

/**
 * Mock version of the service object for triggering different kinds of page updates
 * and generally notifying the local wiki of external changes.
 *
 * Used for testing ChangeHandler.
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class MockPageUpdater implements PageUpdater {

	private $updates = [
		'purgeWebCache' => [],
		'scheduleRefreshLinks' => [],
		'injectRCRecord' => [],
	];

	/**
	 * @param Title[] $titles
	 * @param array $rootJobParams
	 */
	public function purgeWebCache( array $titles, array $rootJobParams = [] ) {
		foreach ( $titles as $title ) {
			$key = $title->getPrefixedDBkey();
			$this->updates['purgeWebCache'][ $key ] = $title;
		}
	}

	/**
	 * @param Title[] $titles
	 * @param array $rootJobParams
	 */
	public function scheduleRefreshLinks( array $titles, array $rootJobParams = [] ) {
		foreach ( $titles as $title ) {
			$key = $title->getPrefixedDBkey();
			$this->updates['scheduleRefreshLinks'][ $key ] = $title;
		}
	}

	/**
	 * @param Title[] $titles
	 * @param EntityChange $change
	 * @param array $rootJobParams
	 */
	public function injectRCRecords( array $titles, EntityChange $change, array $rootJobParams = [] ) {
		foreach ( $titles as $title ) {
			$key = $title->getPrefixedDBkey();
			$this->updates['injectRCRecord'][ $key ] = $change;
		}
	}

	public function getUpdates() {
		return $this->updates;
	}

}
