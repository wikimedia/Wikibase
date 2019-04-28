<?php

namespace Wikibase\Repo\Tests;

use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MovePage;
use TestSites;
use Title;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Store\EntityRevision;
use Wikibase\Repo\WikibaseRepo;
use WikiPage;
use WikitextContent;

/**
 * Tests prevention of moving pages in and out of the data NS.
 *
 * @group Wikibase
 * @group WikibaseItem
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author John Erling Blad < jeblad@gmail.com >
 */
class ItemMoveTest extends \MediaWikiTestCase {

	//@todo: make this a baseclass to use with all types of entities.

	/**
	 * @var EntityRevision
	 */
	protected $entityRevision;

	/**
	 * @var Title
	 */
	protected $itemTitle;

	/**
	 * @var WikiPage
	 */
	protected $page;

	/**
	 * This is to set up the environment
	 */
	protected function setUp() {
		parent::setUp();

		//TODO: remove global TestSites DB setup once we can inject sites sanely.
		static $hasSites = false;

		$wikibaseRepo = WikibaseRepo::getDefaultInstance();

		if ( !$hasSites ) {
			$sitesTable = MediaWikiServices::getInstance()->getSiteStore();
			$sitesTable->clear();
			$sitesTable->saveSites( TestSites::getSites() );
			$hasSites = true;
		}

		$item = new Item();
		$this->entityRevision = $wikibaseRepo->getEntityStore()->saveEntity( $item, '', $GLOBALS['wgUser'], EDIT_NEW );

		$id = $this->entityRevision->getEntity()->getId();
		$this->itemTitle = $wikibaseRepo->getEntityTitleLookup()->getTitleForId( $id );

		$title = Title::newFromText( 'wbmovetest', $this->getDefaultWikitextNS() );
		$this->page = new WikiPage( $title );
		$this->page->doEditContent( new WikitextContent( 'foobar' ), 'test' );
	}

	/**
	 * Tests @see WikibaseItem::getIdForSiteLink
	 * XXX That method doesn't exist
	 *
	 * @dataProvider provideMovePrevention
	 */
	public function testMovePrevention( $callback ) {
		list( $from, $to ) = $callback( $this->page, $this->itemTitle );
		$mp = new MovePage( $from, $to );
		$this->assertFalse( $mp->move( $this->getTestUser()->getUser() )->isOK() );
	}

	public function provideMovePrevention() {
		return [
			// Moving a regular page into data NS onto an existing item
			[ function ( $page, $itemTitle ) {
				return [ $page->getTitle(), $itemTitle ];
			} ],

			// Moving a regular page into data NS to an invalid location
			// @todo: test other types of entities too!
			[ function ( $page, $itemTitle ) {
				$itemNamespace = WikibaseRepo::getDefaultInstance()->getEntityNamespaceLookup()
					->getEntityNamespace( 'item' );
				return [ $page->getTitle(),
					Title::newFromText( $page->getTitle()->getText(), $itemNamespace ) ];
			} ],

			// Moving a regular page into data NS to an empty (but valid) location
			[ function ( $page, $itemTitle ) {
				return [ $page->getTitle(), WikibaseRepo::getDefaultInstance()
					->getEntityTitleLookup()->getTitleForId( new ItemId( 'Q42' ) ) ];
			} ],

			// Moving item page out of data NS onto an existing page
			[ function ( $page, $itemTitle ) {
				return [ $itemTitle, $page->getTitle() ];
			} ],

			// Moving item page out of data NS onto a non-existing page
			[ function ( $page, $itemTitle ) {
				return [ $itemTitle, Title::newFromText( 'wbmovetestitem' ) ];
			} ],

			// Moving item to an invalid location in the data NS
			[ function ( $page, $itemTitle ) {
				$itemNamespace = WikibaseRepo::getDefaultInstance()->getEntityNamespaceLookup()
					->getEntityNamespace( 'item' );
				return [ $itemTitle,
					Title::newFromText( $page->getTitle()->getText(), $itemNamespace ) ];
			} ],

			// Moving item to an valid location in the data NS
			[ function ( $page, $itemTitle ) {
				return [ $itemTitle, WikibaseRepo::getDefaultInstance()->getEntityTitleLookup()
					->getTitleForId( new ItemId( 'Q42' ) ) ];
			} ],
		];
	}

}
