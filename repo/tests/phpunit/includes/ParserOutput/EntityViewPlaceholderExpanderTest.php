<?php

namespace Wikibase\Repo\Tests\ParserOutput;

use PHPUnit_Framework_TestCase;
use User;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\Repo\ParserOutput\EntityViewPlaceholderExpander;
use Wikibase\View\DummyLocalizedTextProvider;
use Wikibase\View\LanguageDirectionalityLookup;
use Wikibase\View\Template\TemplateFactory;

/**
 * @covers Wikibase\Repo\ParserOutput\EntityViewPlaceholderExpander
 *
 * @uses Wikibase\View\TermsListView
 * @uses Wikibase\View\Template\Template
 * @uses Wikibase\View\Template\TemplateFactory
 * @uses Wikibase\View\Template\TemplateRegistry
 *
 * @group Wikibase
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class EntityViewPlaceholderExpanderTest extends PHPUnit_Framework_TestCase {

	/**
	 * @param User $user
	 * @param Item $item
	 *
	 * @return EntityViewPlaceholderExpander
	 */
	private function newExpander( User $user, Item $item ) {
		$templateFactory = TemplateFactory::getDefaultInstance();

		$termsLanguages = [ 'de', 'en', 'ru' ];

		$languageNameLookup = $this->getMock( LanguageNameLookup::class );

		return new EntityViewPlaceholderExpander(
			$templateFactory,
			$user,
			$item,
			$termsLanguages,
			$this->getMock( LanguageDirectionalityLookup::class ),
			$languageNameLookup,
			new DummyLocalizedTextProvider()
		);
	}

	public function provideEntity() {
		$item = new Item( new ItemId( 'Q23' ) );
		$item->setLabel( 'en', 'Moskow' );
		$item->setLabel( 'de', 'Moskau' );
		$item->setDescription( 'de', 'Hauptstadt Russlands' );

		return [
			[ $item ],
		];
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

	/**
	 * @dataProvider provideEntity
	 */
	public function testGetHtmlForPlaceholder( Item $item ) {
		$expander = $this->newExpander( $this->newUser(), $item );

		$html = $expander->getHtmlForPlaceholder( 'termbox' );
		$this->assertInternalType( 'string', $html );
	}

	/**
	 * @dataProvider provideEntity
	 */
	public function testRenderTermBox( Item $item ) {
		$expander = $this->newExpander( $this->newUser(), $item );

		// According to the mock objects, this should generate a term box for
		// 'de' and 'ru', since 'en' is already covered by the interface language.
		$html = $expander->renderTermBox();

		$this->assertContains( 'wikibase-entitytermsforlanguageview-en', $html );
		$this->assertContains( 'Moskow', $html );

		$this->assertContains( 'wikibase-entitytermsforlanguageview-de', $html );
		$this->assertContains( 'Moskau', $html );
		$this->assertContains( 'Hauptstadt Russlands', $html );

		$this->assertContains( 'wikibase-entitytermsforlanguageview-ru', $html );
	}

}
