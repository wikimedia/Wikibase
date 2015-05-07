<?php

namespace Wikibase\Client\Usage\Tests;

use DataValues\StringValue;
use PHPUnit_Framework_Assert as Assert;
use Wikibase\Client\Usage\EntityUsage;
use Wikibase\Client\Usage\UsageAccumulator;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;

/**
 * Contract tester for implementations of the UsageAccumulator interface.
 *
 * @covers Wikibase\Client\Usage\UsageAccumulator
 * @uses Wikibase\Client\Usage\EntityUsage
 *
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseUsageTracking
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class UsageAccumulatorContractTester  {

	/**
	 * @var UsageAccumulator
	 */
	private $usageAccumulator;

	public function __construct( UsageAccumulator $usageAccumulator ) {
		$this->usageAccumulator = $usageAccumulator;
	}

	public function testAddGetUsage() {
		$this->testAddAndGetLabelUsageForSnaks();
		$this->testAddAndGetLabelUsage();
		$this->testAddAndGetTitleUsage();
		$this->testAddAndGetSiteLinksUsage();
		$this->testAddAndGetOtherUsage();
		$this->testAddAndGetAllUsage();

		$q2 = new ItemId( 'Q2' );
		$q3 = new ItemId( 'Q3' );
		$q4 = new ItemId( 'Q4' );
		$expected = array(
			new EntityUsage( $q4, EntityUsage::LABEL_USAGE, 'xx' ),
			new EntityUsage( $q2, EntityUsage::LABEL_USAGE, 'xx' ),
			new EntityUsage( $q2, EntityUsage::TITLE_USAGE ),
			new EntityUsage( $q2, EntityUsage::SITELINK_USAGE ),
			new EntityUsage( $q2, EntityUsage::OTHER_USAGE ),
			new EntityUsage( $q3, EntityUsage::ALL_USAGE ),
		);

		$usages = $this->usageAccumulator->getUsages();
		$this->assertSameUsages( $expected, $usages );
	}

	private function testAddAndGetLabelUsageForSnaks() {
		$q4 = new ItemId( 'Q4' );
		$this->usageAccumulator->addLabelUsageForSnaks( array(
			new PropertyValueSnak( new PropertyId( 'P4' ), new EntityIdValue( $q4 ) ),
			new PropertyValueSnak( new PropertyId( 'P5' ), new StringValue( 'string' ) ),
		), 'xx' );

		$expected = new EntityUsage( $q4, EntityUsage::LABEL_USAGE, 'xx' );

		$usages = $this->usageAccumulator->getUsages();
		$this->assertContainsUsage( $expected, $usages );
	}

	private function testAddAndGetLabelUsage() {
		$q2 = new ItemId( 'Q2' );
		$this->usageAccumulator->addLabelUsage( $q2, 'xx' );

		$expected = new EntityUsage( $q2, EntityUsage::LABEL_USAGE, 'xx' );

		$usages = $this->usageAccumulator->getUsages();
		$this->assertContainsUsage( $expected, $usages );
	}

	private function testAddAndGetTitleUsage() {
		$q2 = new ItemId( 'Q2' );
		$this->usageAccumulator->addTitleUsage( $q2 );

		$expected = new EntityUsage( $q2, EntityUsage::TITLE_USAGE );

		$entityUsages = $this->usageAccumulator->getUsages();
		$this->assertContainsUsage( $expected, $entityUsages );
	}

	private function testAddAndGetSiteLinksUsage() {
		$q2 = new ItemId( 'Q2' );
		$this->usageAccumulator->addSiteLinksUsage( $q2 );

		$expected = new EntityUsage( $q2, EntityUsage::SITELINK_USAGE );

		$entityUsages = $this->usageAccumulator->getUsages();
		$this->assertContainsUsage( $expected, $entityUsages );
	}

	private function testAddAndGetOtherUsage() {
		$q2 = new ItemId( 'Q2' );
		$this->usageAccumulator->addOtherUsage( $q2 );

		$expected = new EntityUsage( $q2, EntityUsage::OTHER_USAGE );

		$usages = $this->usageAccumulator->getUsages();
		$this->assertContainsUsage( $expected, $usages );
	}

	private function testAddAndGetAllUsage() {
		$q3 = new ItemId( 'Q3' );
		$this->usageAccumulator->addAllUsage( $q3 );

		$expected = new EntityUsage( $q3, EntityUsage::ALL_USAGE );

		$usages = $this->usageAccumulator->getUsages();
		$this->assertContainsUsage( $expected, $usages );
	}

	/**
	 * @param EntityUsage $expected
	 * @param EntityUsage[] $actual
	 * @param string $message
	 */
	private function assertContainsUsage( EntityUsage $expected, array $actual, $message = '' ) {
		$expected = $expected->getIdentityString();
		$actual = $this->getIdentityStrings( $actual );

		Assert::assertContains( $expected, $actual, $message );
	}

	/**
	 * @param EntityUsage[] $expected
	 * @param EntityUsage[] $actual
	 * @param string $message
	 */
	private function assertSameUsages( array $expected, array $actual, $message = '' ) {
		$expected = $this->getIdentityStrings( $expected );
		$actual = $this->getIdentityStrings( $actual );

		Assert::assertEquals( $expected, $actual, $message );
	}

	/**
	 * @param EntityUsage[] $usages
	 *
	 * @return string[]
	 */
	private function getIdentityStrings( array $usages ) {
		return array_values(
			array_map( function( EntityUsage $usage ) {
				return $usage->getIdentityString();
			}, $usages )
		);
	}

}
