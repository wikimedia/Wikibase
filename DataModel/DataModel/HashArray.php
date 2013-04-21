<?php

namespace Wikibase;
use Hashable;
use GenericArrayObject;

/**
 * Generic array object with lookups based on hashes of the elements.
 *
 * Elements need to implement Hashable.
 *
 * Note that by default the getHash method uses @see MapValueHashesr
 * which returns a hash based on the contents of the list, regardless
 * of order and keys.
 *
 * Also note that if the Hashable elements are mutable, any modifications
 * made to them via their mutator methods will not cause an update of
 * their associated hash in this array.
 *
 * When acceptDuplicates is set to true, multiple elements with the same
 * hash can reside in the HashArray. Lookup by such a non-unique hash will
 * return only the first element and deletion will also delete only
 * the first such element.
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
 * @since 0.1
 *
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
abstract class HashArray extends GenericArrayObject implements \Hashable, \Comparable {

	/**
	 * Maps element hashes to their offsets.
	 *
	 * @since 0.1
	 *
	 * @var array [ element hash (string) => array [ element offset (string|int) ] | element offset (string|int) ]
	 */
	protected $offsetHashes = array();

	/**
	 * If duplicate values (based on hash) should be accepted or not.
	 *
	 * @since 0.3
	 *
	 * @var boolean
	 */
	protected $acceptDuplicates = false;

	/**
	 * @see GenericArrayObject::preSetElement
	 *
	 * @since 0.1
	 *
	 * @param int|string $index
	 * @param Hashable $hashable
	 *
	 * @return boolean
	 */
	protected function preSetElement( $index, $hashable ) {
		$hash = $hashable->getHash();

		$hasHash = $this->hasElementHash( $hash );

		if ( !$this->acceptDuplicates && $hasHash ) {
			return false;
		}
		else {
			if ( $hasHash ) {
				if ( !is_array( $this->offsetHashes[$hash] ) ) {
					$this->offsetHashes[$hash] = array( $this->offsetHashes[$hash] );
				}

				$this->offsetHashes[$hash][] = $index;
			}
			else {
				$this->offsetHashes[$hash] = $index;
			}

			return true;
		}
	}

	/**
	 * Returns if there is an element with the provided hash.
	 *
	 * @since 0.1
	 *
	 * @param string $elementHash
	 *
	 * @return boolean
	 */
	public function hasElementHash( $elementHash ) {
		return array_key_exists( $elementHash, $this->offsetHashes );
	}

	/**
	 * Returns if there is an element with the same hash as the provided element in the list.
	 *
	 * @since 0.1
	 *
	 * @param Hashable $element
	 *
	 * @return boolean
	 */
	public function hasElement( Hashable $element ) {
		return $this->hasElementHash( $element->getHash() );
	}

	/**
	 * Removes the element with the hash of the provided element, if there is such an element in the list.
	 *
	 * @since 0.1
	 *
	 * @param Hashable $element
	 */
	public function removeElement( Hashable $element ) {
		$this->removeByElementHash( $element->getHash() );
	}

	/**
	 * Removes the element with the provided hash, if there is such an element in the list.
	 *
	 * @since 0.1
	 *
	 * @param string $elementHash
	 */
	public function removeByElementHash( $elementHash ) {
		if ( $this->hasElementHash( $elementHash ) ) {
			$offset = $this->offsetHashes[$elementHash];

			if ( is_array( $offset ) ) {
				$offset = reset( $offset );
			}

			$this->offsetUnset( $offset );
		}
	}

	/**
	 * Adds the provided element to the list if there is no element with the same hash yet.
	 *
	 * @since 0.1
	 *
	 * @param Hashable $element
	 *
	 * @return boolean Indicates if the element was added or not.
	 */
	public function addElement( Hashable $element ) {
		// TODO: this duplicates logic of preSetElement
		// Probably best update setElement in GenericArrayObject to return boolean it got from preSetElement
		$append = $this->acceptDuplicates || !$this->hasElementHash( $element->getHash() );

		if ( $append ) {
			$this->append( $element );
		}

		return $append;
	}

	/**
	 * Returns the element with the provided hash or false if there is no such element.
	 *
	 * @since 0.1
	 *
	 * @param string $elementHash
	 *
	 * @return mixed|bool
	 */
	public function getByElementHash( $elementHash ) {
		if ( $this->hasElementHash( $elementHash ) ) {
			$offset = $this->offsetHashes[$elementHash];

			if ( is_array( $offset ) ) {
				$offset = reset( $offset );
			}

			return $this->offsetGet( $offset );
		}
		else {
			return false;
		}
	}

	/**
	 * @see ArrayObject::offsetUnset
	 *
	 * @since 0.1
	 *
	 * @param mixed $index
	 */
	public function offsetUnset( $index ) {
		if ( $this->offsetExists( $index ) ) {
			/**
			 * @var Hashable $element
			 */
			$element = $this->offsetGet( $index );

			$hash = $element->getHash();

			if ( array_key_exists( $hash, $this->offsetHashes )
				&& is_array( $this->offsetHashes[$hash] )
				&& count( $this->offsetHashes[$hash] ) > 1 ) {

				$this->offsetHashes[$hash] = array_filter(
					$this->offsetHashes[$hash],
					function( $value ) use ( $index ) {
						return $value !== $index;
					}
				);
			}
			else {
				unset( $this->offsetHashes[$hash] );
			}

			parent::offsetUnset( $index );
		}
	}

	/**
	 * @see Hashable::getHash
	 *
	 * The hash is purely valuer based. Order of the elements in the array is not held into account.
	 *
	 * @since 0.1
	 *
	 * @internal param MapHasher $mapHasher
	 *
	 * @return string
	 */
	public function getHash() {
		// We cannot have this as optional arg, because then we're no longer
		// implementing the Hashable interface properly according to PHP...
		$args = func_get_args();

		/**
		 * @var MapHasher $hasher
		 */
		$hasher = array_key_exists( 0, $args ) ? $args[0] : new MapValueHasher();

		return $hasher->hash( $this );
	}

	/**
	 * @see Comparable::equals
	 *
	 * The comparison is done purely value based, ignoring the order of the elements in the array.
	 *
	 * @since 0.3
	 *
	 * @param mixed $mixed
	 *
	 * @return boolean
	 */
	public function equals( $mixed ) {
		return is_object( $mixed )
			&& $mixed instanceof HashArray
			&& $this->getHash() === $mixed->getHash();
	}

	/**
	 * Removes duplicates bases on hash value.
	 *
	 * @since 0.3
	 */
	public function removeDuplicates() {
		$knownHashes = array();

		/**
		 * @var Hashable $hashable
		 */
		foreach ( iterator_to_array( $this ) as $hashable ) {
			$hash = $hashable->getHash();

			if ( in_array( $hash, $knownHashes ) ) {
				$this->removeByElementHash( $hash );
			}
			else {
				$knownHashes[] = $hash;
			}
		}
	}

	/**
	 * Returns if the hash indices are up to date.
	 * For an HashArray with immutable objects this should always be the case.
	 * For one with mutable objects it's the responsibility of the mutating code
	 * to keep the indices up to date (see class documentation) and thus possible
	 * this has not been done since the last update, thus causing a state where
	 * one or more indices are out of date.
	 *
	 * @since 0.4
	 *
	 * @return boolean
	 */
	public function indicesAreUpToDate() {
		foreach ( $this->offsetHashes as $hash => $offsets ) {
			$offsets = (array)$offsets;

			foreach ( $offsets as $offset ) {
				if ( $this[$offset]->getHash() !== $hash ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Removes and adds all elements, ensuring the indices are up to date.
	 *
	 * @since 0.4
	 */
	public function rebuildIndices() {
		$hashables = iterator_to_array( $this );

		$this->offsetHashes = array();

		foreach ( $hashables as $offset => $hashable ) {
			$this->offsetUnset( $offset );
			$this->offsetSet( $offset, $hashable );
		}
	}

}
