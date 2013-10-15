<?php

namespace Wikibase\Lib\Serializers;

use MWException;
use Wikibase\Entity;
use Wikibase\Property;

/**
 * Serializer for properties.
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
 * @since 0.3
 *
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class PropertySerializer extends EntitySerializer implements Unserializer {

	/**
	 * @see EntitySerializer::getEntityTypeSpecificSerialization
	 *
	 * @since 0.3
	 *
	 * @param Entity $property
	 *
	 * @return array
	 * @throws MWException
	 */
	protected function getEntityTypeSpecificSerialization( Entity $property ) {
		if ( !( $property instanceof Property ) ) {
			throw new MWException( 'PropertySerializer can only serialize Property implementing objects' );
		}

		$serialization = array();

		if ( in_array( 'datatype', $this->options->getProps() ) ) {
			$serialization['datatype'] = $property->getDataTypeId();
		}

		return $serialization;
	}

	/**
	 * @param array $data
	 *
	 * @return Property
	 */
	public function newFromSerialization( array $data ) {
		$entity = parent::newFromSerialization( $data );

		// @todo validate datatype
		if ( array_key_exists( 'datatype', $data ) ) {
			$entity->setDataTypeId( $data['datatype'] );
		}

		return $entity;
	}

}
