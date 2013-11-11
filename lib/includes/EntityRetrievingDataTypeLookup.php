<?php

namespace Wikibase\Lib;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\EntityLookup;
use Wikibase\Property;

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

	/**
	 * @var EntityLookup
	 */
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
		$this->verifyIdIsOfAProperty( $propertyId );
		return $this->getProperty( $propertyId )->getDataTypeId();
	}

	private function verifyIdIsOfAProperty( PropertyId $propertyId ) {
		if ( $propertyId->getEntityType() !== Property::ENTITY_TYPE ) {
			throw new InvalidArgumentException( '$propertyId with non-property entity type provided' );
		}
	}

	/**
	 * @param PropertyId $propertyId
	 *
	 * @return Property
	 * @throws PropertyNotFoundException
	 */
	private function getProperty( PropertyId $propertyId ) {
		$property = $this->entityLookup->getEntity( $propertyId );

		if ( $property === null ) {
			throw new PropertyNotFoundException( $propertyId );
		}

		assert( $property instanceof Property );
		return $property;
	}

}
