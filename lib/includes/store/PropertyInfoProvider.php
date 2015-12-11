<?php

namespace Wikibase\Lib;

use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\EntityLookupException;
use Wikibase\Lib\Store\StorageException;

/**
 * Service for providing a specific information about properties.
 *
 * Which information is provided is determined by the concrete implementation and instance.
 * Consumers of this interface should provide documentation that clearly states what information
 * the PropertyInfoProvider instance is expected to return, and in what form.
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
interface PropertyInfoProvider {

	/**
	 * Returns some information associated with a property ID.
	 *
	 * @param PropertyId $propertyId
	 *
	 * @return mixed|null
	 *
	 * @throws StorageException
	 */
	public function getPropertyInfo( PropertyId $propertyId );

}
