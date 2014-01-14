<?php

namespace Wikibase\Client\Scribunto\Test;

use Language;
use Wikibase\Test\MockRepository;
use ValueFormatters\FormatterOptions;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\Lib\EntityIdFormatter;
use Wikibase\Client\Scribunto\WikibaseLuaEntityBindings;
use Wikibase\EntityLookup;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\SnakFactory;

/**
 * @covers Wikibase\Client\Scribunto\WikibaseLuaEntityBindings;
 *
 * @since 0.5
 *
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseScribunto
 *
 * @licence GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 */
class WikibaseLuaEntityBindingsTest extends \PHPUnit_Framework_TestCase {

	public function testConstructor() {
		$wikibaseLibrary = $this->getWikibaseLibraryImplementation();
		$this->assertInstanceOf(
			'Wikibase\Client\Scribunto\WikibaseLuaEntityBindings',
			$wikibaseLibrary
		);
	}

	private function getWikibaseLibraryImplementation( EntityLookup $entityLookup = null ) {
		$language = new Language( 'en' );

		return new WikibaseLuaEntityBindings(
			$this->newSnakFormatterMock(),
			new BasicEntityIdParser(),
			$entityLookup ? $entityLookup : new MockRepository(),
			new EntityIdFormatter( new FormatterOptions() ),
			'enwiki',
			$language // language
		);
	}

	/**
	 * @return Item
	 */
	private function getItemMock() {
		$snakFactory = new SnakFactory();
		$snak = $snakFactory->newSnak(
			new PropertyId( 'P123456' ),
			'somevalue'
		);
		$claim = new Claim( $snak );
		$claim->setGuid( '1' );

		$item = $this->getMockBuilder( 'Wikibase\DataModel\Entity\Item' )
			->disableOriginalConstructor()
			->getMock();

		$item->expects( $this->any() )->method( 'getClaims' )
			->will( $this->returnValue( array( $claim ) ) );

		return $item;
	}

	/**
	 * @param Entity $entity
	 *
	 * @return EntityLookup
	 */
	private function getEntityLookupMock( Entity $entity = null ) {
		$entityLookup = $this->getMock( 'Wikibase\EntityLookup' );

		$entityLookup->expects( $this->any() )->method( 'getEntity' )
			->will( $this->returnValue( $entity ) );

		return $entityLookup;
	}

	/**
	 * @return SnakFormatter
	 */
	private function newSnakFormatterMock() {
		$snakFormatter = $this->getMock( 'Wikibase\Lib\SnakFormatter' );

		$snakFormatter->expects( $this->any() )->method( 'formatSnak' )
			->will( $this->returnValue( 'Snak snak snak' ) );

		return $snakFormatter;
	}


	public function testRenderClaimsByPropertyId() {
		$item = $this->getItemMock();

		$entityLookup = $this->getEntityLookupMock( $item );
		$wikibaseLibrary = $this->getWikibaseLibraryImplementation( $entityLookup );
		$ret = $wikibaseLibrary->renderClaimsByPropertyId( 'Q1', 'P123456' );

		$this->assertSame( array( 'Snak snak snak' ), $ret );
	}

	public function testRenderClaimsByPropertyIdNoEntity() {
		$entityLookup = $this->getEntityLookupMock();

		$wikibaseLibrary = $this->getWikibaseLibraryImplementation( $entityLookup );
		$ret = $wikibaseLibrary->renderClaimsByPropertyId( 'Q1', 'P123456' );

		$this->assertSame( array( '' ), $ret );
	}

	public function testGetGlobalSiteId() {
		$wikibaseLibrary = $this->getWikibaseLibraryImplementation();
		$this->assertEquals( array( 'enwiki' ), $wikibaseLibrary->getGlobalSiteId() );
	}
}
