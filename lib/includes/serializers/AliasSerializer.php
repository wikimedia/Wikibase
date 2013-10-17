<?php

namespace Wikibase\Lib\Serializers;

use InvalidArgumentException;

/**
 * Serializer for aliases.
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
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 */
class AliasSerializer extends SerializerObject {

	/**
	 * Constructor.
	 *
	 * @since 0.4
	 *
	 * @param SerializationOptions $options
	 */
	public function __construct( SerializationOptions $options = null ) {
		parent::__construct( $options );
	}

	/**
	 * Returns a serialized array of aliases.
	 *
	 * @since 0.4
	 *
	 * @param array $aliases
	 *
	 * @return array
	 * @throws InvalidArgumentException
	 */
	public final function getSerialized( $aliases ) {
		if ( !is_array( $aliases ) ) {
			throw new InvalidArgumentException( 'AliasSerializer can only serialize an array of aliases' );
		}

		$value = array();

		if ( !$this->options->shouldIndexTags() ) {
			foreach ( $aliases as $languageCode => $alarr ) {
				$arr = array();
				foreach ( $alarr as $alias ) {
					if ( $alias === '' ) {
						continue; // skip empty aliases
					}
					$arr[] = array(
						'language' => $languageCode,
						'value' => $alias,
					);
				}
				$value[$languageCode] = $arr;
			}
		}
		else {
			foreach ( $aliases as $languageCode => $alarr ) {
				foreach ( $alarr as $alias ) {
					if ( $alias === '' ) {
						continue; // skip empty aliases
					}
					$value[] = array(
						'language' => $languageCode,
						'value' => $alias,
					);
				}
			}
		}

		if ( $this->options->shouldIndexTags() ) {
			$this->setIndexedTagName( $value, 'alias' );
		}

		return $value;
	}
}
