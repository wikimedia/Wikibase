<?php

namespace Wikibase\View\Tests;

use Language;
use MediaWikiTestCase;
use Title;
use User;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\Lib\Store\StorageException;
use Wikibase\Lib\MediaWikiContentLanguages;
use Wikibase\Lib\UserLanguageLookup;
use Wikibase\View\EntityViewPlaceholderExpander;
use Wikibase\View\Template\TemplateFactory;

/**
 * @covers Wikibase\View\EntityViewPlaceholderExpander
 *
 * @uses Wikibase\View\EntityTermsView
 * @uses Wikibase\View\Template\Template
 * @uses Wikibase\View\Template\TemplateFactory
 * @uses Wikibase\View\Template\TemplateRegistry
 *
 * @group Wikibase
 * @group WikibaseView
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class EntityViewPlaceholderExpanderTest extends MediaWikiTestCase {

	/**
	 * @param User $user
	 * @param Item $item
	 * @param ItemId $itemId
	 *
	 * @return EntityViewPlaceholderExpander
	 */
	private function newExpander( User $user, Item $item, ItemId $itemId ) {
		$templateFactory = TemplateFactory::getDefaultInstance();

		$title = $this->getMockBuilder( Title::class )
			->disableOriginalConstructor()
			->getMock();

		$language = Language::factory( 'en' );

		$idParser = $this->getMockBuilder( EntityIdParser::class )
			->disableOriginalConstructor()
			->getMock();
		$idParser->expects( $this->any() )
			->method( 'parse' )
			->will( $this->returnValue( $itemId ) );

		$userLanguages = $this->getMock( UserLanguageLookup::class );
		$userLanguages->expects( $this->any() )
			->method( 'getAllUserLanguages' )
			->will( $this->returnValue( array( 'de', 'en', 'ru' ) ) );

		$languageNameLookup = $this->getMock( LanguageNameLookup::class );

		return new EntityViewPlaceholderExpander(
			$templateFactory,
			$title,
			$user,
			$language,
			$idParser,
			$item,
			$item,
			$item,
			$userLanguages,
			new MediaWikiContentLanguages(),
			$languageNameLookup
		);
	}

	private function getItem() {
		$item = new Item( new ItemId( 'Q23' ) );

		$item->setLabel( 'en', 'Moskow' );
		$item->setLabel( 'de', 'Moskau' );

		$item->setDescription( 'de', 'Hauptstadt Russlands' );

		return $item;
	}

	/**
	 * @param bool $isAnon
	 *
	 * @return User
	 */
	private function newUser( $isAnon = false ) {
		$user = $this->getMockBuilder( User::class )
			->disableOriginalConstructor()
			->getMock();
		$user->expects( $this->any() )
			->method( 'isAnon' )
			->will( $this->returnValue( $isAnon ) );

		/** @var User $user */
		$user->setName( 'EntityViewPlaceholderExpanderTest-DummyUser' );

		return $user;
	}

	public function testGetHtmlForPlaceholder() {
		$item = $this->getItem();
		$expander = $this->newExpander( $this->newUser(), $item, $item->getId() );

		$html = $expander->getHtmlForPlaceholder( 'termbox', 'Q23' );
		$this->assertInternalType( 'string', $html );
	}

	public function testRenderTermBox() {
		$item = $this->getItem();
		$expander = $this->newExpander( $this->newUser(), $item, $item->getId() );

		// According to the mock objects, this should generate a term box for
		// 'de' and 'ru', since 'en' is already covered by the interface language.
		$html = $expander->renderTermBox( new ItemId( 'Q23' ), 0 );

		$this->assertContains( 'wikibase-entitytermsforlanguageview-en', $html );
		$this->assertContains( 'Moskow', $html );

		$this->assertContains( 'wikibase-entitytermsforlanguageview-de', $html );
		$this->assertContains( 'Moskau', $html );
		$this->assertContains( 'Hauptstadt Russlands', $html );

		$this->assertContains( 'wikibase-entitytermsforlanguageview-ru', $html );
	}

	public function testGetExtraUserLanguages() {
		$item = $this->getItem();
		$itemId = $item->getId();

		$expander = $this->newExpander( $this->newUser( true ), $item, $itemId );
		$this->assertArrayEquals( array(), $expander->getExtraUserLanguages() );

		$expander = $this->newExpander( $this->newUser(), $item, $itemId );
		$this->assertArrayEquals( array( 'de', 'ru' ), $expander->getExtraUserLanguages() );
	}

}
