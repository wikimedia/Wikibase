<?php

namespace Wikibase\Test;
use \Wikibase\ItemObject as ItemObject;
use \Wikibase\Item as Item;

/**
 * Tests for the Wikibase\ItemObject class.
 * Some tests for this class are located in ItemMultilangTextsTest,
 * ItemNewEmptyTest and ItemNewFromArrayTest.
 *
 * @file
 * @since 0.1
 *
 * @ingroup Wikibase
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
class ItemObjectTest extends \MediaWikiTestCase {

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

	public function labelProvider() {
		return array(
			array( 'en', 'spam' ),
			array( 'en', 'spam', 'spam' ),
			array( 'de', 'foo bar baz' ),
		);
	}

	/**
	 * @dataProvider labelProvider
	 * @param string $languageCode
	 * @param string $labelText
	 * @param string $moarText
	 */
	public function testSetLabel( $languageCode, $labelText, $moarText = 'ohi there' ) {
		$item = ItemObject::newEmpty();

		$item->setLabel( $languageCode, $labelText );

		$this->assertEquals( $labelText, $item->getLabel( $languageCode ) );

		$item->setLabel( $languageCode, $moarText );

		$this->assertEquals( $moarText, $item->getLabel( $languageCode ) );
	}

	/**
	 * @dataProvider labelProvider
	 * @param string $languageCode
	 * @param string $labelText
	 * @param string $moarText
	 */
	public function testGetLabel( $languageCode, $labelText, $moarText = 'ohi there' ) {
		$item = ItemObject::newEmpty();

		$this->assertFalse( $item->getLabel( $languageCode ) );

		$item->setLabel( $languageCode, $labelText );

		$this->assertEquals( $labelText, $item->getLabel( $languageCode ) );
	}

	/**
	 * @dataProvider labelProvider
	 * @param string $languageCode
	 * @param string $labelText
	 * @param string $moarText
	 */
	public function testRemoveLabel( $languageCode, $labelText, $moarText = 'ohi there' ) {
		$item = ItemObject::newEmpty();
		$item->setLabel( $languageCode, $labelText );
		$item->removeLabel( $languageCode );
		$this->assertFalse( $item->getLabel( $languageCode ) );

		$item->setLabel( 'nl', 'sadefradtgsrduy' );
		$item->setLabel( $languageCode, $labelText );
		$item->removeLabel( array( $languageCode, 'nl' ) );
		$this->assertFalse( $item->getLabel( $languageCode ) );
		$this->assertFalse( $item->getLabel( 'nl' ) );
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
		$item = ItemObject::newEmpty();

		$this->assertTrue( $item->isEmpty() );

		$item->addAliases( 'en', array( 'ohi' ) );

		$this->assertFalse( $item->isEmpty() );

		$item = ItemObject::newEmpty();
		$item->addSiteLink( 'enwiki', 'Foobar' );

		$this->assertFalse( $item->isEmpty() );

		$item = ItemObject::newEmpty();
		$item->setDescription( 'en', 'o_O' );

		$this->assertFalse( $item->isEmpty() );

		$item = ItemObject::newEmpty();
		$item->setLabel( 'en', 'o_O' );

		$this->assertFalse( $item->isEmpty() );
	}

	public function testCopy() {
		$foo = ItemObject::newEmpty();
		$bar = $foo->copy();

		$this->assertInstanceOf( '\Wikibase\Item', $bar );
		$this->assertEquals( $foo, $bar );
		$this->assertFalse( $foo === $bar );
	}

	public function descriptionProvider() {
		return array(
			array( 'en', 'spam' ),
			array( 'en', 'spam', 'spam' ),
			array( 'de', 'foo bar baz' ),
		);
	}

	/**
	 * @dataProvider descriptionProvider
	 * @param string $languageCode
	 * @param string $labelText
	 * @param string $moarText
	 */
	public function testSetDescription( $languageCode, $labelText, $moarText = 'ohi there' ) {
		$item = ItemObject::newEmpty();

		$item->setDescription( $languageCode, $labelText );

		$this->assertEquals( $labelText, $item->getDescription( $languageCode ) );

		$item->setDescription( $languageCode, $moarText );

		$this->assertEquals( $moarText, $item->getDescription( $languageCode ) );
	}

	/**
	 * @dataProvider descriptionProvider
	 * @param string $languageCode
	 * @param string $labelText
	 * @param string $moarText
	 */
	public function testGetDescription( $languageCode, $labelText, $moarText = 'ohi there' ) {
		$item = ItemObject::newEmpty();

		$this->assertFalse( $item->getDescription( $languageCode ) );

		$item->setDescription( $languageCode, $labelText );

		$this->assertEquals( $labelText, $item->getDescription( $languageCode ) );
	}

	/**
	 * @dataProvider descriptionProvider
	 * @param string $languageCode
	 * @param string $labelText
	 * @param string $moarText
	 */
	public function testRemoveDescription( $languageCode, $labelText, $moarText = 'ohi there' ) {
		$item = ItemObject::newEmpty();
		$item->setDescription( $languageCode, $labelText );
		$item->removeDescription( $languageCode );
		$this->assertFalse( $item->getDescription( $languageCode ) );

		$item->setDescription( 'nl', 'sadefradtgsrduy' );
		$item->setDescription( $languageCode, $labelText );
		$item->removeDescription( array( $languageCode, 'nl' ) );
		$this->assertFalse( $item->getDescription( $languageCode ) );
		$this->assertFalse( $item->getDescription( 'nl' ) );
	}

	public function aliasesProvider() {
		return array(
			array( array(
				'en' => array( array( 'spam' ) )
			) ),
			array( array(
				'en' => array( array( 'foo', 'bar', 'baz' ) )
			) ),
			array( array(
				'en' => array( array( 'foo', 'bar' ), array( 'baz', 'spam' ) )
			) ),
			array( array(
				'en' => array( array( 'foo', 'bar', 'baz' ) ),
				'de' => array( array( 'foobar' ), array( 'baz' ) ),
			) ),
		);
	}

	/**
	 * @dataProvider aliasesProvider
	 */
	public function testAddAliases( array $aliasesLists ) {
		$item = ItemObject::newEmpty();

		foreach ( $aliasesLists as $langCode => $aliasesList ) {
			foreach ( $aliasesList as $aliases ) {
				$item->addAliases( $langCode, $aliases );
			}
		}

		foreach ( $aliasesLists as $langCode => $aliasesList ) {
			$expected = call_user_func_array( 'array_merge', $aliasesList );
			asort( $expected );

			$actual = $item->getAliases( $langCode );
			asort( $actual );

			$this->assertEquals( $expected, $actual );
		}
	}

	/**
	 * @dataProvider aliasesProvider
	 */
	public function testSetAliases( array $aliasesLists ) {
		$item = ItemObject::newEmpty();

		foreach ( $aliasesLists as $langCode => $aliasesList ) {
			foreach ( $aliasesList as $aliases ) {
				$item->setAliases( $langCode, $aliases );
			}
		}

		foreach ( $aliasesLists as $langCode => $aliasesList ) {
			$expected = array_pop( $aliasesList );
			asort( $aliasesList );

			$actual = $item->getAliases( $langCode );
			asort( $actual );

			$this->assertEquals( $expected, $actual );
		}
	}

	/**
	 * @dataProvider aliasesProvider
	 */
	public function testGetAliases( array $aliasesLists ) {
		$item = ItemObject::newEmpty();

		foreach ( $aliasesLists as $langCode => $aliasesList ) {
			$expected = array_shift( $aliasesList );
			$item->setAliases( $langCode, $expected );
			$actual = $item->getAliases( $langCode );
			
			asort( $expected );
			asort( $actual );

			$this->assertEquals( $expected, $actual );
		}
	}

	public function duplicateAliasesProvider() {
		return array(
			array( array(
				'en' => array( array( 'foo', 'bar', 'baz' ), array( 'foo', 'bar', 'baz' ) )
			) ),
			array( array(
				'en' => array( array( 'foo', 'bar', 'baz' ), array( 'foo', 'bar' ) )
			) ),
			array( array(
				'en' => array( array( 'foo', 'bar' ), array( 'foo', 'bar', 'baz' ) )
			) ),
			array( array(
				'en' => array( array( 'foo', 'bar' ), array( 'bar', 'baz' ) ),
				'de' => array( array(), array( 'foo' ) ),
				'nl' => array( array( 'foo' ), array() ),
			) ),
			array( array(
				'en' => array( array( 'foo', 'bar', 'baz' ), array( 'foo', 'bar', 'baz', 'foo', 'bar' ) )
			) ),
		);
	}

	/**
	 * @dataProvider duplicateAliasesProvider
	 */
	public function testRemoveAliases( array $aliasesLists ) {
		$item = ItemObject::newEmpty();

		foreach ( $aliasesLists as $langCode => $aliasesList ) {
			$aliases = array_shift( $aliasesList );
			$removedAliases =  array_shift( $aliasesList );

			$item->setAliases( $langCode, $aliases );
			$item->removeAliases( $langCode, $removedAliases );

			$expected = array_diff( $aliases, $removedAliases );
			$actual = $item->getAliases( $langCode );

			asort( $expected );
			asort( $actual );

			$this->assertEquals( $expected, $actual );
		}
	}

}
	