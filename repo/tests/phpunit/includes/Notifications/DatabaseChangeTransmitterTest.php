<?php

namespace Wikibase\Tests\Repo;

use RecentChange;
use Diff\DiffOp\Diff\Diff;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\EntityChange;
use Wikibase\Repo\Notifications\DatabaseChangeTransmitter;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers Wikibase\Repo\Notifications\DatabaseChangeTransmitter
 *
 * @group Database
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseChange
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 * @author Marius Hoch
 */
class DatabaseChangeTransmitterTest extends \MediaWikiTestCase {

	public function transmitChangeProvider() {
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$factory = $wikibaseRepo->getEntityChangeFactory();

		$time = wfTimestamp( TS_MW );

		$simpleChange = $factory->newForEntity( EntityChange::ADD, new ItemId( 'Q21389475' ) );

		$changeWithDiff = $factory->newForEntity( EntityChange::REMOVE, new ItemId( 'Q42' ) );
		$changeWithDiff->setField( 'time', $time );
		$changeWithDiff->setDiff( new Diff() );

		$rc = new RecentChange();
		$rc->setAttribs( array(
			'rc_this_oldid' => 12,
			'rc_user' => 34,
			'rc_user_text' => 'BlackMagicIsEvil',
			'rc_timestamp' => $time,
			'rc_bot' => 0,
			'rc_cur_id' => 2354,
			'rc_this_oldid' => 343,
			'rc_last_oldid' => 897,
			'rc_comment' => 'Fake data!'
		) );

		$changeWithDataFromRC = $factory->newForEntity( EntityChange::REMOVE, new ItemId( 'Q123' ) );
		$changeWithDataFromRC->setMetadataFromRC( $rc );

		return array(
			'Simple change' => array(
				array(
					'change_type' => 'wikibase-item~add',
					'change_time' => $time,
					'change_object_id' => 'q21389475',
					'change_revision_id' => '0',
					'change_user_id' => '0',
					'change_info' => '[]',
				),
				$simpleChange
			),
			'Change with a diff' => array(
				array(
					'change_type' => 'wikibase-item~remove',
					'change_time' => $time,
					'change_object_id' => 'q42',
					'change_revision_id' => '0',
					'change_user_id' => '0',
					'change_info' => '{"diff":{"type":"diff","isassoc":null,"operations":[]}}',
				),
				$changeWithDiff
			),
			'Change with data from RC' => array(
				array(
					'change_type' => 'wikibase-item~remove',
					'change_time' => $time,
					'change_object_id' => 'q123',
					'change_revision_id' => '343',
					'change_user_id' => '34',
					'change_info' => '{"metadata":{"user_text":"BlackMagicIsEvil","bot":0,"page_id":2354,"rev_id":343,' .
						'"parent_id":897,"comment":"Fake data!"}}',
				),
				$changeWithDataFromRC
			)
		);
	}

	/**
	 * @dataProvider transmitChangeProvider
	 */
	public function testTransmitChange( array $expected, EntityChange $change ) {
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();

		$db = wfGetDB( DB_MASTER );
		$tableName = $wikibaseRepo->getStore()->getChangesTable()->getName();

		$db->delete( $tableName, '*', __METHOD__ );
		$this->tablesUsed[] = $tableName;

		$channel = new DatabaseChangeTransmitter( wfGetLB() );
		$channel->transmitChange( $change );

		$res = $db->select( $tableName, '*', array(), __METHOD__ );

		$this->assertEquals( 1, $res->numRows(), 'row count' );

		$row = (array)$res->current();
		$this->assertTrue( is_numeric( $row['change_id'] ) );

		$this->assertEquals(
			wfTimestamp( TS_UNIX, $expected['change_time'] ),
			wfTimestamp( TS_UNIX, $row['change_time'] ),
			'Change time',
			20 // 20s tolerance
		);

		unset( $row['change_id'] );
		unset( $row['change_time'] );
		unset( $expected['change_time'] );

		$this->assertEquals( $expected, $row );

		$this->assertType( 'int', $change->getId() );
	}

}
