<?php

namespace Wikibase\Test\Repo\Api;

use ApiTestCase;
use Wikibase\Repo\WikibaseRepo;

/**
 * Tests for the AvailableBadges class.
 *
 * @group API
 * @group Database
 * @group Wikibase
 * @group WikibaseAPI
 * @group WikibaseRepo
 * @group medium
 *
 * @licence GNU GPL v2+
 *
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class AvailabeBadgesTest extends ApiTestCase {

	private static $badgeItems = array(
		'Q123' => '',
		'Q999' => ''
	);

	private static $oldBadgeItems;

	protected function setUp() {
		parent::setUp();
		// Allow some badges for testing
		self::$oldBadgeItems = WikibaseRepo::getDefaultInstance()->getSettings()->getSetting( 'badgeItems' );
		WikibaseRepo::getDefaultInstance()->getSettings()->setSetting( 'badgeItems', self::$badgeItems );
	}

	protected function tearDown() {
		parent::tearDown();
		WikibaseRepo::getDefaultInstance()->getSettings()->setSetting( 'badgeItems', self::$oldBadgeItems );
	}

	public function testExecute() {
		list( $result,, ) = $this->doApiRequest( array(
			'action' => 'wbavailablebadges'
		) );

		$this->assertEquals( array_keys( self::$badgeItems ), $result['badges'] );
	}

}
