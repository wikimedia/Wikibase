<?php

namespace Wikibase\Test;

use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Store\SiteLinkTable;

/**
 * @covers Wikibase\Lib\Store\SiteLinkTable
 *
 * @group Wikibase
 * @group WikibaseLib
 * @group SiteLink
 * @group WikibaseStore
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class SiteLinkTableTest extends \MediaWikiTestCase {

	/**
	 * @var SiteLinkTable
	 */
	protected $siteLinkTable;

	protected function setUp() {
		parent::setUp();

		if ( !defined( 'WB_VERSION' ) ) {
			$this->markTestSkipped( "Skipping because WikibaseClient doesn't have a local site link table." );
		}

		$this->siteLinkTable = new SiteLinkTable( 'wb_items_per_site', false );
	}

	public function itemProvider() {
		$items = array();

		$item = new Item( new ItemId( 'Q1' ) );
		$item->setLabel( 'en', 'Beer' );

		$siteLinks = array(
			'cswiki' => 'Pivo',
			'enwiki' => 'Beer',
			'jawiki' => 'ビール'
		);

		foreach ( $siteLinks as $siteId => $pageName ) {
			$item->getSiteLinkList()->addNewSiteLink( $siteId, $pageName );
		}

		$items[] = $item;

		return array( $items );
	}

	/**
	 * @dataProvider itemProvider
	 */
	public function testSaveLinksOfItem( Item $item ) {
		$res = $this->siteLinkTable->saveLinksOfItem( $item );
		$this->assertTrue( $res );
	}

	/**
	 * @depends testSaveLinksOfItem
	 */
	public function testSaveLinksOfItem_duplicate() {
		if ( wfGetDB( DB_MASTER )->getType() === 'sqlite' ) {
			$this->markTestSkipped( "Duplicated tables don't have indexes on them in SQLite, thus we can't test uniqueness handling." );
		}

		$item = new Item( new ItemId( 'Q2' ) );
		$item->getSiteLinkList()->addNewSiteLink( 'enwiki', 'Beer' );

		$res = $this->siteLinkTable->saveLinksOfItem( $item );
		$this->assertFalse( $res );
	}

	/**
	 * @dataProvider itemProvider
	 */
	public function testUpdateLinksOfItem() {
		// save initial links
		$item = new Item( new ItemId( 'Q177' ) );
		$item->getSiteLinkList()->addNewSiteLink( 'enwiki', 'Foo' );
		$item->getSiteLinkList()->addNewSiteLink( 'dewiki', 'Bar' );
		$item->getSiteLinkList()->addNewSiteLink( 'svwiki', 'Börk' );

		$this->siteLinkTable->saveLinksOfItem( $item );

		// modify links, and save again
		$item->getSiteLinkList()->removeLinkWithSiteId( 'enwiki' );
		$item->getSiteLinkList()->addNewSiteLink( 'enwiki', 'FooK' );
		$item->getSiteLinkList()->removeLinkWithSiteId( 'dewiki' );
		$item->getSiteLinkList()->addNewSiteLink( 'nlwiki', 'GrooK' );

		$this->siteLinkTable->saveLinksOfItem( $item );

		// check that the update worked correctly
		$actualLinks = $this->siteLinkTable->getSiteLinksForItem( $item->getId() );
		$expectedLinks = $item->getSiteLinks();

		$missingLinks = array_udiff( $expectedLinks, $actualLinks, array( $this->siteLinkTable, 'compareSiteLinks' ) );
		$extraLinks =   array_udiff( $actualLinks, $expectedLinks, array( $this->siteLinkTable, 'compareSiteLinks' ) );

		$this->assertEmpty( $missingLinks, 'Missing links' );
		$this->assertEmpty( $extraLinks, 'Extra links' );
	}

	/**
	 * @depends testSaveLinksOfItem
	 * @dataProvider itemProvider
	 */
	public function testGetSiteLinksOfItem( Item $item ) {
		$siteLinks = $this->siteLinkTable->getSiteLinksForItem( $item->getId() );

		$this->assertEquals(
			$item->getSiteLinks(),
			$siteLinks
		);
	}

	/**
	 * @depends testSaveLinksOfItem
	 * @dataProvider itemProvider
	 */
	public function testGetItemIdForSiteLink( Item $item ) {
		$siteLinks = $item->getSiteLinks();

		foreach ( $siteLinks as $siteLink ) {
			$this->assertEquals(
				$item->getId(),
				$this->siteLinkTable->getItemIdForSiteLink( $siteLink )
			);
		}
	}

	/**
	 * @depends testSaveLinksOfItem
	 * @dataProvider itemProvider
	 */
	public function testGetItemIdForLink( Item $item ) {
		$siteLinks = $item->getSiteLinks();

		foreach ( $siteLinks as $siteLink ) {
			$this->assertEquals(
				$item->getId(),
				$this->siteLinkTable->getItemIdForLink( $siteLink->getSiteId(), $siteLink->getPageName() )
			);
		}
	}

	/**
	 * @depends testSaveLinksOfItem
	 * @dataProvider itemProvider
	 */
	public function testDeleteLinksOfItem( Item $item ) {
		$this->assertTrue(
			$this->siteLinkTable->deleteLinksOfItem( $item->getId() ) !== false
		);

		$this->assertEmpty(
			$this->siteLinkTable->getSiteLinksForItem( $item->getId() )
		);
	}

}
