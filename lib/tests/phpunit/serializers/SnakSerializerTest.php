<?php

namespace Wikibase\Test;

use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\PropertyNotFoundException;
use Wikibase\Lib\Serializers\SnakSerializer;
use Wikibase\PropertyNoValueSnak;
use Wikibase\PropertySomeValueSnak;
use Wikibase\PropertyValueSnak;

/**
 * @covers Wikibase\Lib\Serializers\SnakSerializer
 *
 * @since 0.2
 *
 * @group WikibaseLib
 * @group Wikibase
 * @group WikibaseSerialization
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class SnakSerializerTest extends SerializerBaseTest {

	/**
	 * @see SerializerBaseTest::getClass
	 *
	 * @since 0.2
	 *
	 * @return string
	 */
	protected function getClass() {
		return '\Wikibase\Lib\Serializers\SnakSerializer';
	}

	/**
	 * @since 0.2
	 *
	 * @return SnakSerializer
	 */
	protected function getInstance() {
		$dataTypeLookup = $this->getMock( 'Wikibase\Lib\PropertyDataTypeLookup' );
		$dataTypeLookup->expects( $this->any() )
			->method( 'getDataTypeIdForProperty' )
			->will( $this->returnCallback( array( $this, 'getDataTypeIdForProperty' ) ) );

		$class = $this->getClass();
		return new $class( $dataTypeLookup );
	}

	public function getDataTypeIdForProperty( PropertyId $propertyId ) {

		if ( $propertyId->getNumericId() > 100 ) {
			throw new PropertyNotFoundException( $propertyId );
		}

		return 'test';
	}

	/**
	 * @see SerializerBaseTest::validProvider
	 *
	 * @since 0.2
	 *
	 * @return array
	 */
	public function validProvider() {
		$validArgs = array();

		$id = new PropertyId( 'P42' );

		$validArgs[] = array(
			new PropertyNoValueSnak( $id ),
			array(
				'snaktype' => 'novalue',
				'property' => 'P42',
			)
		);

		$validArgs[] = array(
			new PropertySomeValueSnak( $id ),
			array(
				'snaktype' => 'somevalue',
				'property' => 'P42',
			)
		);

		$dataValue = new StringValue( 'ohi' );

		$validArgs[] = array(
			new PropertyValueSnak( $id, $dataValue ),
			array(
				'snaktype' => 'value',
				'property' => 'P42',
				'datatype' => 'test', // from the getDataTypeIdForProperty() via getInstance()
				'datavalue' => $dataValue->toArray(),
			)
		);

		// If the property ID is > 100, getDataTypeIdForProperty() will throw an exception,
		// and the data type should be skipped.
		$badId = new PropertyId( 'P666' );

		$validArgs[] = array(
			new PropertyValueSnak( $badId, $dataValue ),
			array(
				'snaktype' => 'value',
				'property' => 'P666',
				'datavalue' => $dataValue->toArray(),
			)
		);

		return $validArgs;
	}

}
