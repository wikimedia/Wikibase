<?php

namespace Wikibase\Test;

use DataTypes\DataType;
use DataTypes\DataTypeFactory;
use DataValues\GlobeCoordinateValue;
use DataValues\LatLongValue;
use DataValues\NumberValue;
use DataValues\QuantityValue;
use DataValues\StringValue;
use DataValues\TimeValue;
use ValueValidators\Result;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Item;
use Wikibase\Lib\WikibaseDataTypeBuilders;

/**
 * @covers WikibaseDataTypeBuilders
 *
 * @group WikibaseLib
 * @group Wikibase
 * @group WikibaseValidators
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class WikibaseDataTypeBuildersTest extends \PHPUnit_Framework_TestCase {

	protected function newTypeFactory() {
		$entityIdParser = new BasicEntityIdParser();

		$q8 = Item::newEmpty();
		$q8->setId( new ItemId( 'q8' ) );

		$entityLookup = new MockRepository();
		$entityLookup->putEntity( $q8 );

		$urlSchemes = array( 'http', 'https', 'ftp', 'mailto' );

		$builders = new WikibaseDataTypeBuilders( $entityLookup, $entityIdParser, $urlSchemes );
		$dataTypeFactory = new DataTypeFactory( $builders->getDataTypeBuilders() );

		return $dataTypeFactory;
	}

	public function provideDataTypeValidation() {
		$latLonValue = new LatLongValue( 0, 0 );

		$cases = array(
			//wikibase-item
			array( 'wikibase-item', 'q8', false, 'Expected EntityId, string supplied' ),
			array( 'wikibase-item', new StringValue( 'q8' ), false, 'Expected EntityId, StringValue supplied' ),
			array( 'wikibase-item', new EntityIdValue( new ItemId( 'q8' ) ), true, 'existing entity' ),
			array( 'wikibase-item', new EntityIdValue( new ItemId( 'q3' ) ), false, 'missing entity' ),

			//commonsMedia
			array( 'commonsMedia', 'Foo.jpg', false, 'StringValue expected, string supplied' ),
			array( 'commonsMedia', new NumberValue( 7 ), false, 'StringValue expected' ),
			array( 'commonsMedia', new StringValue( '' ), false, 'empty string should be invalid' ),
			array( 'commonsMedia', new StringValue( str_repeat('x', 250) . '.jpg' ), false, 'name too long' ),
			array( 'commonsMedia', new StringValue( 'Foo' ), false, 'no file extension' ),
			array( 'commonsMedia', new StringValue( 'Foo.jpg' ), true, 'this should be good' ),
			array( 'commonsMedia', new StringValue( 'Foo#bar.jpg' ), false, 'illegal character: hash' ),
			array( 'commonsMedia', new StringValue( 'Foo:bar.jpg' ), false, 'illegal character: colon' ),
			array( 'commonsMedia', new StringValue( 'Foo/bar.jpg' ), false, 'illegal character: slash' ),
			array( 'commonsMedia', new StringValue( 'Foo\bar.jpg' ), false, 'illegal character: backslash' ),
			array( 'commonsMedia', new StringValue( 'Äöü.jpg' ), true, 'Unicode support' ),
			array( 'commonsMedia', new StringValue( ' Foo.jpg ' ), false, 'Untrimmed input is forbidden' ),

			//string
			array( 'string', 'Foo', false, 'StringValue expected, string supplied' ),
			array( 'string', new NumberValue( 7 ), false, 'StringValue expected' ),
			array( 'string', new StringValue( '' ), false, 'empty string should be invalid' ),
			array( 'string', new StringValue( 'Foo' ), true, 'simple string' ),
			array( 'string', new StringValue( 'Äöü' ), true, 'Unicode support' ),
			array( 'string', new StringValue( str_repeat('x', 390) ), true, 'long, but not too long' ),
			array( 'string', new StringValue( str_repeat('x', 401) ), false, 'too long' ),
			array( 'string', new StringValue( ' Foo ' ), false, 'Untrimmed' ),

			//time
			array( 'time', 'Foo', false, 'TimeValue expected, string supplied' ),
			array( 'time', new NumberValue( 7 ), false, 'TimeValue expected' ),

			//time['calendar-model']
			array( 'time', new TimeValue( '+0000000000002013-06-06T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_DAY, '' ), false, 'calendar: empty string should be invalid' ),
			array( 'time', new TimeValue( '+0000000000002013-06-06T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_DAY, 'http://' . str_repeat('x', 256) ), false, 'calendar: too long' ),
			array( 'time', new TimeValue( '+0000000000002013-06-06T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_DAY, 'http://acme.com/calendar' ), true, 'calendar: URL' ),
			array( 'time', new TimeValue( '+0000000000002013-06-06T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_DAY, ' http://acme.com/calendar ' ), false, 'calendar: untrimmed' ),
			array( 'time', new TimeValue( '+0000000000002013-06-06T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_DAY, ' javascript:alert(1)' ), false, 'calendar: bad URL' ),

			//precision to the second (currently not allowed)
			array( 'time', new TimeValue( '+0000000000002013-06-06T11:22:33Z', 0, 0, 0, TimeValue::PRECISION_DAY, 'http://acme.com/calendar' ), false, 'time given to the second' ),
			array( 'time', new TimeValue( '+0000000000002013-06-06T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_SECOND, 'http://acme.com/calendar' ), false, 'precision: second' ),

			//time['time']
			//NOTE: The below will fail with a IllegalValueExcpetion once the TimeValue constructor enforces the time format.
			//      Once that is done, this test and the respective validator can and should both be removed.
			//array( 'string', new TimeValue( '2013-06-06 11:22:33', 0, 0, 0, 0, 'http://acme.com/calendar' ), false, 'time: not ISO 8601' ),

			//TODO: calendar must be an item reference
			//TODO: calendar must be from a list of configured values

			//globe-coordinate
			array( 'globe-coordinate', 'Foo', false, 'GlobeCoordinateValue expected, string supplied' ),
			array( 'globe-coordinate', new NumberValue( 7 ), false, 'GlobeCoordinateValue expected' ),

			//globe-coordinate[precision]
			array(
				'globe-coordinate',
				new GlobeCoordinateValue( $latLonValue, 1, 'http://www.wikidata.org/entity/Q2' ),
				true,
				'integer precision is valid'
			),
			array(
				'globe-coordinate',
				new GlobeCoordinateValue( $latLonValue, 0.2, 'http://www.wikidata.org/entity/Q2' ),
				true,
				'float precision is valid'
			),
			array(
				'globe-coordinate',
				new GlobeCoordinateValue( $latLonValue, null, 'http://www.wikdiata.org/entity/Q2' ),
				false,
				'null precision is invalid'
			),

			//globe-coordinate[globe]
			// FIXME: this is testing unimplemented behaviour? Probably broken...
			array( 'globe-coordinate', new GlobeCoordinateValue( $latLonValue, 1, '' ), false, 'globe: empty string should be invalid' ),
			array( 'globe-coordinate', new GlobeCoordinateValue( $latLonValue, 1, 'http://' . str_repeat('x', 256) ), false, 'globe: too long' ),
			array( 'globe-coordinate', new GlobeCoordinateValue( $latLonValue, 1, 'http://acme.com/globe' ), true, 'globe: URL' ),
			array( 'globe-coordinate', new GlobeCoordinateValue( $latLonValue, 1, ' http://acme.com/globe ' ), false, 'globe: untrimmed' ),
			array( 'globe-coordinate', new GlobeCoordinateValue( $latLonValue, 1, ' javascript:alert(1) ' ), false, 'globe: bad URL scheme' ),
			//TODO: globe must be an item reference
			//TODO: globe must be from a list of configured values

			// url
			array( 'url', 'Foo', false, 'StringValue expected, string supplied' ),
			array( 'url', new NumberValue( 7 ), false, 'StringValue expected' ),

			array( 'url', new StringValue( 'http://acme.com' ), true, 'Simple HTTP URL' ),
			array( 'url', new StringValue( 'https://acme.com' ), true, 'Simple HTTPS URL' ),
			array( 'url', new StringValue( 'ftp://acme.com' ), true, 'Simple FTP URL' ),
			array( 'url', new StringValue( 'http://acme.com/foo/bar?some=stuff#fragment' ), true, 'Complex HTTP URL' ),

			// evil url
			array( 'url', new StringValue( '//bla' ), false, 'Protocol-relative' ),
			array( 'url', new StringValue( '/bla/bla' ), false, 'relative path' ),
			array( 'url', new StringValue( 'just stuff' ), false, 'just words' ),
			array( 'url', new StringValue( 'javascript:alert("evil")' ), false, 'JavaScript URL' ),
			array( 'url', new StringValue( 'http://' ), false, 'bad http URL' ),
			array( 'url', new StringValue( 'http://' . str_repeat('x', 505) ), false, 'URL too long' ),
		);

		if ( defined( 'WB_EXPERIMENTAL_FEATURES' ) && WB_EXPERIMENTAL_FEATURES ) {
			$cases = array_merge( $cases, array(
				//quantity
				array( 'quantity', QuantityValue::newFromNumber( 5 ), true, 'Simple integer' ),
				array( 'quantity', QuantityValue::newFromNumber( 5, 'm' ), false, 'We don\'t support units yet' ),
				array( 'quantity', QuantityValue::newFromDecimal( '-11.234', '1', '-10', '-12' ), true, 'decimal strings' ),

				// ....
			) );
		}

		return $cases;
	}

	/**
	 * @dataProvider provideDataTypeValidation
	 */
	public function testDataTypeValidation( $typeId, $value, $expected, $message ) {
		$typeFactory = $this->newTypeFactory();
		$type = $typeFactory->getType( $typeId );

		$this->assertValidation( $expected, $type, $value, $message );
	}

	protected function assertValidation( $expected, DataType $type, $value, $message ) {
		$validators = $type->getValidators(); //TODO: there should probably be a DataType::validate() method.

		$result = Result::newSuccess();
		foreach ( $validators as $validator ) {
			$result = $validator->validate( $value );

			if ( !$result->isValid() ) {
				break;
			}
		}

		if ( $expected ) {
			$errors = $result->getErrors();
			if ( !empty( $errors ) ) {
				$this->fail( $message . "\n" . $errors[0]->getText() );
			}

			$this->assertEquals( $expected, $result->isValid(), $message );
		} else {
			$this->assertEquals( $expected, $result->isValid(), $message );
		}
	}
}
