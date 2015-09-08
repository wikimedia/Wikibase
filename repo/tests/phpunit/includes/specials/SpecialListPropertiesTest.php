<?php

namespace Wikibase\Test;

use DataTypes\DataTypeFactory;
use Language;
use Title;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\PropertyInfoStore;
use Wikibase\Repo\EntityIdHtmlLinkFormatterFactory;
use Wikibase\Repo\Specials\SpecialListProperties;
use Wikibase\Store\BufferingTermLookup;

/**
 * @covers Wikibase\Repo\Specials\SpecialListProperties
 *
 * @group Database
 * @group SpecialPage
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseSpecialPage
 *
 * @licence GNU GPL v2+
 * @author Bene* < benestar.wikimedia@gmail.com >
 * @author Adam Shorland
 */
class SpecialListPropertiesTest extends SpecialPageTestBase {

	protected function setUp() {
		parent::setUp();

		$this->setMwGlobals( array(
			'wgContLang' => Language::factory( 'qqx' )
		) );
	}

	private function getDataTypeFactory() {
		$dataTypeFactory = new DataTypeFactory( array(
			'wikibase-item' => 'wikibase-item',
			'string' => 'string',
			'quantity' => 'quantity',
		) );

		return $dataTypeFactory;
	}

	private function getPropertyInfoStore() {
		$propertyInfoStore = new MockPropertyInfoStore();

		$propertyInfoStore->setPropertyInfo(
			new PropertyId( 'P789' ),
			array( PropertyInfoStore::KEY_DATA_TYPE => 'string' )
		);

		$propertyInfoStore->setPropertyInfo(
			new PropertyId( 'P456' ),
			array( PropertyInfoStore::KEY_DATA_TYPE => 'wikibase-item' )
		);

		$propertyInfoStore->setPropertyInfo(
			new PropertyId( 'P123' ),
			array( PropertyInfoStore::KEY_DATA_TYPE => 'wikibase-item' )
		);

		return $propertyInfoStore;
	}

	/**
	 * @return BufferingTermLookup
	 */
	private function getBufferingTermLookup() {
		$lookup = $this->getMockBuilder( 'Wikibase\Store\BufferingTermLookup' )
			->disableOriginalConstructor()
			->getMock();
		$lookup->expects( $this->any() )
			->method( 'prefetchTerms' );
		$lookup->expects( $this->any() )
			->method( 'getLabels' )
			->will( $this->returnCallback( function( PropertyId $propertyId ) {
				return array( 'en' => 'Property with label ' . $propertyId->getSerialization() );
			} ) );
		return $lookup;
	}

	/**
	 * @return EntityTitleLookup
	 */
	private function getEntityTitleLookup() {
		$entityTitleLookup = $this->getMock( 'Wikibase\Lib\Store\EntityTitleLookup' );
		$entityTitleLookup->expects( $this->any() )
			->method( 'getTitleForId' )
			->will( $this->returnCallback(
				function ( EntityId $id ) {
					return Title::makeTitle( NS_MAIN, $id->getSerialization() );
				}
			) );

		return $entityTitleLookup;
	}

	protected function newSpecialPage() {
		$specialPage = new SpecialListProperties();

		$specialPage->initServices(
			$this->getDataTypeFactory(),
			$this->getPropertyInfoStore(),
			new EntityIdHtmlLinkFormatterFactory( $this->getEntityTitleLookup(), new LanguageNameLookup() ),
			new LanguageFallbackChainFactory(),
			$this->getEntityTitleLookup(),
			$this->getBufferingTermLookup()
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
		$this->assertNotContains( 'wikibase-listproperties-invalid-datatype', $output );
		$this->assertRegExp( '/P123.*P456.*P789/', $output ); // order is relevant
	}

	public function testOffsetAndLimit() {
		$request = new \FauxRequest( array( 'limit' => '1', 'offset' => '1' ) );
		list( $output, ) = $this->executeSpecialPage( '', $request );

		$this->assertNotContains( 'P123', $output );
		$this->assertContains( 'P456', $output );
		$this->assertNotContains( 'P789', $output );
	}

	public function testExecute_empty() {
		list( $output, ) = $this->executeSpecialPage( 'quantity' );

		$this->assertContains( 'specialpage-empty', $output );
	}

	public function testExecute_error() {
		list( $output, ) = $this->executeSpecialPage( 'test<>' );

		$this->assertContains( 'wikibase-listproperties-invalid-datatype', $output );
		$this->assertContains( 'test&lt;&gt;', $output );
	}

	public function testExecute_wikibase_item() {
		// Use en-gb as language to test language fallback
		list( $output, ) = $this->executeSpecialPage( 'wikibase-item', null, 'en-gb' );

		$this->assertContains( 'Property with label P123', $output );
		$this->assertContains( 'Property with label P456', $output );
		$this->assertNotContains( 'P789', $output );
	}

	public function testExecute_string() {
		list( $output, ) = $this->executeSpecialPage( 'string', null, 'en-gb' );

		$this->assertNotContains( 'P123', $output );
		$this->assertNotContains( 'P456', $output );
		$this->assertContains( 'Property with label P789', $output );
	}

}
