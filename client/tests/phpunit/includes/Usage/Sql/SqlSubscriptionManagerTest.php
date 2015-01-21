<?php
namespace Wikibase\Client\Tests\Usage\Sql;

use Wikibase\Client\Store\Sql\ConnectionManager;
use Wikibase\Client\Tests\Usage\UsageLookupContractTester;
use Wikibase\Client\Tests\Usage\SubscriptionManagerContractTester;
use Wikibase\Client\Usage\Sql\SqlSubscriptionManager;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;

/**
 * @covers Wikibase\Client\Usage\Sql\SqlSubscriptionManager
 *
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseUsageTracking
 * @group Database
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class SqlSubscriptionManagerTest extends \MediaWikiTestCase {

	/**
	 * @var UsageLookupContractTester
	 */
	private $lookupTester;

	protected function setUp() {
		if ( WikibaseClient::getDefaultInstance()->getSettings()->getSetting( 'useLegacyChangesSubscription' ) ) {
			$this->markTestSkipped( 'Skipping test for SqlSubscriptionManager, because the useLegacyChangesSubscription option is set.' );
		}

		if ( !defined( 'WB_VERSION' ) ) {
			$this->markTestSkipped( 'Skipping test for SqlSubscriptionManager, because the repo-side table wb_changes_subscription is not available.' );
		}

		$this->tablesUsed[] = 'wb_changes_subscription';

		parent::setUp();
	}

	private function getSubscriptionManager() {
		return new SqlSubscriptionManager(
			new ConnectionManager( wfGetLB() )
		);
	}

	public function testSubscribeUnsubscribe() {
		$manager = $this->getSubscriptionManager();

		$q11 = new ItemId( 'Q11' );
		$q22 = new ItemId( 'Q22' );
		$p11 = new PropertyId( 'P11' );

		$manager->subscribe( 'enwiki', array( $q11, $p11 ) );
		$manager->subscribe( 'dewiki', array( $q22 ) );
		$manager->subscribe( 'dewiki', array( $q22, $q11 ) );

		$this->assertEquals(
			array(
				'dewiki@Q11',
				'dewiki@Q22',
				'enwiki@P11',
				'enwiki@Q11',
			),
			$this->fetchAllSubscriptions()
		);

		$manager->unsubscribe( 'enwiki', array( $q11, $q22 ) );
		$manager->unsubscribe( 'dewiki', array( $q22 ) );

		$this->assertEquals(
			array(
				'dewiki@Q11',
				'enwiki@P11',
			),
			$this->fetchAllSubscriptions()
		);
	}

	private function fetchAllSubscriptions() {
		$db = wfGetDB( DB_MASTER );

		$res = $db->select( 'wb_changes_subscription', "*", '', __METHOD__ );

		$subscriptions = array();
		foreach ( $res as $row ) {
			$subscriptions[] = $row->cs_subscriber_id . '@' . $row->cs_entity_id;
		}

		sort( $subscriptions );
		return $subscriptions;
	}

}
