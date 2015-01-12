<?php

namespace Wikibase\Repo\Tests\Hooks;

use Language;
use RequestContext;
use Title;
use Linker;
use SpecialPageFactory;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\LanguageFallbackChain;
use Wikibase\LanguageWithConversion;
use Wikibase\Lib\Store\StorageException;
use Wikibase\Lib\Store\TermLookup;
use Wikibase\Repo\EntityNamespaceLookup;
use Wikibase\Repo\Hooks\LinkBeginHookHandler;
use Wikibase\Store\EntityIdLookup;

/**
 * @covers Wikibase\Repo\Hooks\LinkBeginHookHandler
 *
 * @since 0.5
 *
 * @group WikibaseRepo
 * @group Wikibase
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Daniel Kinzler
 */
class LinkBeginHookHandlerTest extends \MediaWikiTestCase {

	const ITEM_WITH_LABEL = 'Q1';
	const ITEM_WITHOUT_LABEL = 'Q11';
	const ITEM_DELETED = 'Q111';

	public function testDoOnLinkBegin() {
		$contextTitle = Title::newFromText( 'Special:Recentchanges' );
		$linkBeginHookHandler = $this->getLinkBeginHookHandler();

		$title = Title::makeTitle( NS_MAIN, self::ITEM_WITH_LABEL );
		$title->resetArticleID( 1 );
		$this->assertTrue( $title->exists() ); // sanity check

		$html = $title->getFullText();
		$customAttribs = array();

		$out = $this->getOutputPage( $contextTitle );
		$linkBeginHookHandler->doOnLinkBegin( $title, $html, $customAttribs, $out );

		$expectedHtml = '<span class="wb-itemlink">'
			. '<span class="wb-itemlink-label" lang="en" dir="ltr">linkbegin-label</span> '
			. '<span class="wb-itemlink-id">(' . self::ITEM_WITH_LABEL . ')</span></span>';

		$this->assertEquals( $expectedHtml, $html );

		$this->assertContains( 'linkbegin-label', $customAttribs['title'] );
		$this->assertContains( 'linkbegin-description', $customAttribs['title'] );

		$this->assertContains( 'wikibase.common', $out->getModuleStyles() );
	}

	public function testDoOnLinkBegin_onNonSpecialPage() {
		$linkBeginHookHandler = $this->getLinkBeginHookHandler();

		$title = Title::makeTitle( NS_MAIN, self::ITEM_WITH_LABEL );
		$title->resetArticleID( 1 );
		$this->assertTrue( $title->exists() ); // sanity check

		$titleText = $title->getFullText();
		$html = $titleText;
		$customAttribs = array();

		$out = $this->getOutputPage( Title::newMainPage() );
		$linkBeginHookHandler->doOnLinkBegin( $title, $html, $customAttribs, $out );

		$this->assertEquals( $titleText, $html );
		$this->assertEquals( array(), $customAttribs );
	}

	public function overrideSpecialNewEntityLinkProvider() {
		$entityTypes = array_keys( WikibaseRepo::getDefaultInstance()->getContentModelMappings() );

		$linkTitles = array();
		foreach ( $entityTypes as $entityType ) {
			$linkTitles[] = array( 'New' . ucfirst( $entityType ) );
		}

		return $linkTitles;
	}

	/**
	 * @dataProvider overrideSpecialNewEntityLinkProvider
	 * @param string $linkTitle
	 */
	public function testDoOnLinkBegin_overrideSpecialNewEntityLink( $linkTitle ) {
		$contextTitle = Title::newFromText( 'Special:Recentchanges' );
		$linkBeginHookHandler = $this->getLinkBeginHookHandler();

		$title = Title::makeTitle( NS_MAIN, $linkTitle );
		$html = $title->getFullText();
		$out = $this->getOutputPage( $contextTitle );
		$attribs = array();

		$linkBeginHookHandler->doOnLinkBegin( $title, $html, $attribs, $out );

		$specialPageTitle = Title::makeTitle(
			NS_SPECIAL,
			SpecialPageFactory::getLocalNameFor( $linkTitle )
		);

		$this->assertContains( Linker::linkKnown( $specialPageTitle ), $html );
		$this->assertContains( $specialPageTitle->getFullText(), $html );
	}

	public function testDoOnLinkBegin_nonEntityTitleLink() {
		$contextTitle = Title::newFromText( 'Special:Recentchanges' );
		$linkBeginHookHandler = $this->getLinkBeginHookHandler();

		$title = Title::newMainPage();
		$title->resetArticleID( 1 );
		$this->assertTrue( $title->exists() ); // sanity check

		$titleText = $title->getFullText();
		$html = $titleText;
		$customAttribs = array();

		$out = $this->getOutputPage( $contextTitle );
		$linkBeginHookHandler->doOnLinkBegin( $title, $html, $customAttribs, $out );

		$this->assertEquals( $titleText, $html );
		$this->assertEquals( array(), $customAttribs );
	}

	public function testDoOnLinkBegin_unknownEntityTitle() {
		$contextTitle = Title::newFromText( 'Special:Recentchanges' );
		$linkBeginHookHandler = $this->getLinkBeginHookHandler();

		$title = Title::makeTitle( NS_MAIN, self::ITEM_DELETED );
		$title->resetArticleID( 0 );
		$this->assertFalse( $title->exists() ); // sanity check

		$titleText = $title->getFullText();
		$html = $titleText;
		$customAttribs = array();

		$out = $this->getOutputPage( $contextTitle );
		$linkBeginHookHandler->doOnLinkBegin( $title, $html, $customAttribs, $out );

		$this->assertEquals( $titleText, $html );
		$this->assertEquals( array(), $customAttribs );
	}

	public function testDoOnLinkBegin_itemHasNoLabel() {
		$contextTitle = Title::newFromText( 'Special:Recentchanges' );
		$linkBeginHookHandler = $this->getLinkBeginHookHandler();

		$title = Title::makeTitle( NS_MAIN, self::ITEM_WITHOUT_LABEL );
		$title->resetArticleID( 1 );
		$this->assertTrue( $title->exists() ); // sanity check

		$html = $title->getFullText();
		$customAttribs = array();

		$out = $this->getOutputPage( $contextTitle );
		$linkBeginHookHandler->doOnLinkBegin( $title, $html, $customAttribs, $out );

		$expected = '<span class="wb-itemlink">'
			. '<span class="wb-itemlink-label" lang="en" dir="ltr"></span> '
			. '<span class="wb-itemlink-id">(' . self::ITEM_WITHOUT_LABEL . ')</span></span>';

		$this->assertEquals( $expected, $html );
		$this->assertContains( self::ITEM_WITHOUT_LABEL, $customAttribs['title'] );
	}

	private function getOutputPage( Title $title ) {
		$context = RequestContext::newExtraneousContext( $title );
		return $context->getOutput();
	}

	/**
	 * @return EntityIdLookup
	 */
	private function getEntityIdLookup() {
		$entityIdLookup = $this->getMock( 'Wikibase\Store\EntityIdLookup' );

		$entityIdLookup->expects( $this->any() )
			->method( 'getEntityIdForTitle' )
			->will( $this->returnCallback( function( Title $title ) {
				if ( preg_match( '/^Q(\d+)$/', $title->getText(), $m ) ) {
					return new ItemId( $m[0] );
				}

				return null;
			} ) );

		return $entityIdLookup;
	}

	/**
	 * @return TermLookup
	 */
	private function getTermLookup() {
		$termLookup = $this->getMock( 'Wikibase\Lib\Store\TermLookup' );

		$termLookup->expects( $this->any() )
			->method( 'getLabels' )
			->will( $this->returnCallback( function ( EntityId $id ) {
				if ( $id->getSerialization() == LinkBeginHookHandlerTest::ITEM_WITH_LABEL ) {
					return array( 'en' => 'linkbegin-label' );
				}

				if ( $id->getSerialization() == LinkBeginHookHandlerTest::ITEM_WITHOUT_LABEL ) {
					return array();
				}

				throw new StorageException( 'No such entity: ' . $id->getSerialization() );
			} ) );

		$termLookup->expects( $this->any() )
			->method( 'getDescriptions' )
			->will( $this->returnCallback( function ( EntityId $id ) {
				if ( $id->getSerialization() == LinkBeginHookHandlerTest::ITEM_WITH_LABEL ) {
					return array( 'en' => 'linkbegin-description' );
				}


				if ( $id->getSerialization() == LinkBeginHookHandlerTest::ITEM_WITHOUT_LABEL ) {
					return array();
				}

				throw new StorageException( 'No such entity: ' . $id->getSerialization() );
			} ) );

		return $termLookup;
	}

	private function getEntityNamespaceLookup() {
		$entityNamespaces = array(
			'wikibase-item' => 0,
			'wikibase-property' => 102
		);

		return new EntityNamespaceLookup( $entityNamespaces );
	}

	private function getLinkBeginHookHandler() {
		$languageFallback = new LanguageFallbackChain( array(
			LanguageWithConversion::factory( 'de-ch' ),
			LanguageWithConversion::factory( 'de' ),
			LanguageWithConversion::factory( 'en' ),
		) );

		return new LinkBeginHookHandler(
			$this->getEntityIdLookup(),
			$this->getTermLookup(),
			$this->getEntityNamespaceLookup(),
			$languageFallback,
			Language::factory( 'en' )
		);

	}

}
