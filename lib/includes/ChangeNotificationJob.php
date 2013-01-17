<?php

namespace Wikibase;

/**
 * Job for notifying a client wiki of a batch of changes on the repository.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @since 0.4
 *
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class ChangeNotificationJob extends \Job {

	/**
	 * @var Change[] $changes: initialized lazily by getChanges().
	 */
	protected $changes = null;

	/**
	 * Creates a ChangeNotificationJob representing the given changes.
	 *
	 * @param array       $changes The list of changes to be processed
	 * @param string      $repo The name of the repository the changes come from (default: "").
	 * @param array|bool  $params extra job parameters, see Job::__construct (default: false).
	 *
	 * @return \Wikibase\ChangeNotificationJob: the job
	 */
	public static function newFromChanges( array $changes, $repo = '', $params = false ) {
		static $dummyTitle = null;

		// Note: we don't really care about the title and will use a dummy
		if ( $dummyTitle === null ) {
			// The Job class wants a Title object for some reason. Supply a dummy.
			$dummyTitle = \Title::newFromText( "ChangeNotificationJob", NS_SPECIAL );
		}

		// get the list of change IDs
		$changeIds = array_map(
			function ( Change $change ) {
				return $change->getId();
			},
			$changes
		);

		if ( $params === false ) {
			$params = array();
		}

		$params['repo'] = $repo;
		$params['changeIds'] = $changeIds;

		return new ChangeNotificationJob( $dummyTitle, $params );
	}

	/**
	 * Constructs a ChangeNotificationJob representing the changes given by $changeIds.
	 *
	 * @note: This is for use by Job::factory, don't call it directly;
	 *           use newFromChanges() instead.
	 *
	 * @note: the constructor's signature is dictated by Job::factory, so we'll have to
	 *           live with it even though it's rather ugly for our use case.
	 *
	 * @see      Job::factory.
	 *
	 * @param \Title $title ignored
	 * @param  $params array|bool
	 * @param  $id     int
	 */
	public function __construct( \Title $title, $params = false, $id = 0 ) {
		parent::__construct( 'ChangeNotification', $title, $params, $id );
	}

	/**
	 * Returns the batch of changes that should be processed.
	 *
	 * Change objects are loaded using a ChangesTable instance.
	 *
	 * @return Change[] the changes to process.
	 */
	public function getChanges() {
		if ( $this->changes === null ) {
			$params = $this->getParams();
			$ids = $params['changeIds'];

			wfDebugLog( __CLASS__, __FUNCTION__ . ": loading " . count( $ids ) . " changes." );

			// load actual change records from the changes table
			// TODO: allow mock store for testing!
			// FIXME: This only works when executed on the client! check WBC_VERSION first!
			$table = ClientStoreFactory::getStore()->newChangesTable();
			$this->changes = $table->selectObjects( null, array( 'id' => $ids ), array(), __METHOD__ );

			wfDebugLog( __CLASS__, __FUNCTION__ . ": loaded " . count( $this->changes ) . " changes." );

			if ( count( $this->changes ) != count( $ids ) ) {
				wfWarn( "Number of changes loaded mismatches the number of change IDs provided." );
			}
		}

		return $this->changes;
	}

	/**
	 * Run the job
	 *
	 * @return boolean success
	 */
	public function run() {
		$changes = $this->getChanges();

		ChangeHandler::singleton()->handleChanges( $changes );
	}

}
