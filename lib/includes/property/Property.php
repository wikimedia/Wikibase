<?php

namespace Wikibase;
use DataTypes\DataType;
use DataValues\DataValue;
use MWException;

/**
 * Interface for objects that represent a single Wikibase property.
 * See https://meta.wikimedia.org/wiki/Wikidata/Data_model#Properties
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
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
interface Property extends Entity, ClaimListAccess {

	const ENTITY_TYPE = 'property';

	/**
	 * Returns the DataType of the property.
	 *
	 * @since 0.2
	 *
	 * @return DataType
	 * @throws MWException
	 */
	public function getDataType();

	/**
	 * Sets the DataType of the property.
	 *
	 * @since 0.2
	 *
	 * @param DataType $dataType
	 */
	public function setDataType( DataType $dataType );

	/**
	 * Sets the DataType of the property.
	 *
	 * @since 0.2
	 *
	 * @param string $dataTypeId
	 */
	public function setDataTypeById( $dataTypeId );

	/**
	 * Convenience function to check if the property contains any claims.
	 *
	 * @since 0.2
	 *
	 * @return boolean
	 */
	public function hasClaims();

	/**
	 * Factory for creating new DataValue objects for the property.
	 *
	 * @since 0.3
	 *
	 * @param mixed $rawDataValue The value that can be obtained via $dataValue->toArray()
	 *
	 * @return DataValue
	 */
	public function newDataValue( $rawDataValue );

}
