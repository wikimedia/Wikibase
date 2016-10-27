<?php

namespace Wikibase\Client\Tests\DataAccess\Scribunto;

use Language;
use LuaSandboxFunction;
use Scribunto_LuaEngine;
use Scribunto_LuaStandaloneInterpreterFunction;
use Wikibase\Client\DataAccess\Scribunto\Scribunto_LuaWikibaseEntityLibrary;
use Wikibase\Client\WikibaseClient;

/**
 * @covers Wikibase\Client\DataAccess\Scribunto\Scribunto_LuaWikibaseEntityLibrary
 *
 * @group WikibaseScribunto
 * @group WikibaseIntegration
 * @group WikibaseClient
 * @group Wikibase
 *
 * @license GPL-2.0+
 * @author Marius Hoch < hoo@online.de >
 */
class Scribunto_LuaWikibaseEntityLibraryTest extends Scribunto_LuaWikibaseLibraryTestCase {

	/**
	 * @var bool
	 */
	private $oldAllowDataAccessInUserLanguage;

	/**
	 * @var bool
	 */
	private $oldEnableLuaEntityFormatStatements;

	protected static $moduleName = 'LuaWikibaseEntityLibraryTests';

	protected function getTestModules() {
		return parent::getTestModules() + [
			'LuaWikibaseEntityLibraryTests' => __DIR__ . '/LuaWikibaseEntityLibraryTests.lua',
		];
	}

	protected function setUp() {
		parent::setUp();

		$settings = WikibaseClient::getDefaultInstance()->getSettings();

		$this->oldEnableLuaEntityFormatStatements = $settings->getSetting( 'enableLuaEntityFormatStatements' );
		$settings->setSetting( 'enableLuaEntityFormatStatements', true );

		$this->oldAllowDataAccessInUserLanguage = $settings->getSetting( 'allowDataAccessInUserLanguage' );
		$this->setAllowDataAccessInUserLanguage( false );
	}

	protected function tearDown() {
		parent::tearDown();

		$this->setAllowDataAccessInUserLanguage( $this->oldAllowDataAccessInUserLanguage );

		$settings = WikibaseClient::getDefaultInstance()->getSettings();
		$settings->setSetting(
			'enableLuaEntityFormatStatements',
			$this->oldEnableLuaEntityFormatStatements
		);
	}

	public function allowDataAccessInUserLanguageProvider() {
		return [
			[ true ],
			[ false ],
		];
	}

	public function testConstructor() {
		$engine = $this->getEngine();
		$luaWikibaseLibrary = new Scribunto_LuaWikibaseEntityLibrary( $engine );
		$this->assertInstanceOf( Scribunto_LuaWikibaseEntityLibrary::class, $luaWikibaseLibrary );
	}

	public function testRegister() {
		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary();
		$package = $luaWikibaseLibrary->register();

		$this->assertInternalType( 'array', $package );
		$this->assertArrayHasKey( 'create', $package );

		// The value of create depends on the Lua runtime in use.
		$isLuaFunction =
			( $package['create'] instanceof Scribunto_LuaStandaloneInterpreterFunction ) ||
			( $package['create'] instanceof LuaSandboxFunction );

		$this->assertTrue(
			$isLuaFunction,
			'$package[\'create\'] needs to be Scribunto_LuaStandaloneInterpreterFunction or LuaSandboxFunction'
		);
	}

	public function testGetGlobalSiteId() {
		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary();

		$expected = array(
			WikibaseClient::getDefaultInstance()->getSettings()->getSetting( 'siteGlobalID' )
		);

		$this->assertSame( $expected, $luaWikibaseLibrary->getGlobalSiteId() );
	}

	/**
	 * @dataProvider allowDataAccessInUserLanguageProvider
	 */
	public function testFormatPropertyValues( $allowDataAccessInUserLanguage ) {
		$cacheSplit = false;
		$this->setAllowDataAccessInUserLanguage( $allowDataAccessInUserLanguage );

		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary( $cacheSplit );

		$this->assertSame(
			[ '' ],
			$luaWikibaseLibrary->formatPropertyValues( 'Q1', 'P65536', [] )
		);

		$this->assertSame( $allowDataAccessInUserLanguage, $cacheSplit );
	}

	public function testFormatPropertyValues_noPropertyId() {
		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary();

		$this->assertSame(
			[ '' ],
			$luaWikibaseLibrary->formatPropertyValues( 'Q1', 'father', [] )
		);
	}

	/**
	 * @dataProvider allowDataAccessInUserLanguageProvider
	 */
	public function testFormatPropertyValues_usage( $allowDataAccessInUserLanguage ) {
		$cacheSplit = false;
		$this->setAllowDataAccessInUserLanguage( $allowDataAccessInUserLanguage );

		$lang = Language::factory( 'es' );
		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary( $cacheSplit, $lang );

		$this->assertSame(
			[ 'Q885588' ],
			$luaWikibaseLibrary->formatPropertyValues( 'Q32488', 'P456', null )
		);

		$usages = $luaWikibaseLibrary->getUsageAccumulator()->getUsages();
		$this->assertArrayHasKey( 'Q885588#T', $usages );

		if ( $allowDataAccessInUserLanguage ) {
			$this->assertArrayHasKey( 'Q885588#L.' . $lang->getCode(), $usages );
		} else {
			$this->assertArrayHasKey( 'Q885588#L.de', $usages );
		}

		$this->assertSame( $allowDataAccessInUserLanguage, $cacheSplit );
	}

	/**
	 * @dataProvider allowDataAccessInUserLanguageProvider
	 */
	public function testFormatStatements( $allowDataAccessInUserLanguage ) {
		$cacheSplit = false;
		$this->setAllowDataAccessInUserLanguage( $allowDataAccessInUserLanguage );

		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary( $cacheSplit );

		$this->assertSame(
			[ '' ],
			$luaWikibaseLibrary->formatStatements( 'Q1', 'P65536', [] )
		);

		$this->assertSame( $allowDataAccessInUserLanguage, $cacheSplit );
	}

	public function testFormatStatements_noPropertyId() {
		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary();

		$this->assertSame(
			[ '' ],
			$luaWikibaseLibrary->formatStatements( 'Q1', 'father', [] )
		);
	}

	/**
	 * @dataProvider allowDataAccessInUserLanguageProvider
	 */
	public function testFormatStatements_usage( $allowDataAccessInUserLanguage ) {
		$cacheSplit = false;
		$this->setAllowDataAccessInUserLanguage( $allowDataAccessInUserLanguage );

		$lang = Language::factory( 'es' );
		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary( $cacheSplit, $lang );

		$this->assertSame(
			[ '<span><span>Q885588</span></span>' ],
			$luaWikibaseLibrary->formatStatements( 'Q32488', 'P456', null )
		);

		$usages = $luaWikibaseLibrary->getUsageAccumulator()->getUsages();
		$this->assertArrayHasKey( 'Q885588#T', $usages );

		if ( $allowDataAccessInUserLanguage ) {
			$this->assertArrayHasKey( 'Q885588#L.' . $lang->getCode(), $usages );
		} else {
			$this->assertArrayHasKey( 'Q885588#L.de', $usages );
		}

		$this->assertSame( $allowDataAccessInUserLanguage, $cacheSplit );
	}

	/**
	 * @param bool &$cacheSplit Will become true when the ParserCache has been split
	 * @param Language|null $userLang The user's language
	 *
	 * @return Scribunto_LuaWikibaseLibrary
	 */
	private function newScribuntoLuaWikibaseLibrary( &$cacheSplit = false, Language $userLang = null ) {
		/* @var $engine Scribunto_LuaEngine */
		$engine = $this->getEngine();
		$engine->load();

		$parserOptions = $engine->getParser()->getOptions();
		if ( $userLang ) {
			$parserOptions->setUserLang( $userLang );
		}
		$parserOptions->registerWatcher(
			function( $optionName ) use ( &$cacheSplit ) {
				$this->assertSame( 'userlang', $optionName );
				$cacheSplit = true;
			}
		);

		return new Scribunto_LuaWikibaseEntityLibrary( $engine );
	}

	/**
	 * @param bool $value
	 */
	private function setAllowDataAccessInUserLanguage( $value ) {
		$settings = WikibaseClient::getDefaultInstance()->getSettings();
		$settings->setSetting( 'allowDataAccessInUserLanguage', $value );
	}

}
