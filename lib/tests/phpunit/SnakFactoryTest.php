<?php

namespace Wikibase\Test;

use DataValues\DataValueFactory;
use DataValues\Deserializers\DataValueDeserializer;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\SnakFactory;

/**
 * @covers Wikibase\SnakFactory
 *
 * @group Wikibase
 * @group WikibaseLib
 * @group WikibaseSnak
 * @group Database
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class SnakFactoryTest extends \MediaWikiTestCase {

	public function provideNewSnak() {
		return array(
			array( 1, 'somevalue', null, null, 'Wikibase\DataModel\Snak\PropertySomeValueSnak', null, null, 'some value' ),
			array( 1, 'novalue', null, null, 'Wikibase\DataModel\Snak\PropertyNoValueSnak', null, null, 'no value' ),
			array( 1, 'value', 'string', 'foo', 'Wikibase\DataModel\Snak\PropertyValueSnak', 'DataValues\StringValue', null, 'a value' ),
			array( 1, 'kittens', null, 'foo', null, null, 'InvalidArgumentException', 'bad snak type' ),
		);
	}

	/**
	 * @dataProvider provideNewSnak
	 */
	public function testNewSnak(
		$propertyId,
		$snakType,
		$valueType,
		$snakValue,
		$expectedSnakClass,
		$expectedValueClass,
		$expectedException,
		$message
	) {
		if ( is_int( $propertyId ) ) {
			$propertyId = PropertyId::newFromNumber( $propertyId );
		}

		if ( $valueType !== null ) {
			$dataValueFactory = new DataValueFactory( new DataValueDeserializer( array(
				$valueType => $expectedValueClass
			) ) );

			$dataValue = $dataValueFactory->newDataValue( $valueType, $snakValue );
		} else {
			$dataValue = null;
		}

		if ( $expectedException !== null ) {
			$this->setExpectedException( $expectedException );
		}

		$factory = new SnakFactory();
		$snak = $factory->newSnak( $propertyId, $snakType, $dataValue );

		if ( $expectedSnakClass !== null ) {
			$this->assertInstanceOf( $expectedSnakClass, $snak, $message );
		}

		if ( $expectedValueClass !== null && $snak instanceof PropertyValueSnak ) {
			$dataValue = $snak->getDataValue();
			$this->assertInstanceOf( $expectedValueClass, $dataValue, $message );
		}
	}

}
