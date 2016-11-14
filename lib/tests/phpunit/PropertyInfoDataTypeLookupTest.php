<?php

namespace Wikibase\Lib\Tests;

use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\EntityRetrievingDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookupException;
use Wikibase\Lib\PropertyInfoDataTypeLookup;
use Wikibase\PropertyInfoStore;
use Wikibase\Lib\Tests\Store\MockPropertyInfoStore;

/**
 * @covers Wikibase\Lib\PropertyInfoDataTypeLookup
 *
 * @group Wikibase
 * @group WikibaseLib
 *
 * @license GPL-2.0+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 */
class PropertyInfoDataTypeLookupTest extends \PHPUnit_Framework_TestCase {

	private $propertiesAndTypes = [
		'P1' => 'NyanData all the way across the sky',
		'P42' => 'string',
		'P1337' => 'percentage',
		'P9001' => 'positive whole number',
	];

	public function getDataTypeForPropertyProvider() {
		$argLists = [];

		$emptyInfoStore = new MockPropertyInfoStore();
		$mockInfoStore = new MockPropertyInfoStore();

		$entityLookup = new MockRepository();
		$propertyDataTypeLookup = new EntityRetrievingDataTypeLookup( $entityLookup );

		foreach ( $this->propertiesAndTypes as $propertyId => $dataTypeId ) {
			$id = new PropertyId( $propertyId );

			// register property info
			$mockInfoStore->setPropertyInfo(
				$id,
				[ PropertyInfoStore::KEY_DATA_TYPE => $dataTypeId ]
			);

			// register property as an entity, for the fallback
			$property = Property::newFromType( $dataTypeId );
			$property->setId( $id );
			$entityLookup->putEntity( $property );

			// try with a working info store
			$argLists[] = [
				$mockInfoStore,
				null,
				$id,
				$dataTypeId
			];

			// try with via fallback
			$argLists[] = [
				$emptyInfoStore,
				$propertyDataTypeLookup,
				$id,
				$dataTypeId
			];
		}

		// try unknown property
		$id = new PropertyId( 'P23' );

		// try with a working info store
		$argLists[] = [
			$mockInfoStore,
			null,
			$id,
			false
		];

		// try with via fallback
		$argLists[] = [
			$emptyInfoStore,
			$propertyDataTypeLookup,
			$id,
			false
		];

		return $argLists;
	}

	/**
	 * @dataProvider getDataTypeForPropertyProvider
	 */
	public function testGetDataTypeForProperty(
		PropertyInfoStore $infoStore,
		PropertyDataTypeLookup $fallbackLookup = null,
		PropertyId $propertyId,
		$expectedDataType
	) {
		if ( $expectedDataType === false ) {
			$this->setExpectedException( PropertyDataTypeLookupException::class );
		}

		$lookup = new PropertyInfoDataTypeLookup( $infoStore, $fallbackLookup );

		$actualDataType = $lookup->getDataTypeIdForProperty( $propertyId );

		if ( $expectedDataType !== false ) {
			$this->assertInternalType( 'string', $actualDataType );
			$this->assertEquals( $expectedDataType, $actualDataType );
		}
	}

}
