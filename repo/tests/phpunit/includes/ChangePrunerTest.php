<?php

namespace Wikibase\Test;

use MediaWikiTestCase;
use Wikibase\ChangesTable;
use Wikibase\Lib\Reporting\MessageReporter;
use Wikibase\Lib\Reporting\ObservableMessageReporter;
use Wikibase\Repo\ChangePruner;

/**
 * @covers Wikibase\Repo\ChangePruner
 *
 * @group Database
 * @group Wikibase
 * @group WikibaseRepo
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class ChangePrunerTest extends MediaWikiTestCase {

	public $messages = array();

	public function testDoPrune() {
		$pruner = new ChangePruner( 1, 1, 1, false );

		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete( 'wb_changes', '*' );

		$this->assertEquals( 0, $dbw->selectRowCount( 'wb_changes' ),
			'sanity check: wb_changes table is empty' );

		$this->addTestChanges();
		$this->assertEquals( 2, $dbw->selectRowCount( 'wb_changes' ),
			'sanity check: 2 changes added to wb_changes'
		);

		$pruner->setMessageReporter( $this->newMessageReporter() );
		$pruner->doPrune();

		$this->assertEquals( 6, count( $this->messages ), 'pruner has reported 6 messages' );

		$this->assertContains( 'pruning entries older than 2015-01-01T00:03:00Z', $this->messages[0] );
		$this->assertContains( '1 rows pruned', $this->messages[1] );
		$this->assertContains( '1 rows pruned', $this->messages[3] );
		$this->assertContains( '0 rows pruned', $this->messages[5] );

		$this->assertEquals( 0, $dbw->selectRowCount( 'wb_changes' ), 'wb_changes table is empty' );
	}

	private function addTestChanges() {
		$changesTable = new ChangesTable( false );

		$change = $changesTable->newRow( $this->getChangeRowData( '20150101000005' ), true );
		$change->save();

		$change = $changesTable->newRow( $this->getChangeRowData( '20150101000300' ), true );
		$change->save();
	}

	private function getChangeRowData( $timestamp ) {
		return array(
			'type' => 'wikibase-item~update',
			'time' => $timestamp,
			'user_id' => 0,
			'revision_id' => 9002,
			'object_id' => 'Q9000',
			'info' => array( 'diff' => array() )
		);
	}

	/**
	 * @return MessageReporter
	 */
	private function newMessageReporter() {
		$reporter = new ObservableMessageReporter();

		$self = $this; // evil PHP 5.3 ;)
		$reporter->registerReporterCallback(
			function ( $message ) use ( $self ) {
				$self->messages[] = $message;
			}
		);

		return $reporter;
	}

}
