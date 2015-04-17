<?php

namespace Wikibase\Test;

use DataTypes\DataType;
use DataTypes\DataTypeFactory;
use Language;
use Title;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\PropertyInfoStore;
use Wikibase\Repo\EntityIdHtmlLinkFormatterFactory;
use Wikibase\Repo\Specials\SpecialListProperties;
use Wikibase\Test\SpecialPageTestBase;

/**
 * @covers Wikibase\Repo\Specials\SpecialListProperties
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group SpecialPage
 * @group WikibaseSpecialPage
 *
 * @licence GNU GPL v2+
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class SpecialListPropertiesTest extends SpecialPageTestBase {

	protected function setUp() {
		parent::setUp();

		$this->setMwGlobals( array(
			'wgContLang' => Language::factory( 'qqx' )
		) );
	}

	private function getDataTypeFactory() {
		$dataTypeFactory = DataTypeFactory::newFromTypes( array(
			new DataType( 'wikibase-item', 'wikibase-item', array() ),
			new DataType( 'string', 'string', array() ),
			new DataType( 'quantity', 'quantity', array() )
		) );

		return $dataTypeFactory;
	}

	private function getPropertyInfoStore() {
		$propertyInfoStore = new MockPropertyInfoStore();

		$propertyInfoStore->setPropertyInfo(
			new PropertyId( 'P123' ),
			array( PropertyInfoStore::KEY_DATA_TYPE => 'wikibase-item' )
		);

		$propertyInfoStore->setPropertyInfo(
			new PropertyId( 'P456' ),
			array( PropertyInfoStore::KEY_DATA_TYPE => 'wikibase-item' )
		);

		$propertyInfoStore->setPropertyInfo(
			new PropertyId( 'P789' ),
			array( PropertyInfoStore::KEY_DATA_TYPE => 'string' )
		);

		return $propertyInfoStore;
	}

	private function getTermLookup() {
		$termLookup = $this->getMock( 'Wikibase\Lib\Store\TermLookup' );
		$termLookup->expects( $this->any() )
			->method( 'getLabels' )
			->will( $this->returnCallback( function( PropertyId $propertyId ) {
				return array( 'en' => 'Property with label ' . $propertyId->getSerialization() );
			} ) );

		return $termLookup;
	}

	private function getTermBuffer() {
		$termBuffer = $this->getMock( 'Wikibase\Store\TermBuffer' );
		$termBuffer->expects( $this->any() )
			->method( 'prefetchTerms' );

		return $termBuffer;
	}

	private function getEntityTitleLookup() {
		$mock = $this->getMock( 'Wikibase\Lib\Store\EntityTitleLookup' );
		$mock->expects( $this->any() )
			->method( 'getTitleForId' )
			->will( $this->returnCallback(
				function ( EntityId $id ) {
					return Title::makeTitle( NS_MAIN, $id->getSerialization() );
				}
			) );

		return $mock;
	}

	protected function newSpecialPage() {
		$specialPage = new SpecialListProperties();

		$specialPage->initServices(
			$this->getDataTypeFactory(),
			$this->getPropertyInfoStore(),
			new LanguageFallbackChainFactory(),
			$this->getTermLookup(),
			$this->getTermBuffer(),
			new EntityIdHtmlLinkFormatterFactory( $this->getEntityTitleLookup(), new LanguageNameLookup() )
		);

		return $specialPage;
	}

	public function testExecute() {
		// This also tests that there is no fatal error, that the restriction handling is working
		// and doesn't block. That is, the default should let the user execute the page.
		list( $output, ) = $this->executeSpecialPage( '' );

		$this->assertInternalType( 'string', $output );
		$this->assertContains( 'wikibase-listproperties-summary', $output );
		$this->assertContains( 'wikibase-listproperties-legend', $output );

		list( $output, ) = $this->executeSpecialPage( 'quantity' );

		$this->assertContains( 'specialpage-empty', $output );

		// Use en-gb as language to test language fallback
		list( $output, ) = $this->executeSpecialPage( 'wikibase-item', null, 'en-gb' );

		$this->assertContains( 'Property with label P123', $output );
		$this->assertContains( 'Property with label P456', $output );
		$this->assertNotContains( 'P789', $output );

		list( $output, ) = $this->executeSpecialPage( 'string', null, 'en-gb' );

		$this->assertNotContains( 'P123', $output );
		$this->assertNotContains( 'P456', $output );
		$this->assertContains( 'Property with label P789', $output );
	}

}

