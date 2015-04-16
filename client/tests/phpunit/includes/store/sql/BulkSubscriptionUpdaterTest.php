<?php

namespace Wikibase\Client\Tests\Store\Sql;

use PHPUnit_Framework_MockObject_Matcher_Invocation;
use Wikibase\Client\Store\Sql\ConsistentReadConnectionManager;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\Lib\Reporting\ExceptionHandler;
use Wikibase\Lib\Reporting\MessageReporter;
use Wikibase\Client\Store\Sql\BulkSubscriptionUpdater;

/**
 * @covers Wikibase\Client\Store\Sql\BulkSubscriptionUpdater
 *
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseUsageTracking
 * @group Database
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class BulkSubscriptionUpdaterTest extends \MediaWikiTestCase {

	public function setUp() {
		if ( WikibaseClient::getDefaultInstance()->getSettings()->getSetting( 'useLegacyChangesSubscription' ) ) {
			$this->markTestSkipped( 'Skipping test for BulkSubscriptionUpdater, '
				. 'because the useLegacyChangesSubscription option is set.' );
		}

		if ( !defined( 'WB_VERSION' ) ) {
			$this->markTestSkipped( "Skipping because WikibaseClient doesn't have a local wb_changes_subscription table." );
		}

		$this->tablesUsed[] = 'wb_changes_subscription';
		$this->tablesUsed[] = 'wbc_entity_usage';

		parent::setUp();
	}

	/**
	 * @param int $batchSize
	 *
	 * @return BulkSubscriptionUpdater
	 */
	private function getBulkSubscriptionUpdater( $batchSize = 10 ) {
		$loadBalancer = wfGetLB();

		return new BulkSubscriptionUpdater(
			new ConsistentReadConnectionManager( $loadBalancer, false ),
			new ConsistentReadConnectionManager( $loadBalancer, false ),
			'testwiki',
			$batchSize
		);
	}

	public function testPurgeSubscriptions() {
		$this->truncateEntityUsage();
		$this->putSubscriptions( array(
			array( 'P11', 'dewiki' ),
			array( 'Q11', 'dewiki' ),
			array( 'Q22', 'dewiki' ),
			array( 'Q22', 'frwiki' ),
			array( 'P11', 'testwiki' ),
			array( 'Q11', 'testwiki' ),
			array( 'Q22', 'testwiki' ),
		) );

		$updater = $this->getBulkSubscriptionUpdater( 2 );
		$updater->setProgressReporter( $this->getMessageReporter( $this->exactly( 2 ) ) );
		$updater->setExceptionHandler( $this->getExceptionHandler( $this->exactly( 0 ) ) );

		$updater->purgeSubscriptions();

		$actual = $this->fetchAllSubscriptions();
		sort( $actual );

		$expected = array(
			'dewiki@P11',
			'dewiki@Q11',
			'dewiki@Q22',
			'frwiki@Q22',
		);

		$this->assertEquals( $expected, $actual );
	}

	public function testPurgeSubscriptions_startItem() {
		$this->truncateEntityUsage();
		$this->putSubscriptions( array(
			array( 'P11', 'dewiki' ),
			array( 'Q11', 'dewiki' ),
			array( 'Q22', 'dewiki' ),
			array( 'Q22', 'frwiki' ),
			array( 'P11', 'testwiki' ),
			array( 'Q11', 'testwiki' ),
			array( 'Q22', 'testwiki' ),
		) );

		$updater = $this->getBulkSubscriptionUpdater( 2 );
		$updater->setProgressReporter( $this->getMessageReporter( $this->exactly( 1 ) ) );
		$updater->setExceptionHandler( $this->getExceptionHandler( $this->exactly( 0 ) ) );

		$updater->purgeSubscriptions( new ItemId( 'Q20' ) );

		$actual = $this->fetchAllSubscriptions();
		sort( $actual );

		$expected = array(
			'dewiki@P11',
			'dewiki@Q11',
			'dewiki@Q22',
			'frwiki@Q22',
			'testwiki@P11',
			'testwiki@Q11',
		);

		$this->assertEquals( $expected, $actual );
	}

	public function testUpdateSubscriptions() {
		$this->truncateEntityUsage();
		$this->putSubscriptions( array(
			array( 'P11', 'dewiki' ),
			array( 'Q11', 'dewiki' ),
			array( 'Q22', 'dewiki' ),
			array( 'Q22', 'frwiki' ),
		) );
		$this->putEntityUsage( array(
			array( 'P11', 11 ),
			array( 'Q11', 11 ),
			array( 'Q22', 22 ),
			array( 'Q22', 33 ),
		) );

		$updater = $this->getBulkSubscriptionUpdater( 2 );
		$updater->setProgressReporter( $this->getMessageReporter( $this->exactly( 2 ) ) );
		$updater->setExceptionHandler( $this->getExceptionHandler( $this->exactly( 0 ) ) );

		$updater->updateSubscriptions();

		$actual = $this->fetchAllSubscriptions();
		sort( $actual );

		$expected = array(
			'dewiki@P11',
			'dewiki@Q11',
			'dewiki@Q22',
			'frwiki@Q22',
			'testwiki@P11',
			'testwiki@Q11',
			'testwiki@Q22',
		);

		$this->assertEquals( $expected, $actual );
	}

	public function testUpdateSubscriptions_startItem() {
		$this->truncateEntityUsage();
		$this->putSubscriptions( array(
			array( 'P11', 'dewiki' ),
			array( 'Q11', 'dewiki' ),
			array( 'Q22', 'dewiki' ),
			array( 'Q22', 'frwiki' ),
		) );
		$this->putEntityUsage( array(
			array( 'P11', 11 ),
			array( 'Q11', 11 ),
			array( 'Q22', 22 ),
			array( 'Q22', 33 ),
		) );

		$updater = $this->getBulkSubscriptionUpdater( 2 );
		$updater->setProgressReporter( $this->getMessageReporter( $this->exactly( 1 ) ) );
		$updater->setExceptionHandler( $this->getExceptionHandler( $this->exactly( 0 ) ) );

		$updater->updateSubscriptions( new ItemId( 'Q20' ) );

		$actual = $this->fetchAllSubscriptions();
		sort( $actual );

		$expected = array(
			'dewiki@P11',
			'dewiki@Q11',
			'dewiki@Q22',
			'frwiki@Q22',
			'testwiki@Q22',
		);

		$this->assertEquals( $expected, $actual );
	}

	private function truncateEntityUsage() {
		$db = wfGetDB( DB_MASTER );
		$db->delete( 'wbc_entity_usage', '*' );
	}

	private static $entityTypeMap = array(
		'Q' => Item::ENTITY_TYPE,
		'P' => Property::ENTITY_TYPE,
	);

	private function putEntityUsage( array $entries ) {
		$db = wfGetDB( DB_MASTER );

		$db->startAtomic( __METHOD__ );

		foreach ( $entries as $entry ) {
			list( $entityId, $pageId ) = $entry;
			$aspect = 'X';

			$db->insert( 'wbc_entity_usage', array(
				'eu_entity_id' => $entityId,
				'eu_aspect' => $aspect,
				'eu_page_id' => (int)$pageId,
			), __METHOD__ );
		}

		$db->endAtomic( __METHOD__ );
	}

	private function putSubscriptions( array $entries ) {
		$db = wfGetDB( DB_MASTER );

		$db->startAtomic( __METHOD__ );

		foreach ( $entries as $entry ) {
			list( $entityId, $subscriberId ) = $entry;

			$db->insert( 'wb_changes_subscription', array(
				'cs_entity_id' => $entityId,
				'cs_subscriber_id' => $subscriberId,
			), __METHOD__ );
		}

		$db->endAtomic( __METHOD__ );
	}

	private function fetchAllSubscriptions() {
		$db = wfGetDB( DB_MASTER );

		$res = $db->select( 'wb_changes_subscription', "*", '', __METHOD__ );

		$subscriptions = array();
		foreach ( $res as $row ) {
			$subscriptions[] = $row->cs_subscriber_id . '@' . $row->cs_entity_id;
		}

		return $subscriptions;
	}

	/**
	 * @param PHPUnit_Framework_MockObject_Matcher_Invocation $matcher
	 *
	 * @return ExceptionHandler
	 */
	private function getExceptionHandler( PHPUnit_Framework_MockObject_Matcher_Invocation $matcher ) {
		$mock = $this->getMock( 'Wikibase\Lib\Reporting\ExceptionHandler' );
		$mock->expects( $matcher )
			->method( 'handleException' );

		return $mock;
	}

	/**
	 * @param PHPUnit_Framework_MockObject_Matcher_Invocation $matcher
	 *
	 * @return MessageReporter
	 */
	private function getMessageReporter( PHPUnit_Framework_MockObject_Matcher_Invocation $matcher ) {
		$mock = $this->getMock( 'Wikibase\Lib\Reporting\MessageReporter' );
		$mock->expects( $matcher )
			->method( 'reportMessage' );

		return $mock;
	}

}
