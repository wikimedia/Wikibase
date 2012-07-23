<?php

namespace Wikibase\Test;
use \Wikibase\ItemObject as ItemObject;
use \Wikibase\Item as Item;
use \Wikibase\SiteLink as SiteLink;

/**
 * Tests for the Wikibase\ItemObject class.
 * Some tests for this class are located in ItemMultilangTextsTest,
 * ItemNewEmptyTest and ItemNewFromArrayTest.
 *
 * @file
 * @since 0.1
 *
 * @ingroup WikibaseLib
 * @ingroup Test
 *
 * @group Wikibase
 * @group WikibaseItem
 * @group WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author John Erling Blad < jeblad@gmail.com >
 */
class ItemObjectTest extends EntityObjectTest {

	/**
	 * @see EntityObjectTest::getNewEmpty
	 *
	 * @since 0.1
	 *
	 * @return \Wikibase\Item
	 */
	protected function getNewEmpty() {
		return ItemObject::newEmpty();
	}

	public function testConstructor() {
		$instance = new ItemObject( array() );

		$this->assertInstanceOf( 'Wikibase\Item', $instance );

		$exception = null;
		try { $instance = new ItemObject( 'Exception throws you!' ); } catch ( \Exception $exception ){}
		$this->assertInstanceOf( '\Exception', $exception );
	}

	public function testToArray() {
		/**
		 * @var \Wikibase\Item $item
		 */
		foreach ( TestItems::getItems() as $item ) {
			$this->assertInternalType( 'array', $item->toArray() );
		}
	}

	public function testGetId() {
		/**
		 * @var \Wikibase\Item $item
		 */
		foreach ( TestItems::getItems() as $item ) {
			$this->assertTrue( is_null( $item->getId() ) || is_integer( $item->getId() ) );
		}
	}

	public function testSetId() {
		/**
		 * @var \Wikibase\Item $item
		 */
		foreach ( TestItems::getItems() as $item ) {
			$item->setId( 42 );
			$this->assertEquals( 42, $item->getId() );
		}
	}

	public function testGetSiteLinks() {
		/**
		 * @var \Wikibase\Item $item
		 */
		foreach ( TestItems::getItems() as $item ) {
			$links = $item->getSiteLinks();
			$this->assertInternalType( 'array', $links );

			foreach ( $links as $link ) {
				$this->assertInstanceOf( '\Wikibase\SiteLink', $link );
			}
		}
	}

	public function testIsEmpty() {
		parent::testIsEmpty();

		$item = ItemObject::newEmpty();
		$item->addSiteLink( SiteLink::newFromText( 'enwiki', 'Foobar' ) );

		$this->assertFalse( $item->isEmpty() );
	}

	public function testCopy() {
		$foo = ItemObject::newEmpty();
		$bar = $foo->copy();

		$this->assertInstanceOf( '\Wikibase\Item', $bar );
		$this->assertEquals( $foo, $bar );
		$this->assertFalse( $foo === $bar );
	}

}
	