<?php

namespace Wikibase\Lib\Test;

use DataTypes\DataType;
use DataTypes\DataTypeFactory;
use DataValues\StringValue;
use DataValues\UnDeserializableValue;
use OutOfBoundsException;
use ValueFormatters\FormatterOptions;
use ValueFormatters\StringFormatter;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookupException;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Lib\DispatchingValueFormatter;
use Wikibase\Lib\PropertyValueSnakFormatter;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Lib\UnDeserializableValueFormatter;

/**
 * @covers Wikibase\Lib\PropertyValueSnakFormatter
 *
 * @group ValueFormatters
 * @group DataValueExtensions
 * @group WikibaseLib
 * @group Wikibase
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class PropertyValueSnakFormatterTest extends \MediaWikiTestCase {

	/**
	 * @dataProvider constructorErrorsProvider
	 */
	public function testConstructorErrors( $format, $error ) {
		$this->setExpectedException( $error );

		$this->getDummyPropertyValueSnakFormatter( $format );
	}

	public function constructorErrorsProvider() {
		return array(
			'format must be a string' => array(
				17,
				'InvalidArgumentException'
			),
		);
	}

	/**
	 * @param string $dataType
	 *
	 * @return PropertyDataTypeLookup
	 */
	private function getMockDataTypeLookup( $dataType ) {
		if ( $dataType !== '' ) {
			$getDataTypeIdForPropertyResult = $this->returnValue( $dataType );
		} else {
			$getDataTypeIdForPropertyResult = $this->throwException(
				new PropertyDataTypeLookupException( new PropertyId( 'P666' ) ) );
		}

		$typeLookup = $this->getMock( 'Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup' );
		$typeLookup->expects( $this->atLeastOnce() )
			->method( 'getDataTypeIdForProperty' )
			->will( $getDataTypeIdForPropertyResult );

		return $typeLookup;
	}

	/**
	 * @param string $dataType
	 * @param string $valueType
	 *
	 * @return DataTypeFactory
	 */
	private function getMockDataTypeFactory( $dataType, $valueType ) {
		if ( $valueType !== '' ) {
			$getValueTypeIdForPropertyResult = $this->returnValue( new DataType( $dataType, $valueType ) );
		} else {
			$getValueTypeIdForPropertyResult = $this->throwException(
				new OutOfBoundsException( 'unknown datatype ' . $dataType ) );
		}

		$typeFactory = $this->getMockBuilder( 'DataTypes\DataTypeFactory' )
			->disableOriginalConstructor()
			->getMock();

		$typeFactory->expects( $this->any() )
			->method( 'getType' )
			->will( $getValueTypeIdForPropertyResult );

		return $typeFactory;
	}

	/**
	 * @dataProvider formatSnakProvider
	 */
	public function testFormatSnak(
		$snak, $dataType, $valueType, $targetFormat, ValueFormatter $formatter,
		$expected, $expectedException = null
	) {
		if ( $expectedException !== null ) {
			$this->setExpectedException( $expectedException );
		}

		$typeLookup = $this->getMockDataTypeLookup( $dataType );
		$typeFactory = $this->getMockDataTypeFactory( $dataType, $valueType );

		$options = new FormatterOptions( array(
			PropertyValueSnakFormatter::OPT_LANG => 'en',
		) );

		$formatter = new PropertyValueSnakFormatter(
			$targetFormat,
			$options,
			$formatter,
			$typeLookup,
			$typeFactory
		);

		$actual = $formatter->formatSnak( $snak );

		$this->assertRegExp( $expected, $actual );
	}

	private function getMockFormatter( $value ) {
		$formatter = $this->getMock( 'ValueFormatters\ValueFormatter' );
		$formatter->expects( $this->any() )
			->method( 'format' )
			->will( $this->returnValue( $value ) );

		return $formatter;
	}

	public function formatSnakProvider() {
		$formatters = array(
			'VT:bad' => new UnDeserializableValueFormatter( new FormatterOptions() ),
			'VT:string' => $this->getMockFormatter( 'VT:string' ),
			'PT:commonsMedia' => $this->getMockFormatter( 'PT:commonsMedia' )
		);

		$dispatchingFormatter = new DispatchingValueFormatter( $formatters );

		return array(
			'match PT' => array(
				new PropertyValueSnak( 17, new StringValue( 'Foo.jpg' ) ),
				'commonsMedia',
				'string',
				SnakFormatter::FORMAT_PLAIN,
				$dispatchingFormatter,
				'/^PT:commonsMedia$/'
			),

			'match VT' => array(
				new PropertyValueSnak( 33, new StringValue( 'something' ) ),
				'someStuff',
				'string',
				SnakFormatter::FORMAT_WIKI,
				$dispatchingFormatter,
				'/^VT:string$/'
			),

			'use plain value formatter' => array(
				new PropertyValueSnak( 33, new StringValue( 'something' ) ),
				'url',
				'string',
				SnakFormatter::FORMAT_WIKI,
				new StringFormatter(),
				'/^something$/'
			),

			'UnDeserializableValue, fail' => array(
				new PropertyValueSnak( 7,
					new UnDeserializableValue( 'cookie', 'globecoordinate', 'cannot understand!' )
				),
				'globe-coordinate',
				'globecoordinate',
				SnakFormatter::FORMAT_HTML,
				$dispatchingFormatter,
				null,
				'ValueFormatters\Exceptions\MismatchingDataValueTypeException'
			),

			'VT mismatching PT, fail' => array(
				new PropertyValueSnak( 7, new StringValue( 'dummy' ) ),
				'url',
				'iri', // url expects an iri, but will get a string
				SnakFormatter::FORMAT_WIKI,
				$dispatchingFormatter,
				null,
				'ValueFormatters\Exceptions\MismatchingDataValueTypeException'
			),

			'property not found, fail' => array(
				new PropertyValueSnak( 7, new StringValue( 'dummy' ) ),
				'', // triggers an exception from the mock DataTypeFactory
				'xxx', // should not be used
				SnakFormatter::FORMAT_HTML,
				$dispatchingFormatter,
				null,
				'Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookupException'
			),

			'data type not found, fail' => array(
				new PropertyValueSnak( 7, new StringValue( 'dummy' ) ),
				'url',
				'', // triggers an exception from the mock DataTypeFactory
				SnakFormatter::FORMAT_HTML,
				$dispatchingFormatter,
				null,
				'ValueFormatters\FormattingException'
			),
		);
	}

	private function getDummyPropertyValueSnakFormatter( $format = 'test' ) {
		$typeLookup = $this->getMock( 'Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup' );
		$typeLookup->expects( $this->never() )->method( 'getDataTypeIdForProperty' );

		$typeFactory = $this->getMockBuilder( 'DataTypes\DataTypeFactory' )
			->disableOriginalConstructor()
			->getMock();

		$typeFactory->expects( $this->never() )->method( 'getType' );

		$valueFormatter = new DispatchingValueFormatter( array() );

		$options = new FormatterOptions( array() );

		$formatter = new PropertyValueSnakFormatter( $format, $options, $valueFormatter, $typeLookup, $typeFactory );
		return $formatter;
	}

	public function testGetFormat() {
		$formatter = $this->getDummyPropertyValueSnakFormatter();
		$this->assertEquals( 'test', $formatter->getFormat() );
	}

}
