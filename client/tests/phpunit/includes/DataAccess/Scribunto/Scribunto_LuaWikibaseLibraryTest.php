<?php

namespace Wikibase\Client\Tests\DataAccess\Scribunto;

use Language;
use Parser;
use ParserOptions;
use Scribunto;
use Title;
use User;
use Wikibase\Client\DataAccess\Scribunto\Scribunto_LuaWikibaseLibrary;
use Wikibase\Client\WikibaseClient;

/**
 * @covers Wikibase\Client\DataAccess\Scribunto\Scribunto_LuaWikibaseLibrary
 *
 * @group WikibaseScribunto
 * @group WikibaseIntegration
 * @group WikibaseClient
 * @group Wikibase
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Marius Hoch < hoo@online.de >
 */
class Scribunto_LuaWikibaseLibraryTest extends Scribunto_LuaWikibaseLibraryTestCase {

	protected static $moduleName = 'LuaWikibaseLibraryTests';

	/**
	 * @var bool
	 */
	private $oldAllowDataAccessInUserLanguage;

	protected function getTestModules() {
		return parent::getTestModules() + array(
			'LuaWikibaseLibraryTests' => __DIR__ . '/LuaWikibaseLibraryTests.lua',
		);
	}

	/**
	 * @return int
	 */
	protected static function getEntityAccessLimit() {
		// testGetEntity_entityAccessLimitExceeded needs this to be 2
		return 2;
	}

	protected function setUp() {
		parent::setUp();

		$settings = WikibaseClient::getDefaultInstance()->getSettings();
		$this->oldAllowDataAccessInUserLanguage = $settings->getSetting( 'allowDataAccessInUserLanguage' );
		$this->setAllowDataAccessInUserLanguage( false );
	}

	protected function tearDown() {
		parent::tearDown();

		$this->setAllowDataAccessInUserLanguage( $this->oldAllowDataAccessInUserLanguage );
	}

	public function allowDataAccessInUserLanguageProvider() {
		return array(
			array( true ),
			array( false ),
		);
	}

	public function testConstructor() {
		$engine = Scribunto::newDefaultEngine( array() );
		$luaWikibaseLibrary = new Scribunto_LuaWikibaseLibrary( $engine );
		$this->assertInstanceOf(
			'Wikibase\Client\DataAccess\Scribunto\Scribunto_LuaWikibaseLibrary',
			$luaWikibaseLibrary
		);
	}

	public function testRegister() {
		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary();
		$package = $luaWikibaseLibrary->register();

		$this->assertInternalType( 'array', $package );
		$this->assertArrayHasKey( 'setupInterface', $package );
		$this->assertInstanceOf(
			'Scribunto_LuaStandaloneInterpreterFunction',
			$package['setupInterface']
		);
	}

	/**
	 * @dataProvider allowDataAccessInUserLanguageProvider
	 */
	public function testGetEntity( $allowDataAccessInUserLanguage ) {
		$this->setAllowDataAccessInUserLanguage( $allowDataAccessInUserLanguage );
		$cacheSplit = false;

		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary( $cacheSplit );
		$entity = $luaWikibaseLibrary->getEntity( 'Q888' );
		$this->assertEquals( array( null ), $entity );

		$this->assertSame( $allowDataAccessInUserLanguage, $cacheSplit );
	}

	public function testGetEntity_hasLanguageFallback() {
		$this->setMwGlobals( array(
			'wgContLang' => Language::factory( 'ku-arab' )
		) );

		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary();
		$entityArray = $luaWikibaseLibrary->getEntity( 'Q885588' );

		$expected = array(
			array(
				'id' => 'Q885588',
				'type' => 'item',
				'labels' => array(
					'ku-latn' => array(
						'language' => 'ku-latn',
						'value' => 'Pisîk'
					),
					'ku-arab' => array(
						'language' => 'ku-arab',
						'value' => 'پسیک',
						'source-language' => 'ku-latn',
					)
				),
				'schemaVersion' => 2,
				'descriptions' => array( 'de' =>
					array(
						'language' => 'de',
						'value' => 'Description of Q885588'
					)
				)
			)
		);

		$this->assertEquals( $expected, $entityArray, 'getEntity' );

		$label = $luaWikibaseLibrary->getLabel( 'Q885588' );
		$this->assertEquals( array( 'پسیک' ), $label, 'getLabel' );

		// All languages in the fallback chain for 'ku-arab' count as "used".
		$usage = $luaWikibaseLibrary->getUsageAccumulator()->getUsages();
		$this->assertArrayHasKey( 'Q885588#L.ku', $usage );
		$this->assertArrayHasKey( 'Q885588#L.ku-arab', $usage );
		$this->assertArrayHasKey( 'Q885588#L.ku-latn', $usage );
	}

	public function testGetEntityInvalidIdType() {
		$this->setExpectedException( 'ScribuntoException' );
		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary();
		$luaWikibaseLibrary->getEntity( array() );
	}

	public function testGetEntityInvalidEntityId() {
		$this->setExpectedException( 'ScribuntoException' );
		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary();
		$luaWikibaseLibrary->getEntity( 'X888' );
	}

	public function testGetEntity_entityAccessLimitExceeded() {
		$this->setExpectedException( 'ScribuntoException' );

		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary();

		$luaWikibaseLibrary->getEntity( 'Q32487' );
		$luaWikibaseLibrary->getEntity( 'Q32488' );
		$luaWikibaseLibrary->getEntity( 'Q199024' );
	}

	public function testGetEntityId() {
		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary();
		$entityId = $luaWikibaseLibrary->getEntityId( 'CanHazKitten123' );
		$this->assertEquals( array( null ), $entityId );
	}

	/**
	 * @dataProvider allowDataAccessInUserLanguageProvider
	 */
	public function testGetLabel( $allowDataAccessInUserLanguage ) {
		$user = new User();
		$user->setOption( 'language', 'de' );

		$this->setMwGlobals( array(
			'wgContLang' => Language::factory( 'en' ),
			'wgUser' => $user
		) );

		$this->setAllowDataAccessInUserLanguage( $allowDataAccessInUserLanguage );
		$cacheSplit = false;

		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary( $cacheSplit );
		$label = $luaWikibaseLibrary->getLabel( 'Q32487' );

		if ( $allowDataAccessInUserLanguage ) {
			$this->assertSame( 'Lua Test Item', $label[0] );
		} else {
			$this->assertSame( 'Test all the code paths', $label[0] );
		}

		$this->assertSame( $allowDataAccessInUserLanguage, $cacheSplit );
	}

	/**
	 * @dataProvider allowDataAccessInUserLanguageProvider
	 */
	public function testRenderSnak( $allowDataAccessInUserLanguage ) {
		$this->setAllowDataAccessInUserLanguage( $allowDataAccessInUserLanguage );
		$cacheSplit = false;

		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary( $cacheSplit );
		$entityArr = $luaWikibaseLibrary->getEntity( 'Q32488' );

		$snak = $entityArr[0]['claims']['P456'][1]['mainsnak'];
		$this->assertSame(
			array( 'Q885588' ),
			$luaWikibaseLibrary->renderSnak( $snak )
		);

		// When rendering the item reference in the snak,
		// track table and title usage.
		$usage = $luaWikibaseLibrary->getUsageAccumulator()->getUsages();

		if ( $allowDataAccessInUserLanguage ) {
			global $wgUser;

			$userLang = $wgUser->getOption( 'language' );

			$this->assertArrayHasKey( 'Q885588#L.' . $userLang, $usage );
		} else {
			$this->assertArrayHasKey( 'Q885588#L.de', $usage );
		}

		$this->assertArrayHasKey( 'Q885588#T', $usage );

		$this->assertSame( $allowDataAccessInUserLanguage, $cacheSplit );
	}

	public function testRenderSnak_invalidSerialization() {
		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary();

		$this->setExpectedException( 'ScribuntoException' );
		$luaWikibaseLibrary->renderSnak( array( 'a' => 'b' ) );
	}

	/**
	 * @dataProvider allowDataAccessInUserLanguageProvider
	 */
	public function testRenderSnaks( $allowDataAccessInUserLanguage ) {
		$this->setAllowDataAccessInUserLanguage( $allowDataAccessInUserLanguage );
		$cacheSplit = false;

		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary( $cacheSplit );
		$entityArr = $luaWikibaseLibrary->getEntity( 'Q32487' );

		$snaks = $entityArr[0]['claims']['P342'][1]['qualifiers'];
		$expected = array( 'A qualifier Snak, Moar qualifiers' );
		if ( $allowDataAccessInUserLanguage ) {
			global $wgUser;

			$lang = Language::factory( $wgUser->getOption( 'language' ) );
			$expected = array(
				$lang->commaList( array( 'A qualifier Snak', 'Moar qualifiers' ) )
			);
		}

		$this->assertSame( $expected, $luaWikibaseLibrary->renderSnaks( $snaks ) );
		$this->assertSame( $allowDataAccessInUserLanguage, $cacheSplit );
	}

	public function testRenderSnaks_invalidSerialization() {
		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary();

		$this->setExpectedException( 'ScribuntoException' );
		$luaWikibaseLibrary->renderSnaks( array( 'a' => 'b' ) );
	}

	public function testResolvePropertyId() {
		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary();

		$this->assertSame(
			array( 'P342' ),
			$luaWikibaseLibrary->resolvePropertyId( 'LuaTestStringProperty' )
		);
	}

	public function testResolvePropertyId_propertyIdGiven() {
		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary();

		$this->assertSame(
			array( 'P342' ),
			$luaWikibaseLibrary->resolvePropertyId( 'P342' )
		);
	}

	public function testResolvePropertyId_labelNotFound() {
		$luaWikibaseLibrary = $this->newScribuntoLuaWikibaseLibrary();

		$this->assertSame(
			array( null ),
			$luaWikibaseLibrary->resolvePropertyId( 'foo' )
		);
	}

	/**
	 * @param bool &$cacheSplit Will become true when the ParserCache has been split
	 *
	 * @return Scribunto_LuaWikibaseLibrary
	 */
	private function newScribuntoLuaWikibaseLibrary( &$cacheSplit = false ) {
		$title = Title::newFromText( 'Whatever' );
		$parserOptions = new ParserOptions();

		$parser = new Parser();
		$parser->startExternalParse(
			$title,
			$parserOptions,
			Parser::OT_HTML
		);

		$parserOptions->registerWatcher(
			function( $optionName ) use ( &$cacheSplit ) {
				$this->assertSame( 'userlang', $optionName );
				$cacheSplit = true;
			}
		);

		$engine = Scribunto::newDefaultEngine( array(
			'parser' => $parser,
			'title' => $title
		) );
		$engine->load();

		return new Scribunto_LuaWikibaseLibrary( $engine );
	}

	/**
	 * @param bool $value
	 */
	private function setAllowDataAccessInUserLanguage( $value ) {
		$settings = WikibaseClient::getDefaultInstance()->getSettings();
		$settings->setSetting( 'allowDataAccessInUserLanguage', $value );
	}

}
