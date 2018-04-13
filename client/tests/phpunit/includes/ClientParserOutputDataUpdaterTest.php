<?php

namespace Wikibase\Client\Tests;

use PHPUnit4And6Compat;
use ParserOutput;
use Title;
use Wikibase\Client\Hooks\OtherProjectsSidebarGenerator;
use Wikibase\Client\Hooks\OtherProjectsSidebarGeneratorFactory;
use Wikibase\Client\ParserOutput\ClientParserOutputDataUpdater;
use Wikibase\Client\Usage\EntityUsage;
use Wikibase\Client\Usage\ParserOutputUsageAccumulator;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\SiteLinkList;
use Wikibase\Lib\Tests\MockRepository;

/**
 * @covers Wikibase\Client\ParserOutput\ClientParserOutputDataUpdater
 *
 * @group WikibaseClient
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Daniel Kinzler
 */
class ClientParserOutputDataUpdaterTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	/**
	 * @var MockRepository|null
	 */
	private $mockRepo = null;

	/**
	 * @return Item[]
	 */
	private function getItems() {
		$items = [];

		$item = new Item( new ItemId( 'Q1' ) );
		$item->setLabel( 'en', 'Foo' );
		$links = $item->getSiteLinkList();
		$links->addNewSiteLink( 'dewiki', 'Foo de' );
		$links->addNewSiteLink( 'enwiki', 'Foo en', [ new ItemId( 'Q17' ) ] );
		$links->addNewSiteLink( 'srwiki', 'Foo sr' );
		$links->addNewSiteLink( 'dewiktionary', 'Foo de word' );
		$links->addNewSiteLink( 'enwiktionary', 'Foo en word' );
		$items[] = $item;

		$item = new Item( new ItemId( 'Q2' ) );
		$item->setLabel( 'en', 'Talk:Foo' );
		$links = $item->getSiteLinkList();
		$links->addNewSiteLink( 'dewiki', 'Talk:Foo de' );
		$links->addNewSiteLink( 'enwiki', 'Talk:Foo en' );
		$links->addNewSiteLink( 'srwiki', 'Talk:Foo sr', [ new ItemId( 'Q17' ) ] );
		$items[] = $item;

		return $items;
	}

	/**
	 * @param string[] $otherProjects
	 *
	 * @return ClientParserOutputDataUpdater
	 */
	private function newInstance( array $otherProjects = [] ) {
		$this->mockRepo = new MockRepository();

		foreach ( $this->getItems() as $item ) {
			$this->mockRepo->putEntity( $item );
		}

		return new ClientParserOutputDataUpdater(
			$this->getOtherProjectsSidebarGeneratorFactory( $otherProjects ),
			$this->mockRepo,
			$this->mockRepo,
			'srwiki'
		);
	}

	/**
	 * @param string[] $otherProjects
	 *
	 * @return OtherProjectsSidebarGeneratorFactory
	 */
	private function getOtherProjectsSidebarGeneratorFactory( array $otherProjects ) {
		$generator = $this->getOtherProjectsSidebarGenerator( $otherProjects );

		$factory = $this->getMockBuilder( OtherProjectsSidebarGeneratorFactory::class )
			->disableOriginalConstructor()
			->getMock();

		$factory->expects( $this->any() )
			->method( 'getOtherProjectsSidebarGenerator' )
			->will( $this->returnValue( $generator ) );

		return $factory;
	}

	/**
	 * @param string $prefixedText
	 * @param bool $isRedirect
	 *
	 * @return Title
	 */
	private function getTitle( $prefixedText, $isRedirect = false ) {
		$title = $this->getMock( Title::class );

		$title->expects( $this->once() )
			->method( 'getPrefixedText' )
			->will( $this->returnValue( $prefixedText ) );

		$title->method( 'isRedirect' )
			->will( $this->returnValue( $isRedirect ) );

		return $title;
	}

	/**
	 * @param string[] $otherProjects
	 *
	 * @return OtherProjectsSidebarGenerator
	 */
	private function getOtherProjectsSidebarGenerator( array $otherProjects ) {
		$generator = $this->getMockBuilder( OtherProjectsSidebarGenerator::class )
			->disableOriginalConstructor()
			->getMock();

		$generator->expects( $this->any() )
			->method( 'buildProjectLinkSidebar' )
			->will( $this->returnValue( $otherProjects ) );

		return $generator;
	}

	public function testUpdateItemIdProperty() {
		$parserOutput = new ParserOutput();

		$titleText = 'Foo sr';
		$title = $this->getTitle( $titleText );

		$instance = $this->newInstance();

		$instance->updateItemIdProperty( $title, $parserOutput );
		$property = $parserOutput->getProperty( 'wikibase_item' );

		$itemId = $this->mockRepo->getItemIdForLink( 'srwiki', $titleText );
		$this->assertEquals( $itemId->getSerialization(), $property );

		$this->assertUsageTracking( $itemId, EntityUsage::SITELINK_USAGE, $parserOutput );
	}

	private function assertUsageTracking( ItemId $id, $aspect, ParserOutput $parserOutput ) {
		$usageAcc = new ParserOutputUsageAccumulator( $parserOutput );
		$usage = $usageAcc->getUsages();
		$expected = new EntityUsage( $id, $aspect );

		$this->assertContains( $expected, $usage, '', false, false );
	}

	public function testUpdateItemIdPropertyForUnconnectedPage() {
		$parserOutput = new ParserOutput();

		$titleText = 'Foo xx';
		$title = $this->getTitle( $titleText );

		$instance = $this->newInstance();

		$instance->updateItemIdProperty( $title, $parserOutput );
		$property = $parserOutput->getProperty( 'wikibase_item' );

		$this->assertFalse( $property );
	}

	/**
	 * @dataProvider updateOtherProjectsLinksDataProvider
	 */
	public function testUpdateOtherProjectsLinksData( $expected, $otherProjects, $titleText ) {
		$parserOutput = new ParserOutput();
		$title = $this->getTitle( $titleText );

		$instance = $this->newInstance( $otherProjects );

		$instance->updateOtherProjectsLinksData( $title, $parserOutput );
		$extensionData = $parserOutput->getExtensionData( 'wikibase-otherprojects-sidebar' );

		$this->assertEquals( $expected, $extensionData );
	}

	public function updateOtherProjectsLinksDataProvider() {
		return [
			'other project exists, page has site link' => [
				[ 'project' => 'catswiki' ],
				[ 'project' => 'catswiki' ],
				'Foo sr'
			],
			'other project exists, page has no site link' => [
				[],
				[ 'project' => 'catswiki' ],
				'Foo xx'
			],
			'no other projects, page has site link' => [
				[],
				[],
				'Foo sr'
			],
			'no site link for this page' => [
				[],
				[],
				'Foo xx'
			]
		];
	}

	public function testUpdateBadgesProperty() {
		$parserOutput = new ParserOutput();

		$title = $this->getTitle( 'Talk:Foo sr' );

		$instance = $this->newInstance();

		$instance->updateBadgesProperty( $title, $parserOutput );
		$this->assertTrue(
			$parserOutput->getProperty( 'wikibase-badge-Q17' ),
			'property "wikibase-badge-Q17" should be set'
		);
	}

	public function testUpdateBadgesProperty_removesPreviousData() {
		$parserOutput = new ParserOutput();
		$parserOutput->setProperty( 'wikibase-badge-Q17', true );

		$title = $this->getTitle( 'Foo sr' );

		$instance = $this->newInstance();

		$instance->updateBadgesProperty( $title, $parserOutput );
		$this->assertFalse(
			$parserOutput->getProperty( 'wikibase-badge-Q17' ),
			'property "wikibase-badge-Q17" should not be set'
		);
	}

	public function testUpdateBadgesProperty_inconsistentSiteLinkLookupEmptySiteLinkList() {
		$parserOutput = new ParserOutput();

		$title = $this->getTitle( 'Foo sr' );

		$siteLinkLookup = new MockRepository();
		$mockRepoNoSiteLinks = new MockRepository();
		foreach ( $this->getItems() as $item ) {
			$siteLinkLookup->putEntity( $item );

			$itemNoSiteLinks = $item->copy();
			$itemNoSiteLinks->setSiteLinkList( new SiteLinkList() );

			$mockRepoNoSiteLinks->putEntity( $itemNoSiteLinks );
		}

		$parserOutputDataUpdater = new ClientParserOutputDataUpdater(
			$this->getOtherProjectsSidebarGeneratorFactory( [] ),
			$siteLinkLookup,
			$mockRepoNoSiteLinks,
			'srwiki'
		);

		// Suppress warnings as this is supposed to throw one.
		\Wikimedia\suppressWarnings();
		$parserOutputDataUpdater->updateBadgesProperty( $title, $parserOutput );
		\Wikimedia\restoreWarnings();

		// Stuff didn't blow up
		$this->assertTrue( true );
	}

	public function testUpdateBadgesProperty_inconsistentSiteLinkLookupNoSuchEntity() {
		$parserOutput = new ParserOutput();

		$title = $this->getTitle( 'Foo sr' );

		$siteLinkLookup = new MockRepository();
		foreach ( $this->getItems() as $item ) {
			$siteLinkLookup->putEntity( $item );
		}

		$parserOutputDataUpdater = new ClientParserOutputDataUpdater(
			$this->getOtherProjectsSidebarGeneratorFactory( [] ),
			$siteLinkLookup,
			new MockRepository(),
			'srwiki'
		);

		// Suppress warnings as this is supposed to throw one.
		\Wikimedia\suppressWarnings();
		$parserOutputDataUpdater->updateBadgesProperty( $title, $parserOutput );
		\Wikimedia\restoreWarnings();

		// Stuff didn't blow up
		$this->assertTrue( true );
	}

	public function updateTrackingCategoriesDataProvider() {
		return [
			[ 'Foo sr', false, 0 ],
			[ 'Foo sr', true, 1 ],
			[ 'Foo xx', false, 0 ],
			[ 'Foo xx', true, 0 ],
		];
	}

	/**
	 * @dataProvider updateTrackingCategoriesDataProvider
	 */
	public function testUpdateTrackingCategories( $titleText, $isRedirect, $expected ) {
		$parserOutput = $this->getMock( ParserOutput::class );
		$parserOutput->expects( $this->exactly( $expected ) )
			->method( 'addTrackingCategory' );

		$title = $this->getTitle( $titleText, $isRedirect );

		$instance = $this->newInstance();
		$instance->updateTrackingCategories( $title, $parserOutput );
	}

}
