<?php

namespace Wikibase\Test;
use Wikibase\HashArray;
use Wikibase\Hashable;

/**
 * Tests for the Wikibase\HashArray class.
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
 * @ingroup Wikibase
 * @ingroup Test
 *
 * @group Wikibase
 * @group WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
abstract class HashArrayTest extends \GenericArrayObjectTest {

	public abstract function constructorProvider();

	public function instanceProvider() {
		$class = $this->getInstanceClass();

		$instances = array();

		foreach ( $this->constructorProvider() as $args ) {
			$instances[] = array( new $class( array_key_exists( 0, $args ) ? $args[0] : null ) );
		}

		return $instances;
	}

	/**
	 * @dataProvider instanceProvider
	 *
	 * @param \Wikibase\HashArray $array
	 */
	public function testHasElement( HashArray $array ) {
		/**
		 * @var Hashable $hashable
		 */
		foreach ( iterator_to_array( $array ) as $hashable ) {
			$this->assertTrue( $array->hasElement( $hashable ) );
			$this->assertTrue( $array->hasElementHash( $hashable->getHash() ) );
			$array->removeElement( $hashable );
			$this->assertFalse( $array->hasElement( $hashable ) );
			$this->assertFalse( $array->hasElementHash( $hashable->getHash() ) );
		}

		$this->assertTrue( true );
	}

	/**
	 * @dataProvider instanceProvider
	 *
	 * @param \Wikibase\HashArray $array
	 */
	public function testRemoveElement( HashArray $array ) {
		$elementCount = $array->count();

		/**
		 * @var Hashable $element
		 */
		foreach ( iterator_to_array( $array ) as $element ) {
			$this->assertTrue( $array->hasElement( $element ) );

			if ( $elementCount % 2 === 0 ) {
				$array->removeElement( $element );
			}
			else {
				$array->removeByElementHash( $element->getHash() );
			}

			$this->assertFalse( $array->hasElement( $element ) );
			$this->assertEquals( --$elementCount, $array->count() );
		}

		$element = new \Wikibase\PropertyNoValueSnak( 42 );

		$array->removeElement( $element );
		$array->removeByElementHash( $element->getHash() );

		$this->assertTrue( true );
	}

	/**
	 * @dataProvider instanceProvider
	 *
	 * @param \Wikibase\HashArray $array
	 */
	public function testAddElement( HashArray $array ) {
		$elementCount = $array->count();

		$elements = $this->elementInstancesProvider();
		$element = array_shift( $elements );
		$element = $element[0][0];

		if ( !$array->hasElement( $element ) ) {
			++$elementCount;
		}

		$this->assertEquals( !$array->hasElement( $element ), $array->addElement( $element ) );

		$this->assertEquals( $elementCount, $array->count() );

		$this->assertFalse( $array->addElement( $element ) );

		$this->assertEquals( $elementCount, $array->count() );
	}

	/**
	 * @dataProvider instanceProvider
	 *
	 * @param \Wikibase\HashArray $array
	 */
	public function testGetHash( HashArray $array ) {
		$hash = $array->getHash();

		$this->assertEquals( $hash, $array->getHash() );

		$elements = $this->elementInstancesProvider();
		$element = array_shift( $elements );
		$element = $element[0][0];

		$hasElement = $array->hasElement( $element );
		$array->addElement( $element );

		$this->assertTrue( ( $hash === $array->getHash() ) === $hasElement );
	}

}
