<?php

namespace Wikibase\Test;

use MediaWikiTestCase;
use SpecialPage;
use SpecialPageFactory;
use Wikibase\Repo\View\SectionEditLinkGenerator;
use Wikibase\Template\TemplateFactory;
use Wikibase\Template\TemplateRegistry;

/**
 * @covers Wikibase\Repo\View\SectionEditLinkGenerator
 *
 * @uses Wikibase\Template\Template
 * @uses Wikibase\Template\TemplateFactory
 * @uses Wikibase\Template\TemplateRegistry
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group EntityView
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Daniel Kinzler
 * @author Adrian Lang
 */
class SectionEditLinkGeneratorTest extends MediaWikiTestCase {

	protected function setUp() {
		// Make sure wgSpecialPages has the special pages this tests use
		$this->setMwGlobals(
			'wgSpecialPages',
			array(
				'Version' => new SpecialPage( 'Version' ),
				'SetLabel' => new SpecialPage( 'SetLabel' ),
				'FooBar' => new SpecialPage( 'FooBar' )
			)
		);

		SpecialPageFactory::resetList();
		$doubleLanguage = $this->getMock( 'Language', array( 'getSpecialPageAliases' ) );
		$doubleLanguage->mCode = 'en';
		$doubleLanguage->expects( $this->any() )
			->method( 'getSpecialPageAliases' )
			->will( $this->returnValue(
				array(
					'Version' => array( 'Version' ),
					'SetLabel' => array( 'SetLabel' ),
					'FooBar' => array( 'FooBar' ),
				)
			) );

		$this->setMwGlobals(
			'wgContLang',
			$doubleLanguage
		);
		parent::setUp();
	}
	/**
	 * @dataProvider getHtmlForEditSectionProvider
	 */
	public function testGetHtmlForEditSection( $expected, $pageName, $action, $enabled, $langCode ) {
		$generator = $this->newSectionEditLinkGenerator();

		$key = $action === 'add' ? 'wikibase-add' : 'wikibase-edit';
		$msg = wfMessage( $key )->inLanguage( $langCode );

		$html = $generator->getHtmlForEditSection( $pageName, array( 'Q1' ), '', $msg, $enabled );
		$matcher = array(
			'tag' => 'span',
			'class' => 'wikibase-toolbar'
		);

		$this->assertTag( $matcher, $html, "$action action" );
		$this->assertRegExp( $expected, $html, "$action button label" );
	}

	public function getHtmlForEditSectionProvider() {
		return array(
			array(
				'/' . wfMessage( 'wikibase-edit' )->inLanguage( 'es' )->text() . '/',
				'Version',
				'edit',
				true,
				'es'
			),
			array(
				'/' . wfMessage( 'wikibase-add' )->inLanguage( 'de' )->text() . '/',
				'Version',
				'add',
				true,
				'de'
			)
		);
	}

	/**
	 * @dataProvider getHtmlForEditSection_editUrlProvider
	 */
	public function testGetHtmlForEditSection_editUrl( $expected, $specialPageName, $specialPageParams ) {
		$generator = $this->newSectionEditLinkGenerator();

		$html = $generator->getHtmlForEditSection(
			$specialPageName,
			$specialPageParams,
			'add',
			wfMessage( 'wikibase-add' )
		);

		$this->assertTag( $expected, $html );
	}

	public function getHtmlForEditSection_editUrlProvider() {
		return array(
			array(
				array(
					'tag' => 'a',
					'attributes' => array( 'href' => 'regexp:+\bSpecial:Version/Q1$+' )
				),
				'Version',
				array( 'Q1' )
			),
			array(
				array(
					'tag' => 'a',
					'attributes' => array( 'href' => 'regexp:+\bSpecial:SetLabel/Q1/de$+' )
				),
				'SetLabel',
				array( 'Q1', 'de' ),
			),
			array(
				array(
					'tag' => 'a',
					'attributes' => array( 'href' => 'regexp:+\bSpecial:FooBar/Q1/de$+' )
				),
				'FooBar',
				array( 'Q1', 'de' ),
			)
		);
	}

	/**
	 * @dataProvider getHtmlForEditSection_disabledProvider
	 */
	public function testGetHtmlForEditSection_disabled( $specialPageName, $specialPageUrlParams, $enabled ) {
		$generator = $this->newSectionEditLinkGenerator();

		$html = $generator->getHtmlForEditSection(
			$specialPageName,
			$specialPageUrlParams,
			'edit',
			wfMessage( 'wikibase-edit' ),
			$enabled
		);

		$this->assertNotContains( '<a ', $html );
		$this->assertNotContains( 'wikibase-toolbar-button', $html );
	}

	public function getHtmlForEditSection_disabledProvider() {
		return array(
			array( 'SetLabel', array( 'Q1' ), false ),
			array( 'SetLabel', array(), true ),
			array( null, array( 'Q1' ), true ),
		);
	}

	private function newSectionEditLinkGenerator() {
		return new SectionEditLinkGenerator( new TemplateFactory( TemplateRegistry::getDefaultInstance() ) );
	}
}
