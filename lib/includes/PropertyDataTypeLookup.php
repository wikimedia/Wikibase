<?php

namespace Wikibase\Lib;

use Wikibase\DataModel\Entity\PropertyId;

/**
 * Interface for objects that can find the if of the DataType
 * for the Property of which the id is given.
 *
 * @since 0.4
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
interface PropertyDataTypeLookup {

	/**
	 * Returns the DataType for the Property of which the id is given.
	 *
	 * @since 0.4
	 *
	 * @param PropertyId $propertyId
	 *
	 * @return string
	 * @throws PropertyNotFoundException
	 */
	public function getDataTypeIdForProperty( PropertyId $propertyId );

}
