<?php

namespace Wikibase\Test;
use Wikibase\Item;
use Wikibase\SiteLink;

/**
 * Tests for the Wikibase\Item class.
 * Some tests for this class are located in ItemMultilangTextsTest,
 * ItemNewEmptyTest and ItemNewFromArrayTest.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @since 0.1
 *
 * @ingroup WikibaseLib
 * @ingroup Test
 *
 * @group Wikibase
 * @group WikibaseItem
 * @group WikibaseDataModel
 * @group WikibaseItemTest
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author John Erling Blad < jeblad@gmail.com >
 */
class ItemTest extends EntityTest {

	/**
	 * @see EntityTest::getNewEmpty
	 *
	 * @since 0.1
	 *
	 * @return \Wikibase\Item
	 */
	protected function getNewEmpty() {
		return Item::newEmpty();
	}

	/**
	 * @see   EntityTest::getNewFromArray
	 *
	 * @since 0.1
	 *
	 * @param array $data
	 *
	 * @return \Wikibase\Entity
	 */
	protected function getNewFromArray( array $data ) {
		return Item::newFromArray( $data );
	}

	public function testConstructor() {
		$instance = new Item( array() );

		$this->assertInstanceOf( 'Wikibase\Item', $instance );
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
			// getId()
			$this->assertTrue( is_null( $item->getId() ) || $item->getId() instanceof \Wikibase\EntityId );
			// getPrefixedId()
			$this->assertTrue(
				$item->getId() === null ? $item->getPrefixedId() === null : is_string( $item->getPrefixedId() )
			);
		}
	}

	public function testSetId() {
		/**
		 * @var \Wikibase\Item $item
		 */
		foreach ( TestItems::getItems() as $item ) {
			$item->setId( 42 );
			$this->assertEquals( 42, $item->getId()->getNumericId() );
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

		$item = Item::newEmpty();
		$item->addSiteLink( SiteLink::newFromText( 'enwiki', 'Foobar' ) );

		$this->assertFalse( $item->isEmpty() );
	}

	public function testClear() {
		parent::testClear(); //NOTE: we must test the Item implementation of the functionality already tested for Entity.

		$item = $this->getNewEmpty();

		$item->addSiteLink( SiteLink::newFromText( "enwiki", "Foozzle" ) );

		$item->clear();

		$this->assertEmpty( $item->getSiteLinks(), "sitelinks" );
		$this->assertTrue( $item->isEmpty() );
	}

	public function itemProvider() {
		$items = array();

		$items[] = Item::newEmpty();

		$item = Item::newEmpty();
		$item->setDescription( 'en', 'foo' );
		$items[] = $item;

		$item = Item::newEmpty();
		$item->setDescription( 'en', 'foo' );
		$item->setDescription( 'de', 'foo' );
		$item->setLabel( 'en', 'foo' );
		$item->setAliases( 'de', array( 'bar', 'baz' ) );
		$items[] = $item;

		/**
		 * @var Item $item;
		 */
		$item = $item->copy();
		$item->addClaim( new \Wikibase\Statement(
			new \Wikibase\PropertyNoValueSnak( new \Wikibase\EntityId( \Wikibase\Property::ENTITY_TYPE, 42 ) )
		) );
		$items[] = $item;

		return $this->arrayWrap( $items );
	}

	public function diffProvider() {
		$argLists = parent::diffProvider();

		// Addition of a sitelink
		$entity0 = $this->getNewEmpty();
		$entity1 = $this->getNewEmpty();
		$entity1->addSiteLink( SiteLink::newFromText( 'enwiki', 'Berlin' ) );

		$expected = new \Wikibase\EntityDiff( array(
			'links' => new \Diff\Diff( array(
				'enwiki' => new \Diff\DiffOpAdd( 'Berlin' ),
			), true ),
		) );

		$argLists[] = array( $entity0, $entity1, $expected );


		// Removal of a sitelink
		$entity0 = $this->getNewEmpty();
		$entity0->addSiteLink( SiteLink::newFromText( 'enwiki', 'Berlin' ) );
		$entity1 = $this->getNewEmpty();

		$expected = new \Wikibase\EntityDiff( array(
			'links' => new \Diff\Diff( array(
				'enwiki' => new \Diff\DiffOpRemove( 'Berlin' ),
			), true ),
		) );

		$argLists[] = array( $entity0, $entity1, $expected );


		// Modification of a sitelink
		$entity0 = $this->getNewEmpty();
		$entity0->addSiteLink( SiteLink::newFromText( 'enwiki', 'Berlin' ) );
		$entity1 = $this->getNewEmpty();
		$entity1->addSiteLink( SiteLink::newFromText( 'enwiki', 'Foobar' ) );

		$expected = new \Wikibase\EntityDiff( array(
			'links' => new \Diff\Diff( array(
				'enwiki' => new \Diff\DiffOpChange( 'Berlin', 'Foobar' ),
			), true ),
		) );

		$argLists[] = array( $entity0, $entity1, $expected );

		return $argLists;
	}

	public function patchProvider() {
		$argLists = parent::patchProvider();

		// Addition of a sitelink
		$source = $this->getNewEmpty();
		$patch = new \Wikibase\ItemDiff( array(
			'links' => new \Diff\Diff( array( 'enwiki' => new \Diff\DiffOpAdd( 'Berlin' ) ), true )
		) );
		$expected = clone $source;
		$expected->addSiteLink( SiteLink::newFromText( 'enwiki', 'Berlin' ) );

		$argLists[] = array( $source, $patch, $expected );


		// Retaining of a sitelink
		$source = clone $expected;
		$patch = new \Wikibase\ItemDiff();
		$expected = clone $source;

		$argLists[] = array( $source, $patch, $expected );


		// Modification of a sitelink
		$source = clone $expected;
		$patch = new \Wikibase\ItemDiff( array(
			'links' => new \Diff\Diff( array( 'enwiki' => new \Diff\DiffOpChange( 'Berlin', 'Foobar' ) ), true )
		) );
		$expected = $this->getNewEmpty();
		$expected->addSiteLink( SiteLink::newFromText( 'enwiki', 'Foobar' ) );

		$argLists[] = array( $source, $patch, $expected );


		// Removal of a sitelink
		$source = clone $expected;
		$patch = new \Wikibase\ItemDiff( array(
			'links' => new \Diff\Diff( array( 'enwiki' => new \Diff\DiffOpRemove( 'Foobar' ) ), true )
		) );
		$expected = $this->getNewEmpty();

		$argLists[] = array( $source, $patch, $expected );

		return $argLists;
	}

}
