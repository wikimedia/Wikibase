<?php

namespace Wikibase\Lib\Serializers;

use MWException;
use ValueFormatters\ValueFormatter;
use Wikibase\LanguageFallbackChainFactory;

/**
 * Options for Serializer objects.
 *
 * TODO: use PDO like options system as done in ValueParsers
 *
 * @since 0.2
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class SerializationOptions {

	/**
	 * @since 0.3
	 * @var boolean
	 */
	protected $indexTags = false;

	const ID_KEYS_UPPER = 1;
	const ID_KEYS_LOWER = 2;
	const ID_KEYS_BOTH = 3;

	/**
	 * @since 0.5
	 * @var int $idKeyMode bit field determining whether to use upper case entities IDs as
	 *      keys in the serialized structure, or lower case IDs, or both.
	 */
	protected $idKeyMode = self::ID_KEYS_UPPER;

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

	/**
	 * Returns whether lower case entities IDs should be used as keys in the serialized data structure.
	 *
	 * @see setIdKeyMode()
	 *
	 * @since 0.5
	 *
	 * @return boolean
	 */
	public function shouldUseLowerCaseIdsAsKeys() {
		return ( $this->idKeyMode & self::ID_KEYS_LOWER ) > 0;
	}

	/**
	 * Returns whether upper case entities IDs should be used as keys in the serialized data structure.
	 *
	 * @see setIdKeyMode()
	 *
	 * @since 0.5
	 *
	 * @return boolean
	 */
	public function shouldUseUpperCaseIdsAsKeys() {
		return ( $this->idKeyMode & self::ID_KEYS_UPPER ) > 0;
	}

	/**
	 * Sets whether upper case entities IDs should be used as keys in the serialized data structure,
	 * or lower case, or both.
	 *
	 * Allowing for different forms of IDs to be used as keys is needed for backwards
	 * compatibility while we change from lower case to upper case IDs in version 0.5.
	 *
	 * @see shouldUseLowerCaseIdsAsKeys()
	 * @see shouldUseUpperCaseIdsAsKeys()
	 *
	 * @since 0.5
	 *
	 * @param int $mode a bit field using the ID_KEYS_XXX constants.
	 * @throws \InvalidArgumentException
	 */
	public function setIdKeyMode( $mode ) {
		if ( ( $mode & self::ID_KEYS_BOTH ) === 0 ) {
			throw new \InvalidArgumentException( "At least one ID key mode must be set in the bit field." );
		}

		if ( ( $mode & ~self::ID_KEYS_BOTH ) !== 0 ) {
			throw new \InvalidArgumentException( "Unknown bits set in ID key mode, use the ID_KEYS_XXX constants." );
		}

		$this->idKeyMode = $mode;
	}
}

/**
 * Options for MultiLang Serializers.
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
 * @since 0.4
 *
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Jeroen De Dauw < tobias.gritschacher@wikimedia.de >
 */
class MultiLangSerializationOptions extends SerializationOptions {
	/**
	 * The language info array of the languages for which internationalized data (ie descriptions) should be returned.
	 * Or null for no restriction.
	 *
	 * Array keys are language codes (may include pseudo ones to identify some given fallback chains); values are
	 * LanguageFallbackChain objects (plain code inputs are constructed into language chains with a single language).
	 *
	 * @since 0.4
	 *
	 * @var null|array as described above
	 */
	protected $languages = null;

	/**
	 * Used to create LanguageFallbackChain objects when the old style array-of-strings argument is used in setLanguage().
	 *
	 * @var LanguageFallbackChainFactory
	 */
	protected $languageFallbackChainFactory;

	/**
	 * Sets the language codes or language fallback chains of the languages for which internationalized data
	 * (ie descriptions) should be returned.
	 *
	 * @since 0.2
	 *
	 * @param array|null $languages array of strings (back compat, as language codes)
	 *                     or LanguageFallbackChain objects (requested language codes as keys, to identify chains)
	 */
	public function setLanguages( array $languages = null ) {
		if ( $languages === null ) {
			$this->languages = null;

			return;
		}

		$this->languages = array();

		foreach ( $languages as $languageCode => $languageFallbackChain ) {
			// back-compat
			if ( is_numeric( $languageCode ) ) {
				$languageCode = $languageFallbackChain;
				$languageFallbackChain = $this->getLanguageFallbackChainFactory()->newFromLanguageCode(
					$languageCode, LanguageFallbackChainFactory::FALLBACK_SELF
				);
			}

			$this->languages[$languageCode] = $languageFallbackChain;
		}
	}

	/**
	 * Gets the language codes of the languages for which internationalized data (ie descriptions) should be returned.
	 *
	 * @since 0.2
	 *
	 * @return array|null
	 */
	public function getLanguages() {
		if ( $this->languages === null ) {
			return null;
		} else {
			return array_keys( $this->languages );
		}
	}

	/**
	 * Gets an associative array with language codes as keys and their fallback chains as values, or null.
	 *
	 * @since 0.4
	 *
	 * @return array|null
	 */
	public function getLanguageFallbackChains() {
		return $this->languages;
	}

	/**
	 * Get the language fallback chain factory previously set, or a new one if none was set.
	 *
	 * @since 0.4
	 *
	 * @return LanguageFallbackChainFactory
	 */
	public function getLanguageFallbackChainFactory() {
		if ( $this->languageFallbackChainFactory === null ) {
			$this->languageFallbackChainFactory = new LanguageFallbackChainFactory();
		}

		return $this->languageFallbackChainFactory;
	}

	/**
	 * Set language fallback chain factory and return the previously set one.
	 *
	 * @since 0.4
	 *
	 * @param LanguageFallbackChainFactory $factory
	 *
	 * @return LanguageFallbackChainFactory|null
	 */
	public function setLanguageFallbackChainFactory( LanguageFallbackChainFactory $factory ) {
		return wfSetVar( $this->languageFallbackChainFactory, $factory );
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
class EntitySerializationOptions extends MultiLangSerializationOptions {

	const SORT_ASC = 'ascending';
	const SORT_DESC = 'descending';
	const SORT_NONE = 'none';

	/**
	 * The optional properties of the entity that should be included in the serialization.
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
		// TODO: the following properties are not part of all entities, listing them here is not nice
		'datatype', // property specific
		'sitelinks', // item specific
	);

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
	 *
	 * @param string $name
	 */
	public function addProp( $name ) {
		$this->props[] = $name;
	}

	/**
	 * Removes a prop from the list of optionally included elements of the entity.
	 *
	 * @since 0.4
	 *
	 * @param string $name
	 */
	public function removeProp ( $name ) {
		$this->props = array_diff( $this->props, array( $name ) );
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
