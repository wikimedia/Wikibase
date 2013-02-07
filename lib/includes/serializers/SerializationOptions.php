<?php

namespace Wikibase\Lib\Serializers;
use MWException;

/**
 * Options for Serializer objects.
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
 * @since 0.2
 *
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class SerializationOptions {

	/**
	 * @since 0.3
	 * @var boolean
	 */
	protected $indexTags = false;

	/**
	 * Sets if tags should be indexed.
	 * The MediaWiki API needs this when building API results in formats such as XML.
	 *
	 * @since 0.3
	 *
	 * @param boolean $indexTags
	 *
	 * @throws MWException
	 */
	public function setIndexTags( $indexTags ) {
		if ( !is_bool( $indexTags ) ) {
			throw new MWException( 'Expected boolean, got something else' );
		}

		$this->indexTags = $indexTags;
	}

	/**
	 * Returns if tags should be indexed.
	 *
	 * @since 0.3
	 *
	 * @return boolean
	 */
	public function shouldIndexTags() {
		return $this->indexTags;
	}

}

/**
 * Options for Entity serializers.
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
 * @since 0.2
 *
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class EntitySerializationOptions extends SerializationOptions {

	const SORT_ASC = 'ascending';
	const SORT_DESC = 'descending';
	const SORT_NONE = 'none';

	/**
	 * The optional properties of the entity that should be included in the serialization.
	 *
	 * TODO: include all props by default, so callers don't have to go specify the
	 * whole list (inc entity type specific props) all the time.
	 *
	 * @since 0.2
	 *
	 * @var array of string
	 */
	protected $props = array(
		'aliases',
		'descriptions',
		'labels',
		'claims',
	);

	/**
	 * The language codes of the languages for which internationalized data (ie descriptions) should be returned.
	 * Or null for no restriction.
	 *
	 * @since 0.2
	 *
	 * @var null|array of string
	 */
	protected $languageCodes = null;

	/**
	 * Names of fields to sort on.
	 *
	 * @since 0.2
	 *
	 * @var array
	 */
	protected $sortFields = array();

	/**
	 * The direction the result should be sorted in.
	 *
	 * @since 0.2
	 *
	 * @var string Element of the EntitySerializationOptions::SORT_ enum
	 */
	protected $sortDirection = self::SORT_NONE;

	/**
	 * Sets if keys should be used in the serialization.
	 *
	 * @since 0.2
	 * @deprecated
	 *
	 * @param boolean $useKeys
	 *
	 * @throws MWException
	 */
	public function setUseKeys( $useKeys ) {
		if ( !is_bool( $useKeys ) ) {
			throw new MWException( __METHOD__ . ' expects a boolean' );
		}

		$this->indexTags = $useKeys;
	}

	/**
	 * Returns if keys should be used in the serialization.
	 *
	 * @since 0.2
	 * @deprecated
	 *
	 * @return boolean
	 */
	public function shouldUseKeys() {
		return !$this->indexTags;
	}

	/**
	 * Sets the optional properties of the entity that should be included in the serialization.
	 *
	 * @since 0.2
	 *
	 * @param array $props
	 */
	public function setProps( array $props ) {
		$this->props = $props;
	}

	/**
	 * Gets the optional properties of the entity that should be included in the serialization.
	 *
	 * @since 0.2
	 *
	 * @return array
	 */
	public function getProps() {
		return $this->props;
	}

	/**
	 * Adds a prop to the list of optionally included elements of the entity.
	 *
	 * @since 0.3
	 */
	public function addProp( $name ) {
		$this->props[] = $name;
	}

	/**
	 * Sets the language codes of the languages for which internationalized data (ie descriptions) should be returned.
	 *
	 * @since 0.2
	 *
	 * @param array|null $languageCodes
	 */
	public function setLanguages( array $languageCodes = null ) {
		$this->languageCodes = $languageCodes;
	}

	/**
	 * Gets the language codes of the languages for which internationalized data (ie descriptions) should be returned.
	 *
	 * @since 0.2
	 *
	 * @return array|null
	 */
	public function getLanguages() {
		return $this->languageCodes;
	}

	/**
	 * Sets the names of fields to sort on.
	 *
	 * @since 0.2
	 *
	 * @param array $sortFields
	 */
	public function setSortFields( array $sortFields ) {
		$this->sortFields = $sortFields;
	}

	/**
	 * Returns the names of fields to sort on.
	 *
	 * @since 0.2
	 *
	 * @return array
	 */
	public function getSortFields() {
		return $this->sortFields;
	}

	/**
	 * Sets the direction the result should be sorted in.
	 *
	 * @since 0.2
	 *
	 * @param string $sortDirection Element of the EntitySerializationOptions::SORT_ enum
	 * @throws MWException
	 */
	public function setSortDirection( $sortDirection ) {
		if ( !in_array( $sortDirection, array( self::SORT_ASC, self::SORT_DESC, self::SORT_NONE ) ) ) {
			throw new MWException( 'Invalid sort direction provided' );
		}

		$this->sortDirection = $sortDirection;
	}

	/**
	 * Returns the direction the result should be sorted in.
	 *
	 * @since 0.2
	 *
	 * @return string Element of the EntitySerializationOptions::SORT_ enum
	 */
	public function getSortDirection() {
		return $this->sortDirection;
	}

}
