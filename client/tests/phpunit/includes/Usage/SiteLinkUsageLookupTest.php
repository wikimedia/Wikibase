<?php

namespace Wikibase\Client\Tests\Usage\Sql;

use MediaWikiTestCase;
use Title;
use Wikibase\Client\Store\TitleFactory;
use Wikibase\Client\Usage\EntityUsage;
use Wikibase\Client\Usage\PageEntityUsages;
use Wikibase\Client\Usage\SiteLinkUsageLookup;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Store\HashSiteLinkStore;
use Wikibase\Lib\Store\SiteLinkLookup;

/**
 * @covers Wikibase\Client\Usage\SiteLinkUsageLookup
 *
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseUsageTracking
 * @group Database
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class SiteLinkUsageLookupTest extends MediaWikiTestCase {

	const PAGE_NAME_PREFIX = 'Page number ';

	/**
	 * @param ItemId[] $links
	 *
	 * @return SiteLinkLookup
	 */
	private function getSiteLinkLookup( array $links ) {
		$siteLinkLookup = new HashSiteLinkStore();

		foreach ( $links as $pageId => $itemId ) {
			$pageName = self::PAGE_NAME_PREFIX . $pageId;

			$item = new Item( $itemId );
			$item->getSiteLinkList()->addNewSiteLink( 'testwiki', $pageName );
			$item->getSiteLinkList()->addNewSiteLink( 'badwiki', $pageName );
			$item->getSiteLinkList()->addNewSiteLink( 'sadwiki', 'Other stuff' );

			$siteLinkLookup->saveLinksOfItem( $item );
		}

		return $siteLinkLookup;
	}

	/**
	 * @param SiteLinkLookup $siteLinks
	 * @param TitleFactory $titleFactory
	 *
	 * @return SiteLinkUsageLookup
	 */
	private function getUsageLookup( SiteLinkLookup $siteLinks, TitleFactory $titleFactory ) {
		return new SiteLinkUsageLookup(
			'testwiki',
			$siteLinks,
			$titleFactory
		);
	}

	/**
	 * @return TitleFactory
	 */
	private function getTitleFactory() {
		$titleFactory = $this->getMock( TitleFactory::class );
		$titleFactory->expects( $this->any() )
			->method( 'newFromText' )
			->will( $this->returnCallback( function( $text ) {
				$title = Title::newFromText( $text );
				$title->resetArticleID(
					substr( $text, strlen( self::PAGE_NAME_PREFIX ) )
				);
				return $title;
			} ) );
		$titleFactory->expects( $this->any() )
			->method( 'newFromID' )
			->will( $this->returnCallback( function( $pageId ) {
				$title = Title::newFromText( self::PAGE_NAME_PREFIX . $pageId );
				$title->resetArticleID( $pageId );
				return $title;
			} ) );

		return $titleFactory;
	}

	public function testGetUsagesForPage() {
		$links = $this->getSiteLinkLookup( array(
			'23' => new ItemId( 'Q23' ),
		) );

		$titleFactory = $this->getTitleFactory();
		$lookup = $this->getUsageLookup( $links, $titleFactory );

		$actual = $lookup->getUsagesForPage( 42 );
		$this->assertEmpty( $actual );

		$actual = $lookup->getUsagesForPage( 23 );
		$this->assertCount( 1, $actual );
		$this->assertEquals( 'Q23#S', $actual[0]->getIdentityString() );
	}

	public function testGetPagesUsing() {
		$q23 = new ItemId( 'Q23' );
		$q42 = new ItemId( 'Q42' );
		$p11 = new PropertyId( 'P11' );

		$links = $this->getSiteLinkLookup( array(
			'23' => $q23,
		) );

		$titleFactory = $this->getTitleFactory();
		$lookup = $this->getUsageLookup( $links, $titleFactory );

		$actual = $lookup->getPagesUsing( array( $q42, $p11 ) );
		$this->assertInstanceOf( 'Traversable', $actual );
		$this->assertCount( 0, $actual );

		$actual = $lookup->getPagesUsing( array( $q23 ), array( EntityUsage::OTHER_USAGE ) );
		$this->assertInstanceOf( 'Traversable', $actual );
		$this->assertContainsOnlyInstancesOf( 'Wikibase\Client\Usage\PageEntityUsages', $actual );
		$this->assertCount( 1, $actual );

		/** @var PageEntityUsages[] $actual */
		$pageUsageObject = $actual[0];
		$this->assertEquals( 23, $pageUsageObject->getPageId() );

		$usages = $pageUsageObject->getUsages();
		$this->assertInternalType( 'array', $usages );
		$this->assertContainsOnlyInstancesOf( 'Wikibase\Client\Usage\EntityUsage', $usages );
		$this->assertCount( 1, $usages );

		$usage = reset( $usages );
		$this->assertEquals( EntityUsage::ALL_USAGE, $usage->getAspect() );
		$this->assertEquals( $q23, $usage->getEntityId() );

		$actual = $lookup->getPagesUsing( array( $q42, $q23, $p11 ) );
		$this->assertInstanceOf( 'Traversable', $actual );
		$this->assertContainsOnlyInstancesOf( 'Wikibase\Client\Usage\PageEntityUsages', $actual );
		$this->assertCount( 1, $actual );

		$pageUsageObject = $actual[0];
		$this->assertEquals( 23, $pageUsageObject->getPageId() );

		$usages = $pageUsageObject->getUsages();
		$this->assertInternalType( 'array', $usages );
		$this->assertContainsOnlyInstancesOf( 'Wikibase\Client\Usage\EntityUsage', $usages );
		$this->assertCount( 1, $usages );

		$usage = reset( $usages );
		$this->assertEquals( EntityUsage::ALL_USAGE, $usage->getAspect() );
		$this->assertEquals( $q23, $usage->getEntityId() );
	}

	public function testGetUnusedEntities() {
		$q23 = new ItemId( 'Q23' );
		$q42 = new ItemId( 'Q42' );
		$p11 = new PropertyId( 'P11' );

		$links = $this->getSiteLinkLookup( array(
			'23' => $q23,
		) );

		$titleFactory = $this->getTitleFactory();
		$lookup = $this->getUsageLookup( $links, $titleFactory );

		$actual = $lookup->getUnusedEntities( array() );
		$this->assertEmpty( $actual );

		$actual = $lookup->getUnusedEntities( array( $q23 ) );
		$this->assertEmpty( $actual );

		$actual = $lookup->getUnusedEntities( array( $q42, $q23 ) );
		$this->assertCount( 1, $actual );
		$this->assertEquals( $q42, $actual[0] );

		$actual = $lookup->getUnusedEntities( array( $q23, $p11 ) );
		$this->assertCount( 1, $actual );
		$this->assertEquals( $p11, $actual[0] );
	}

	public function testGetPagesUsing_withDeletePage() {
		$itemId = new ItemId( 'Q23' );

		$links = $this->getSiteLinkLookup(
			array(
				'randomkitten2u8!kgxhkl4v3' => $itemId
			)
		);

		$titleFactory = new TitleFactory();
		$lookup = $this->getUsageLookup( $links, $titleFactory );

		$usages = $lookup->getPagesUsing( array( $itemId ), array() );

		$this->assertInstanceOf( 'Traversable', $usages );
		$this->assertCount( 0, $usages );
	}

}
