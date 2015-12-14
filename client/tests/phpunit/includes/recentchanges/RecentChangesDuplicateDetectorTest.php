<?php

namespace Wikibase\Client\Tests\RecentChanges;

use RecentChange;
use Wikibase\Client\RecentChanges\RecentChangeFactory;
use Wikibase\Client\RecentChanges\RecentChangesDuplicateDetector;
use Wikibase\Client\Store\Sql\ConsistentReadConnectionManager;

/**
 * @covers Wikibase\Client\RecentChanges\RecentChangesDuplicateDetector
 *
 * @group WikibaseClient
 * @group Wikibase
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class RecentChangesDuplicateDetectorTest extends \MediaWikiTestCase {

	protected function setUp() {
		parent::setUp();

		$this->tablesUsed[] = 'recentchanges';
	}

	public function provideChangeExists() {
		return array(
			'same' => array( true, array(
				'rc_id' => 17,
				'rc_timestamp' => '20111111111111',
				'rc_user' => 23,
				'rc_user_text' => 'Test',
				'rc_namespace' => 0,
				'rc_title' => 'Test',
				'rc_comment' => 'Testing',
				'rc_type' => RC_EXTERNAL,
				'rc_source' => RecentChangeFactory::SRC_WIKIBASE,
				'rc_last_oldid' => 11,
				'rc_this_oldid' => 12,
				'rc_params' => array(
					'wikibase-repo-change' => array(
						'parent_id' => 1,
						'rev_id' => 2,
					)
				),
			) ),
			'irrelevant differences' => array( true, array(
				'rc_id' => 1117, // ignored
				'rc_timestamp' => '20111111111111',
				'rc_user' => 23,
				'rc_user_text' => 'Test',
				'rc_namespace' => 0,
				'rc_title' => 'Test',
				'rc_comment' => 'Kittens', // ignored
				'rc_type' => RC_EXTERNAL,
				'rc_source' => RecentChangeFactory::SRC_WIKIBASE,
				'rc_last_oldid' => 1111, // ignored
				'rc_this_oldid' => 1112, // ignored
				'rc_params' => array(
					'wikibase-repo-change' => array(
						'parent_id' => 1,
						'rev_id' => 2,
					)
				),
			) ),
			'repo change mismatch' => array( false, array(
				'rc_id' => 17,
				'rc_timestamp' => '20111111111111',
				'rc_user' => 23,
				'rc_user_text' => 'Test',
				'rc_namespace' => 0,
				'rc_title' => 'Test',
				'rc_comment' => 'Testing',
				'rc_type' => RC_EXTERNAL,
				'rc_source' => RecentChangeFactory::SRC_WIKIBASE,
				'rc_last_oldid' => 11,
				'rc_this_oldid' => 12,
				'rc_params' => array(
					'wikibase-repo-change' => array(
						'parent_id' => 7,
						'rev_id' => 8,
					)
				),
			) ),
			'local timestamp mismatch' => array( false, array(
				'rc_id' => 17,
				'rc_timestamp' => '20111111112233',
				'rc_user' => 23,
				'rc_user_text' => 'Test',
				'rc_namespace' => 0,
				'rc_title' => 'Test',
				'rc_comment' => 'Testing',
				'rc_type' => RC_EXTERNAL,
				'rc_source' => RecentChangeFactory::SRC_WIKIBASE,
				'rc_last_oldid' => 11,
				'rc_this_oldid' => 12,
				'rc_params' => array(
					'wikibase-repo-change' => array(
						'parent_id' => 1,
						'rev_id' => 2,
					)
				),
			) ),
			'local title mismatch' => array( false, array(
				'rc_id' => 17,
				'rc_timestamp' => '20111111111111',
				'rc_user' => 23,
				'rc_user_text' => 'Test',
				'rc_namespace' => 0,
				'rc_title' => 'Kittens',
				'rc_comment' => 'Testing',
				'rc_type' => RC_EXTERNAL,
				'rc_source' => RecentChangeFactory::SRC_WIKIBASE,
				'rc_last_oldid' => 11,
				'rc_this_oldid' => 12,
				'rc_params' => array(
					'wikibase-repo-change' => array(
						'parent_id' => 1,
						'rev_id' => 2,
					)
				),
			) ),
		);
	}

	/**
	 * @dataProvider provideChangeExists
	 */
	public function testChangeExists( $expected, array $changeData ) {
		$connectionManager = new ConsistentReadConnectionManager( wfGetLB() );
		$detector = new RecentChangesDuplicateDetector( $connectionManager );

		$this->initRecentChanges();

		$change = $this->newChange( $changeData );

		$this->assertEquals( $expected, $detector->changeExists( $change ), 'changeExists()' );
	}

	private function newChange( array $changeData ) {
		if ( isset( $changeData['rc_params'] ) && !is_string( $changeData['rc_params'] ) ) {
			$changeData['rc_params'] = serialize( $changeData['rc_params'] );
		}

		$defaults = array(
			'rc_id' => 0,
			'rc_timestamp' => '20000000000000',
			'rc_user' => 0,
			'rc_user_text' => '',
			'rc_namespace' => 0,
			'rc_title' => '',
			'rc_comment' => '',
			'rc_minor' => false,
			'rc_bot' => false,
			'rc_new' => false,
			'rc_cur_id' => 0,
			'rc_this_oldid' => 0,
			'rc_last_oldid' => 0,
			'rc_type' => RC_EXTERNAL,
			'rc_source' => RecentChangeFactory::SRC_WIKIBASE,
			'rc_patrolled' => false,
			'rc_ip' => '127.0.0.1',
			'rc_old_len' => 0,
			'rc_new_len' => 0,
			'rc_deleted' => false,
			'rc_logid' => 0,
			'rc_log_type' => null,
			'rc_log_action' => '',
			'rc_params' => '',
		);

		$changeData = array_merge( $defaults, $changeData );

		$change = RecentChange::newFromRow( (object)$changeData );
		$change->setExtra( array(
			'pageStatus' => 'changed'
		) );

		return $change;
	}

	private function initRecentChanges() {
		$change = $this->newChange( array(
			'rc_id' => 17,
			'rc_timestamp' => '20111111111111',
			'rc_user' => 23,
			'rc_user_text' => 'Test',
			'rc_namespace' => 0,
			'rc_title' => 'Test',
			'rc_comment' => 'Testing',
			'rc_type' => RC_EXTERNAL,
			'rc_source' => RecentChangeFactory::SRC_WIKIBASE,
			'rc_last_oldid' => 11,
			'rc_this_oldid' => 12,
			'rc_params' => array(
				'wikibase-repo-change' => array(
					'parent_id' => 1001, // different id
					'rev_id' => 1002, // different id
				)
			)
		) );
		$change->save();

		$change = $this->newChange( array(
			'rc_id' => 18,
			'rc_timestamp' => '20111111111111',
			'rc_user' => 23,
			'rc_user_text' => 'Test',
			'rc_namespace' => 0,
			'rc_title' => 'Test',
			'rc_comment' => 'Testing',
			'rc_type' => RC_EXTERNAL,
			'rc_source' => RecentChangeFactory::SRC_WIKIBASE,
			'rc_last_oldid' => 11,
			'rc_this_oldid' => 12,
			'rc_params' => array(
				'wikibase-repo-change' => array(
					'parent_id' => 1,
					'rev_id' => 2,
				)
			)
		) );

		$change->save();
	}

}
