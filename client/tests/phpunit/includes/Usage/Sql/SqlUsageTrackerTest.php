<?php

namespace Wikibase\Client\Tests\Usage\Sql;

use MediaWikiTestCase;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikimedia\Rdbms\SessionConsistentConnectionManager;
use Wikibase\Client\Tests\Usage\UsageLookupContractTester;
use Wikibase\Client\Tests\Usage\UsageTrackerContractTester;
use Wikibase\Client\Usage\EntityUsage;
use Wikibase\Client\Usage\Sql\EntityUsageTable;
use Wikibase\Client\Usage\Sql\SqlUsageTracker;

/**
 * @covers Wikibase\Client\Usage\Sql\SqlUsageTracker
 *
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseUsageTracking
 * @group Database
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class SqlUsageTrackerTest extends MediaWikiTestCase {

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

	protected function setUp() {
		$this->tablesUsed[] = EntityUsageTable::DEFAULT_TABLE_NAME;
		$this->tablesUsed[] = 'page';

		parent::setUp();

		$this->sqlUsageTracker = new SqlUsageTracker(
			new ItemIdParser(),
			new SessionConsistentConnectionManager( wfGetLB() ),
			[]
		);

		$this->trackerTester = new UsageTrackerContractTester( $this->sqlUsageTracker, array( $this, 'getUsages' ) );
		$this->lookupTester = new UsageLookupContractTester( $this->sqlUsageTracker, array( $this, 'putUsages' ) );
	}

	public function getUsages( $pageId ) {
		$db = wfGetDB( DB_REPLICA );
		$updater = new EntityUsageTable( new ItemIdParser(), $db );
		return $updater->queryUsages( $pageId );
	}

	public function putUsages( $pageId, array $usages ) {
		$db = wfGetDB( DB_MASTER );
		$updater = new EntityUsageTable( new ItemIdParser(), $db );
		return $updater->addUsages( $pageId, $usages );
	}

	public function testAddUsedEntities() {
		$this->trackerTester->testAddUsedEntities();
	}

	public function testAddUsedEntitiesBlacklist() {
		$q3 = new ItemId( 'Q3' );
		$q4 = new ItemId( 'Q4' );

		$usages = [
			new EntityUsage( $q3, EntityUsage::SITELINK_USAGE ),
			new EntityUsage( $q3, EntityUsage::STATEMENT_USAGE, 'P12' ),
			new EntityUsage( $q4, EntityUsage::LABEL_USAGE, 'de' ),
		];

		$sqlUsageTracker = new SqlUsageTracker(
			new ItemIdParser(),
			new SessionConsistentConnectionManager( wfGetLB() ),
			[ EntityUsage::STATEMENT_USAGE ]
		);
		$sqlUsageTracker->addUsedEntities( 23, $usages );

		// All entries but the blacklisted should be set
		$this->assertEquals(
			[ 'Q3#S', 'Q4#L.de' ],
			array_keys( $this->getUsages( 23 ) )
		);
	}

	public function testReplaceUsedEntities() {
		$this->trackerTester->testReplaceUsedEntities();
	}

	public function testReplaceUsedEntitiesBlacklist() {
		$q3 = new ItemId( 'Q3' );
		$q4 = new ItemId( 'Q4' );

		$usages = [
			new EntityUsage( $q3, EntityUsage::SITELINK_USAGE ),
			new EntityUsage( $q3, EntityUsage::STATEMENT_USAGE, 'P12' ),
			new EntityUsage( $q4, EntityUsage::LABEL_USAGE, 'de' ),
		];

		$sqlUsageTracker = new SqlUsageTracker(
			new ItemIdParser(),
			new SessionConsistentConnectionManager( wfGetLB() ),
			[]
		);
		// Make sure the blacklisted entries are actually removed.
		$sqlUsageTracker->addUsedEntities( 23, $usages );

		$sqlUsageTrackerWithBlacklist = new SqlUsageTracker(
			new ItemIdParser(),
			new SessionConsistentConnectionManager( wfGetLB() ),
			[ EntityUsage::STATEMENT_USAGE ]
		);
		$sqlUsageTrackerWithBlacklist->replaceUsedEntities( 23, $usages );

		// All entries but the blacklisted should be set
		$this->assertEquals(
			[ 'Q3#S', 'Q4#L.de' ],
			array_keys( $this->getUsages( 23 ) )
		);
	}

	public function testPruneUsages() {
		$this->trackerTester->testPruneUsages();
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
