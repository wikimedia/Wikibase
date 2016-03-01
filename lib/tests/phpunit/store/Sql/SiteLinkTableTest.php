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
 * @license GPL-2.0+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class SiteLinkTableTest extends \MediaWikiTestCase {

	/**
	 * @var SiteLinkTable
	 */
	private $siteLinkTable;

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
		$item = new Item( new ItemId( 'Q2' ) );
		$item->getSiteLinkList()->addNewSiteLink( 'enwiki', 'Beer' );

		$res = $this->siteLinkTable->saveLinksOfItem( $item );
		$this->assertFalse( $res );
	}

	public function testUpdateLinksOfItem() {
		// save initial links
		$item = new Item( new ItemId( 'Q177' ) );
		$siteLinks = $item->getSiteLinkList();
		$siteLinks->addNewSiteLink( 'enwiki', 'Foo' );
		$siteLinks->addNewSiteLink( 'dewiki', 'Bar' );
		$siteLinks->addNewSiteLink( 'svwiki', 'Börk' );

		$this->siteLinkTable->saveLinksOfItem( $item );

		// modify links, and save again
		$siteLinks->removeLinkWithSiteId( 'enwiki' );
		$siteLinks->addNewSiteLink( 'enwiki', 'FooK' );
		$siteLinks->removeLinkWithSiteId( 'dewiki' );
		$siteLinks->addNewSiteLink( 'nlwiki', 'GrooK' );

		$this->siteLinkTable->saveLinksOfItem( $item );

		// check that the update worked correctly
		$actualLinks = $this->siteLinkTable->getSiteLinksForItem( $item->getId() );
		$this->assertArrayEquals( $siteLinks->toArray(), $actualLinks );
	}

	/**
	 * @depends testSaveLinksOfItem
	 * @dataProvider itemProvider
	 */
	public function testGetSiteLinksOfItem( Item $item ) {
		$siteLinks = $this->siteLinkTable->getSiteLinksForItem( $item->getId() );

		$this->assertArrayEquals(
			$item->getSiteLinkList()->toArray(),
			$siteLinks
		);
	}

	/**
	 * @depends testSaveLinksOfItem
	 * @dataProvider itemProvider
	 */
	public function testGetItemIdForSiteLink( Item $item ) {
		foreach ( $item->getSiteLinkList()->toArray() as $siteLink ) {
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
		foreach ( $item->getSiteLinkList()->toArray() as $siteLink ) {
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

	/**
	 * @depends testSaveLinksOfItem
	 * @dataProvider itemProvider
	 */
	public function testClear( Item $item ) {
		$this->assertTrue(
			$this->siteLinkTable->clear() !== false
		);

		$this->assertEmpty(
			$this->siteLinkTable->getSiteLinksForItem( $item->getId() )
		);
	}

}
