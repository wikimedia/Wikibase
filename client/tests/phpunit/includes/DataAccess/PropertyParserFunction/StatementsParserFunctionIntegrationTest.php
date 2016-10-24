<?php

namespace Wikibase\Client\Tests\DataAccess\PropertyParserFunction;

use Language;
use MediaWikiTestCase;
use Parser;
use ParserOptions;
use Title;
use User;
use Wikibase\Client\Tests\DataAccess\WikibaseDataAccessTestItemSetUpHelper;
use Wikibase\Client\WikibaseClient;
use Wikibase\Test\MockClientStore;

/**
 * Simple integration test for the {{#statements:…}} parser function.
 *
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseDataAccess
 * @group WikibaseIntegration
 * @group Database
 *
 * @license GPL-2.0+
 * @author Marius Hoch < hoo@online.de >
 */
class StatementsParserFunctionIntegrationTest extends MediaWikiTestCase {

	protected function setUp() {
		parent::setUp();

		$wikibaseClient = WikibaseClient::getDefaultInstance( 'reset' );
		$store = $wikibaseClient->getStore();

		if ( !( $store instanceof MockClientStore ) ) {
			$store = new MockClientStore( 'de' );
			$wikibaseClient->overrideStore( $store );
		}

		$this->assertInstanceOf(
			MockClientStore::class,
			$wikibaseClient->getStore(),
			'Mocking the default ClientStore failed'
		);

		$this->setMwGlobals( 'wgContLang', Language::factory( 'de' ) );

		$setupHelper = new WikibaseDataAccessTestItemSetUpHelper( $store );
		$setupHelper->setUp();

		$this->oldAllowDataAccessInUserLanguage = $wikibaseClient->getSettings()->getSetting( 'allowDataAccessInUserLanguage' );
		$this->setAllowDataAccessInUserLanguage( false );
	}

	protected function tearDown() {
		parent::tearDown();

		$this->setAllowDataAccessInUserLanguage( $this->oldAllowDataAccessInUserLanguage );
		WikibaseClient::getDefaultInstance( 'reset' );
	}

	/**
	 * @param bool $value
	 */
	private function setAllowDataAccessInUserLanguage( $value ) {
		$settings = WikibaseClient::getDefaultInstance()->getSettings();
		$settings->setSetting( 'allowDataAccessInUserLanguage', $value );
	}

	public function testStatementsParserFunction_byPropertyLabel() {
		$result = $this->parseWikitextToHtml( '{{#statements:LuaTestStringProperty}}' );

		$this->assertSame( "<p><span>Lua&#160;:)</span>\n</p>", $result );
	}

	public function testStatementsParserFunction_byPropertyId() {
		$result = $this->parseWikitextToHtml( '{{#statements:P342}}' );

		$this->assertSame( "<p><span>Lua&#160;:)</span>\n</p>", $result );
	}

	public function testStatementsParserFunction_arbitraryAccess() {
		$result = $this->parseWikitextToHtml( '{{#statements:P342|from=Q32488}}' );

		$this->assertSame( "<p><span>Lua&#160;:)</span>\n</p>", $result );
	}

	public function testStatementsParserFunction_multipleValues() {
		$result = $this->parseWikitextToHtml( '{{#statements:P342|from=Q32489}}' );

		$this->assertSame( "<p><span><span>Lua&#160;:)</span>, <span>Lua&#160;:)</span></span>\n</p>", $result );
	}

	public function testStatementsParserFunction_arbitraryAccessNotFound() {
		$result = $this->parseWikitextToHtml( '{{#statements:P342|from=Q1234567}}' );

		$this->assertSame( '', $result );
	}

	public function testStatementsParserFunction_byNonExistent() {
		$result = $this->parseWikitextToHtml( '{{#statements:P2147483647}}' );

		$this->assertRegExp(
			'/<p.*class=".*wikibase-error.*">.*P2147483647.*<\/p>/',
			$result
		);
	}

	public function testStatementsParserFunction_pageNotConnected() {
		$result = $this->parseWikitextToHtml(
			'{{#statements:P342}}',
			'A page not connected to an item'
		);

		$this->assertSame( '', $result );
	}

	/**
	 * @param string $wikiText
	 * @param string $title
	 *
	 * @return string HTML
	 */
	private function parseWikitextToHtml( $wikiText, $title = 'WikibaseClientDataAccessTest' ) {
		$settings = WikibaseClient::getDefaultInstance()->getSettings();
		$enabled = $settings->getSetting( 'enableStatementsParserFunction' );
		$settings->setSetting( 'enableStatementsParserFunction', true );

		$popt = new ParserOptions( User::newFromId( 0 ), Language::factory( 'en' ) );

		$parser = new Parser( [ 'class' => 'Parser' ] );
		$pout = $parser->parse( $wikiText, Title::newFromText( $title ), $popt, Parser::OT_HTML );

		$settings->setSetting( 'enableStatementsParserFunction', $enabled );
		return $pout->getText();
	}

}
