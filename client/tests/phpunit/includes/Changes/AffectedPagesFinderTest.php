<?php

namespace Wikibase\Client\Tests\Changes;

use ArrayIterator;
use Title;
use Wikibase\Client\Changes\AffectedPagesFinder;
use Wikibase\Client\Store\TitleFactory;
use Wikibase\Client\Usage\EntityUsage;
use Wikibase\Client\Usage\PageEntityUsages;
use Wikibase\Client\Usage\SiteLinkUsageLookup;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\ItemChange;
use Wikibase\Lib\Store\StorageException;
use Wikibase\Test\TestChanges;

/**
 * @covers Wikibase\Client\Changes\AffectedPagesFinder
 *
 * @group Database
 * @group WikibaseClient
 * @group Wikibase
 * @group AffectedPagesFinder
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Daniel Kinzler
 */
class AffectedPagesFinderTest extends \MediaWikiTestCase {

	/**
	 * Returns a TitleFactory that generates Title objects based on the assumption
	 * that a page's title is the same as the page's article ID (in decimal notation).
	 *
	 * @return TitleFactory
	 */
	private function getTitleFactory() {
		$titleFactory = $this->getMock( 'Wikibase\Client\Store\TitleFactory' );

		$titleFactory->expects( $this->any() )
			->method( 'newFromID' )
			->will( $this->returnCallback( function( $id ) {
				$title = Title::makeTitle( NS_MAIN, "$id" );
				$title->resetArticleID( $id );
				return $title;
			} ) );

		$titleFactory->expects( $this->any() )
			->method( 'newFromText' )
			->will( $this->returnCallback( function( $text, $defaultNs = NS_MAIN ) {
				$title = Title::newFromText( $text, $defaultNs );

				if ( !$title ) {
					throw new StorageException( 'Bad title text: ' . $text );
				}

				$title->resetArticleID( $text );
				return $title;
			} ) );

		return $titleFactory;
	}

	private function getAffectedPagesFinder( array $usage ) {
		$usageLookup = $this->getMock( 'Wikibase\Client\Usage\UsageLookup' );

		$usageLookup->expects( $this->any() )
			->method( 'getPagesUsing' )
			->will( $this->returnValue( new ArrayIterator( $usage ) ) );

		$affectedPagesFinder = new AffectedPagesFinder(
			$usageLookup,
			$this->getNamespaceChecker(),
			$this->getTitleFactory(),
			'enwiki',
			'en',
			false
		);

		return $affectedPagesFinder;
	}

	private function getNamespaceChecker() {
		$namespaceChecker = $this->getMockBuilder( 'Wikibase\NamespaceChecker' )
			->disableOriginalConstructor()->getMock();

		$namespaceChecker->expects( $this->any() )
			->method( 'isWikibaseEnabled' )
			->will( $this->returnValue( true ) );

		return $namespaceChecker;
	}

	public function getChangedAspectsProvider() {
		$changeFactory = TestChanges::getEntityChangeFactory();
		$cases = array();

		$q1 = new ItemId( 'Q1' );
		$q2 = new ItemId( 'Q2' );

		$cases['create linked item Q1'] = array(
			array( EntityUsage::SITELINK_USAGE, EntityUsage::TITLE_USAGE ),
			$changeFactory->newFromUpdate(
				ItemChange::ADD,
				null,
				$this->getItemWithSiteLinks( $q1, array( 'enwiki' => '1' ) )
			)
		);

		$cases['unlink item Q1'] = array(
			array( EntityUsage::SITELINK_USAGE, EntityUsage::TITLE_USAGE ),
			$changeFactory->newFromUpdate(
				ItemChange::UPDATE,
				$this->getItemWithSiteLinks( $q1, array( 'enwiki' => '1' ) ),
				new Item( $q1 )
			)
		);

		$cases['link item Q2'] = array(
			array( EntityUsage::SITELINK_USAGE, EntityUsage::TITLE_USAGE ),
			$changeFactory->newFromUpdate(
				ItemChange::UPDATE,
				new Item( $q2 ),
				$this->getItemWithSiteLinks( $q2, array( 'enwiki' => '2' ) )
			)
		);

		$cases['change link of Q1'] = array(
			array( EntityUsage::SITELINK_USAGE, EntityUsage::TITLE_USAGE ),
			$changeFactory->newFromUpdate(
				ItemChange::UPDATE,
				$this->getItemWithSiteLinks( $q1, array( 'enwiki' => '1' ) ),
				$this->getItemWithSiteLinks( $q1, array( 'enwiki' => '2' ) )
			)
		);

		$cases['delete linked item Q2'] = array(
			array( EntityUsage::SITELINK_USAGE, EntityUsage::TITLE_USAGE ),
			$changeFactory->newFromUpdate(
				ItemChange::REMOVE,
				$this->getItemWithSiteLinks( $q2, array( 'enwiki' => '2' ) ),
				null
			),
			'item connected to client was deleted'
		);

		$cases['add another sitelink to Q2'] = array(
			array( EntityUsage::SITELINK_USAGE ),
			$changeFactory->newFromUpdate(
				ItemChange::UPDATE,
				$this->getItemWithSiteLinks( $q2, array( 'enwiki' => '2' ) ),
				$this->getItemWithSiteLinks( $q2, array(
					'enwiki' => '2',
					'itwiki' => 'DUE',
				) )
			)
		);

		$cases['other language label change on Q1'] = array(
			array( EntityUsage::OTHER_USAGE ),
			$changeFactory->newFromUpdate(
				ItemChange::UPDATE,
				new Item( $q1 ),
				$this->getItemWithAliases( $q1, 'de', array( 'EINS' ) )
			)
		);

		$cases['local label change on Q1 (used by Q2)'] = array(
			array( EntityUsage::makeAspectKey( EntityUsage::LABEL_USAGE, 'en' ) ),
			$changeFactory->newFromUpdate(
				ItemChange::UPDATE,
				new Item( $q1 ),
				$this->getItemWithLabel( $q1, 'en', 'ONE' )
			)
		);

		$badges = array( new ItemId( 'Q34' ) );
		$cases['badge only change on Q1'] = array(
			array( EntityUsage::SITELINK_USAGE ),
			$changeFactory->newFromUpdate( ItemChange::UPDATE,
				$this->getItemWithSiteLinks( $q1, array( 'enwiki' => '1' ) ),
				$this->getItemWithSiteLinks( $q1, array( 'enwiki' => '1' ), $badges ) )
		);

		return $cases;
	}

	/**
	 * @dataProvider getChangedAspectsProvider
	 */
	public function testGetChangedAspects( array $expected, ItemChange $change ) {
		$referencedPagesFinder = $this->getAffectedPagesFinder( array() );

		$actual = $referencedPagesFinder->getChangedAspects( $change );

		sort( $expected );
		sort( $actual );
		$this->assertEquals( $expected, $actual );
	}

	public function getAffectedUsagesByPageProvider() {
		$changeFactory = TestChanges::getEntityChangeFactory();

		$q1 = new ItemId( 'Q1' );
		$q2 = new ItemId( 'Q2' );

		$q1SitelinkUsage = new EntityUsage( $q1, EntityUsage::SITELINK_USAGE );
		$q2SitelinkUsage = new EntityUsage( $q2, EntityUsage::SITELINK_USAGE );
		$q2AllUsage = new EntityUsage( $q2, EntityUsage::ALL_USAGE );
		$q2OtherUsage = new EntityUsage( $q2, EntityUsage::OTHER_USAGE );

		$q1LabelUsage_en = new EntityUsage( $q1, EntityUsage::LABEL_USAGE, 'en' );
		$q2LabelUsage = new EntityUsage( $q2, EntityUsage::LABEL_USAGE );
		$q2LabelUsage_en = new EntityUsage( $q2, EntityUsage::LABEL_USAGE, 'en' );
		$q2LabelUsage_de = new EntityUsage( $q2, EntityUsage::LABEL_USAGE, 'de' );

		$q1TitleUsage = new EntityUsage( $q1, EntityUsage::TITLE_USAGE );
		$q2TitleUsage = new EntityUsage( $q2, EntityUsage::TITLE_USAGE );

		// Page 1 is linked to Q1
		$page1Q1Usages = new PageEntityUsages( 1, array(
			$q1SitelinkUsage,
		) );

		// Page 2 uses label and title to link to Q1
		$page2Q1Usages = new PageEntityUsages( 2, array(
			$q1LabelUsage_en,
			$q1TitleUsage,
		) );

		// Page 1 uses label and title to link to Q2, and shows the German label too.
		$page1Q2Usages = new PageEntityUsages( 1, array(
			$q2LabelUsage, // "all languages" usage
			$q2TitleUsage,
		) );

		// Page 2 uses Q2 to render an infobox
		$page2Q2Usages = new PageEntityUsages( 2, array(
			$q2AllUsage,
		) );

		// Cases
		// item with link created
		// item with link deleted
		// link added
		// removed added
		// link changed
		// direct aspect match
		// no aspect match
		// all matches any
		// any matches all

		$cases = array();

		$cases['create linked item Q1'] = array(
			array(
				new PageEntityUsages( 1, array( $q1SitelinkUsage ) ),
			),
			array(), // No usages recorded yet
			$changeFactory->newFromUpdate(
				ItemChange::ADD,
				null,
				$this->getItemWithSiteLinks( $q1, array( 'enwiki' => '1' ) )
			)
		);

		$cases['unlink item Q1'] = array(
			array(
				new PageEntityUsages( 1, array( $q1SitelinkUsage ) ),
				new PageEntityUsages( 2, array( $q1TitleUsage ) ),
			),
			array( $page1Q1Usages, $page2Q1Usages ), // "1" was recorded to be linked to Q1 and the local title used on page "2"
			$changeFactory->newFromUpdate(
				ItemChange::UPDATE,
				$this->getItemWithSiteLinks( $q1, array( 'enwiki' => '1' ) ),
				new Item( $q1 )
			)
		);

		$cases['link item Q2'] = array(
			array(
				new PageEntityUsages( 1, array( $q2TitleUsage ) ),
				new PageEntityUsages( 2, array( $q2TitleUsage, $q2SitelinkUsage ) ),
			),
			array( $page1Q2Usages, $page2Q2Usages ),
			$changeFactory->newFromUpdate(
				ItemChange::UPDATE,
				new Item( $q2 ),
				$this->getItemWithSiteLinks( $q2, array( 'enwiki' => '2' ) )
			)
		);

		$cases['change link of Q1, with NO prior record'] = array(
			array(
				new PageEntityUsages( 1, array( $q1SitelinkUsage ) ),
				new PageEntityUsages( 2, array( $q1SitelinkUsage ) ),
			),
			array(),
			$changeFactory->newFromUpdate(
				ItemChange::UPDATE,
				$this->getItemWithSiteLinks( $q1, array( 'enwiki' => '1' ) ),
				$this->getItemWithSiteLinks( $q1, array( 'enwiki' => '2' ) )
			)
		);

		$cases['change link of Q1, with prior record'] = array(
			array(
				new PageEntityUsages( 1, array( $q1SitelinkUsage ) ),
				new PageEntityUsages( 2, array( $q1SitelinkUsage, $q1TitleUsage ) ),
			),
			array( $page1Q1Usages, $page2Q1Usages ),
			$changeFactory->newFromUpdate(
				ItemChange::UPDATE,
				$this->getItemWithSiteLinks( $q1, array( 'enwiki' => '1' ) ),
				$this->getItemWithSiteLinks( $q1, array( 'enwiki' => '2' ) )
			)
		);

		$badges = array( new ItemId( 'Q34' ) );
		$cases['badge only change on Q1'] = array(
			array(
				new PageEntityUsages( 1, array( $q1SitelinkUsage ) ),
			),
			array( $page1Q1Usages, $page2Q1Usages ),
			$changeFactory->newFromUpdate( ItemChange::UPDATE,
				$this->getItemWithSiteLinks( $q1, array( 'enwiki' => '1' ) ),
				$this->getItemWithSiteLinks( $q1, array( 'enwiki' => '1' ), $badges ) )
		);

		$cases['delete linked item Q2'] = array(
			array(
				new PageEntityUsages( 1, array( $q2TitleUsage ) ),
				new PageEntityUsages( 2, array( $q2TitleUsage, $q2SitelinkUsage ) ),
			),
			array( $page1Q2Usages, $page2Q2Usages ),
			$changeFactory->newFromUpdate(
				ItemChange::REMOVE,
				$this->getItemWithSiteLinks( $q2, array( 'enwiki' => '2' ) ),
				null
			),
			'item connected to client was deleted'
		);

		$cases['add another sitelink to Q2'] = array(
			array(
				new PageEntityUsages( 2, array( $q2SitelinkUsage ) ),
			),
			array( $page2Q2Usages ),
			$changeFactory->newFromUpdate(
				ItemChange::UPDATE,
				$this->getItemWithSiteLinks( $q2, array( 'enwiki' => '2' ) ),
				$this->getItemWithSiteLinks( $q2, array(
					'enwiki' => '2',
					'itwiki' => 'DUE',
				) )
			)
		);

		$cases['other language label change on Q1 (not used on any page)'] = array(
			array(),
			array( $page1Q1Usages, $page2Q1Usages ),
			$changeFactory->newFromUpdate(
				ItemChange::UPDATE,
				new Item( $q1 ),
				$this->getItemWithLabel( $q1, 'de', 'EINS' )
			)
		);

		$cases['other change on Q2 (used on page 2)'] = array(
			array(
				new PageEntityUsages( 2, array( $q2OtherUsage ) ),
			),
			array( $page1Q2Usages, $page2Q2Usages ),
			$changeFactory->newFromUpdate(
				ItemChange::UPDATE,
				new Item( $q2 ),
				$this->getItemWithAliases( $q2, 'fr', array( 'X', 'Y' ) )
			)
		);

		$cases['other language label change on Q2 (used on page 1 and 2)'] = array(
			array(
				new PageEntityUsages( 1, array( $q2LabelUsage_de ) ),
				new PageEntityUsages( 2, array( $q2LabelUsage_de ) ),
			),
			array( $page1Q2Usages, $page2Q2Usages ),
			$changeFactory->newFromUpdate(
				ItemChange::UPDATE,
				new Item( $q2 ),
				$this->getItemWithLabel( $q2, 'de', 'EINS' )
			)
		);

		$cases['local label change on Q1 (used by page 2)'] = array(
			array(
				new PageEntityUsages( 2, array( $q1LabelUsage_en ) ),
			),
			array( $page1Q1Usages, $page2Q1Usages ),
			$changeFactory->newFromUpdate(
				ItemChange::UPDATE,
				new Item( $q1 ),
				$this->getItemWithLabel( $q1, 'en', 'ONE' )
			)
		);

		$cases['local label change on Q2 (used by page 1 and page 2)'] = array(
			array(
				new PageEntityUsages( 1, array( $q2LabelUsage_en ) ),
				new PageEntityUsages( 2, array( $q2LabelUsage_en ) ),
			),
			array( $page1Q2Usages, $page2Q2Usages ),
			$changeFactory->newFromUpdate(
				ItemChange::UPDATE,
				new Item( $q2 ),
				$this->getItemWithLabel( $q2, 'en', 'TWO' )
			)
		);

		return $cases;
	}

	/**
	 * @dataProvider getAffectedUsagesByPageProvider
	 */
	public function testGetAffectedUsagesByPage( array $expected, array $usage, ItemChange $change ) {
		$referencedPagesFinder = $this->getAffectedPagesFinder( $usage );

		$actual = $referencedPagesFinder->getAffectedUsagesByPage( $change );

		$this->assertPageEntityUsages( $expected, $actual );
	}

	public function testGetAffectedUsagesByPage_withDeletedPage() {
		$pageTitle = 'RandomKitten-2x5jsg8j3bvmpm4!5';

		$affectedPagesFinder = new AffectedPagesFinder(
			$this->getSiteLinkUsageLookup( $pageTitle ),
			$this->getNamespaceChecker(),
			new TitleFactory(),
			'enwiki',
			'en',
			false
		);

		$itemId = new ItemId( 'Q1' );

		$changeFactory = TestChanges::getEntityChangeFactory();

		$change = $changeFactory->newFromUpdate(
			ItemChange::UPDATE,
			$this->getItemWithSiteLinks( $itemId, array( 'enwiki' => $pageTitle ) ),
			new Item( $itemId )
		);

		$usages = $affectedPagesFinder->getAffectedUsagesByPage( $change );

		$this->assertCount( 0, $usages );
	}

	private function getSiteLinkUsageLookup( $pageTitle ) {
		$siteLinkLookup = $this->getMock( 'Wikibase\Lib\Store\SiteLinkLookup' );

		$siteLinkLookup->expects( $this->any() )
			->method( 'getItemIdForLink' )
			->will( $this->returnValue( new ItemId( 'Q1' ) ) );

		$siteLinkLookup->expects( $this->any() )
			->method( 'getLinks' )
			->will( $this->returnValue( array(
				array( 'enwiki', $pageTitle, 1 )
			) ) );

		$titleFactory = new TitleFactory();

		return new SiteLinkUsageLookup( 'enwiki', $siteLinkLookup, $titleFactory );
	}

	/**
	 * @param ItemId $id
	 * @param string[] $links
	 * @param ItemId[] $badges
	 *
	 * @return Item
	 */
	private function getItemWithSiteLinks( ItemId $id, array $links, array $badges = array() ) {
		$item = new Item( $id );

		foreach ( $links as $siteId => $page ) {
			$item->getSiteLinkList()->addNewSiteLink( $siteId, $page, $badges );
		}

		return $item;
	}

	/**
	 * @param ItemId $id
	 * @param string $languageCode
	 * @param string $label
	 *
	 * @return Item
	 */
	private function getItemWithLabel( ItemId $id, $languageCode, $label ) {
		$item = new Item( $id );
		$item->setLabel( $languageCode, $label );

		return $item;
	}

	/**
	 * @param ItemId $id
	 * @param string $languageCode
	 * @param string[] $aliases
	 *
	 * @return Item
	 */
	private function getItemWithAliases( ItemId $id, $languageCode, array $aliases ) {
		$item = new Item( $id );
		$item->setAliases( $languageCode, $aliases );

		return $item;
	}

	/**
	 * @param PageEntityUsages[]|Iterator<PageEntityUsages> $usagesPerPage
	 *
	 * @return PageEntityUsages[]
	 */
	private function getPageEntityUsageStrings( $usagesPerPage ) {
		$strings = array();

		foreach ( $usagesPerPage as $pageUsages ) {
			$strings[] = "$pageUsages";
		}

		sort( $strings );
		return $strings;
	}

	private function assertPageEntityUsages( $expected, $actual, $message = '' ) {
		$this->assertEquals(
			$this->getPageEntityUsageStrings( $expected ),
			$this->getPageEntityUsageStrings( $actual ),
			$message
		);
	}

}
