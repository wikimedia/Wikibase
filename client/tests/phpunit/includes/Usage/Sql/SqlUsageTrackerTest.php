<?php
namespace Wikibase\Client\Tests\Usage\Sql;

use PHPUnit_Framework_Assert as Assert;
use Wikibase\Client\Store\Sql\ConnectionManager;
use Wikibase\Client\Tests\Usage\UsageLookupContractTester;
use Wikibase\Client\Tests\Usage\UsageTrackerContractTester;
use Wikibase\Client\Usage\Sql\SqlUsageTracker;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\BasicEntityIdParser;

/**
 * @covers Wikibase\Client\Usage\Sql\SqlUsageTracker
 *
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseUsageTracking
 * @group Database
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class SqlUsageTrackerTest extends \MediaWikiTestCase {

	/**
	 * @var SqlUsageTracker
	 */
	private $sqlUsageTracker;

	/**
	 * @var UsageTrackerContractTester
	 */
	private $trackerTester;

	/**
	 * @var UsageLookupContractTester
	 */
	private $lookupTester;

	public function setUp() {
		if ( WikibaseClient::getDefaultInstance()->getSettings()->getSetting( 'useLegacyUsageIndex' ) ) {
			$this->markTestSkipped( 'Skipping test for SqlUsageTracker, because the useLegacyUsageIndex option is set.' );
		}

		$this->tablesUsed[] = 'wbc_entity_usage';

		parent::setUp();

		$this->sqlUsageTracker = new SqlUsageTracker(
			new BasicEntityIdParser(),
			new ConnectionManager( wfGetLB() )
		);

		$this->trackerTester = new UsageTrackerContractTester( $this->sqlUsageTracker );
		$this->lookupTester = new UsageLookupContractTester( $this->sqlUsageTracker, $this->sqlUsageTracker );
	}

	public function testTrackUsedEntities() {
		$this->trackerTester->testTrackUsedEntities();
	}

	public function testRemoveEntities() {
		$this->trackerTester->testRemoveEntities();
	}

	public function testGetUsageForPage() {
		$this->lookupTester->testGetUsageForPage();
	}

	public function testGetPagesUsing() {
		$this->lookupTester->testGetPagesUsing();
	}

	public function testGetUnusedEntities() {
		$this->lookupTester->testGetUnusedEntities();
	}

}
