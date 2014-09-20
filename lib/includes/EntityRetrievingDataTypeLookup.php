<?php

namespace Wikibase\Lib;

use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyDataTypeLookup;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\PropertyNotFoundException;
use Wikibase\Lib\Store\EntityLookup;

/**
 * PropertyDataTypeLookup that uses an EntityLookup to find
 * a property's data type ID.
 *
 * @since 0.4
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class EntityRetrievingDataTypeLookup implements PropertyDataTypeLookup {

	private $entityLookup;

	public function __construct( EntityLookup $entityLookup ) {
		$this->entityLookup = $entityLookup;
	}

	/**
	 * @since 0.4
	 *
	 * @param PropertyId $propertyId
	 *
	 * @return string
	 * @throws PropertyNotFoundException
	 */
	public function getDataTypeIdForProperty( PropertyId $propertyId ) {
		return $this->getProperty( $propertyId )->getDataTypeId();
	}

	/**
	 * @param PropertyId $propertyId
	 *
	 * @return Property
	 * @throws PropertyNotFoundException
	 */
	private function getProperty( PropertyId $propertyId ) {
		$property = $this->entityLookup->getEntity( $propertyId );

		if ( !( $property instanceof Property ) ) {
			throw new PropertyNotFoundException( $propertyId );
		}

		return $property;
	}

}
