<?php

namespace Wikibase\Test;

use MediaWikiSite;
use MediaWikiTestCase;
use SiteList;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\SiteLink;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\Lib\Store\EntityLookup;
use Wikibase\Repo\View\EditSectionGenerator;
use Wikibase\Repo\View\SiteLinksView;
use Wikibase\Template\TemplateFactory;
use Wikibase\Template\TemplateRegistry;

/**
 * @covers Wikibase\Repo\View\SiteLinksView
 *
 * @uses Wikibase\Template\Template
 * @uses Wikibase\Template\TemplateFactory
 * @uses Wikibase\Template\TemplateRegistry
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Adrian Lang <adrian.lang@wikimedia.de>
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class SiteLinksViewTest extends \MediaWikiTestCase {

	/**
	 * @dataProvider getHtmlProvider
	 */
	public function testGetHtml( Item $item, array $groups, $expectedValue ) {
		$siteLinksView = $this->getSiteLinksView();

		$value = $siteLinksView->getHtml( $item->getSiteLinks(), $item->getId(), $groups );
		$this->assertInternalType( 'string', $value );
		MediaWikiTestCase::assertTag( $expectedValue, $value, $value . ' did not match ' . var_export( $expectedValue, true ) );
	}

	public function getHtmlProvider() {
		$testCases = array();

		$item = new Item( new ItemId( 'Q1' ) );
		$item->getSiteLinkList()->addNewSiteLink( 'enwiki', 'test' );

		$testCases[] = array(
			$item,
			array( 'wikipedia' ),
			array(
				'tag' => 'div',
				'attributes' => array(
					'data-wb-sitelinks-group' => 'wikipedia'
				),
				'descendant' => array(
					'tag' => 'span',
					'class' => 'wikibase-sitelinkview-link-enwiki',
					'content' => 'test'
				)
			)
		);

		$item = new Item( new ItemId( 'Q1' ) );
		$item->getSiteLinkList()->addNewSiteLink( 'specialwiki', 'test' );

		$testCases[] = array(
			$item,
			array( 'special' ),
			array(
				'tag' => 'div',
				'attributes' => array(
					'data-wb-sitelinks-group' => 'special'
				),
			)
		);

		$item = new Item( new ItemId( 'Q1' ) );
		$item->addSiteLink( new SiteLink( 'enwiki', 'en test', array( new ItemId( 'Q42' ) ) ) );
		$item->addSiteLink( new SiteLink( 'dewiki', 'de test', array( new ItemId( 'Q42' ), new ItemId( 'Q12' ) ) ) );

		$testCases[] = array(
			$item,
			array( 'wikipedia' ),
			array(
				'tag' => 'div',
				'descendant' => array(
					'tag' => 'span',
					'attributes' => array(
						'class' => 'wb-badge wb-badge-Q42 wb-badge-featuredarticle',
						'title' => 'Featured article'
					)
				)
			)
		);

		$testCases[] = array(
			$item,
			array( 'wikipedia' ),
			array(
				'tag' => 'div',
				'descendant' => array(
					'tag' => 'span',
					'attributes' => array(
						'class' => 'wb-badge wb-badge-Q12 wb-badge-goodarticle',
						'title' => 'Q12'
					)
				)
			)
		);

		return $testCases;
	}

	/**
	 * @dataProvider getEmptyHtmlProvider
	 */
	public function testGetEmptyHtml( Item $item, array $groups ) {
		$siteLinksView = $this->getSiteLinksView();

		$value = $siteLinksView->getHtml( $item->getSiteLinks(), $item->getId(), $groups );
		$this->assertInternalType( 'string', $value );
		$this->assertEquals( '', $value );
	}

	public function getEmptyHtmlProvider() {
		$item = new Item( new ItemId( 'Q1' ) );

		$testCases = array();

		$testCases[] = array(
			$item,
			array(),
		);

		$item = $item->copy();
		$item->getSiteLinkList()->addNewSiteLink( 'enwiki', 'test' );

		$testCases[] = array(
			$item,
			array()
		);

		$newItem = new Item();

		// item with no id, as happens with new items
		$testCases[] = array(
			$newItem,
			array()
		);

		return $testCases;
	}

	/**
	 * @return SiteLinksView
	 */
	private function getSiteLinksView() {

		return new SiteLinksView(
			new TemplateFactory( TemplateRegistry::getDefaultInstance() ),
			$this->newSiteList(),
			$this->getEditSectionGeneratorMock(),
			$this->getEntityLookupMock(),
			new LanguageNameLookup(),
			array(
				'Q42' => 'wb-badge-featuredarticle',
				'Q12' => 'wb-badge-goodarticle'
			),
			array( 'special group' ),
			'en'
		);
	}

	/**
	 * @return EditSectionGenerator
	 */
	private function getEditSectionGeneratorMock() {
		$editSectionGenerator = $this->getMock( 'Wikibase\Repo\View\EditSectionGenerator' );

		return $editSectionGenerator;
	}

	/**
	 * @return SiteList
	 */
	private function newSiteList() {
		$dummySite = MediaWikiSite::newFromGlobalId( 'enwiki' );
		$dummySite->setGroup( 'wikipedia' );

		$dummySite2 = MediaWikiSite::newFromGlobalId( 'specialwiki' );
		$dummySite2->setGroup( 'special group' );

		$dummySite3 = MediaWikiSite::newFromGlobalId( 'dewiki' );
		$dummySite3->setGroup( 'wikipedia' );

		return new SiteList( array( $dummySite, $dummySite2, $dummySite3 ) );
	}

	/**
	 * @return EntityLookup
	 */
	private function getEntityLookupMock() {
		$entityLookup = $this->getMockBuilder( 'Wikibase\Lib\Store\EntityLookup' )
			->disableOriginalConstructor()
			->getMock();

		$entityLookup->expects( $this->any() )
			->method( 'getEntity' )
			->will( $this->returnCallback( function( EntityId $id ) {
				if ( $id->getSerialization() === 'Q42' ) {
					$item = new Item();
					$item->setLabel( 'en', 'Featured article' );
					return $item;
				}

				return null;
			} ) );

		return $entityLookup;
	}

}
