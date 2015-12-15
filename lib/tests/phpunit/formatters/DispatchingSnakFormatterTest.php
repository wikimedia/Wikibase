<?php

namespace Wikibase\Lib\Test;

use DataValues\StringValue;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\Lib\DispatchingSnakFormatter;
use Wikibase\Lib\MessageSnakFormatter;
use Wikibase\Lib\SnakFormatter;

/**
 * @covers Wikibase\Lib\DispatchingSnakFormatter
 *
 * @group ValueFormatters
 * @group DataValueExtensions
 * @group WikibaseLib
 * @group Wikibase
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class DispatchingSnakFormatterTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @param string $dataType
	 *
	 * @return PropertyDataTypeLookup
	 */
	private function getDataTypeLookup( $dataType = 'string' ) {
		$dataTypeLookup = $this->getMock( 'Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup' );

		$dataTypeLookup->expects( $this->any() )
			->method( 'getDataTypeIdForProperty' )
			->will( $this->returnValue( $dataType ) );

		return $dataTypeLookup;
	}

	/**
	 * @param string $output the return value for formatSnak
	 * @param string $format the return value for getFormat
	 *
	 * @return SnakFormatter
	 */
	private function makeSnakFormatter( $output, $format = SnakFormatter::FORMAT_PLAIN ) {
		$formatter = $this->getMock( 'Wikibase\Lib\SnakFormatter' );

		$formatter->expects( $this->any() )
			->method( 'formatSnak' )
			->will( $this->returnValue( $output ) );

		$formatter->expects( $this->any() )
			->method( 'getFormat' )
			->will( $this->returnValue( $format ) );

		return $formatter;
	}

	/**
	 * @dataProvider constructorProvider
	 */
	public function testConstructor( $format, array $formattersBySnakType, array $formattersByDataType ) {
		$dataTypeLookup = $this->getDataTypeLookup();

		new DispatchingSnakFormatter(
			$format,
			$dataTypeLookup,
			$formattersBySnakType,
			$formattersByDataType
		);

		// we are just checking that the constructor did not throw an exception
		$this->assertTrue( true );
	}

	public function constructorProvider() {
		$formatter = new MessageSnakFormatter(
			'novalue',
			wfMessage( 'wikibase-snakview-snaktypeselector-novalue' ),
			SnakFormatter::FORMAT_HTML_DIFF
		);

		return array(
			'plain constructor call' => array(
				SnakFormatter::FORMAT_HTML_DIFF,
				array( 'novalue' => $formatter ),
				array( 'string' => $formatter ),
			),
			'constructor call with formatters for base format ID' => array(
				SnakFormatter::FORMAT_HTML,
				array( 'novalue' => $formatter ),
				array( 'string' => $formatter ),
			),
		);
	}

	/**
	 * @dataProvider constructorErrorsProvider
	 */
	public function testConstructorErrors( $format, array $formattersBySnakType, array $formattersByDataType ) {
		$this->setExpectedException( 'InvalidArgumentException' );

		$dataTypeLookup = $this->getDataTypeLookup();

		new DispatchingSnakFormatter(
			$format,
			$dataTypeLookup,
			$formattersBySnakType,
			$formattersByDataType
		);
	}

	public function constructorErrorsProvider() {
		$formatter = new MessageSnakFormatter(
			'novalue',
			wfMessage( 'wikibase-snakview-snaktypeselector-novalue' ),
			SnakFormatter::FORMAT_PLAIN
		);

		return array(
			'format must be a string' => array(
				17,
				array(),
				array(),
			),
			'snak types must be strings' => array(
				SnakFormatter::FORMAT_PLAIN,
				array( 17 => $formatter ),
				array( 'string' => $formatter ),
			),
			'data types must be strings' => array(
				SnakFormatter::FORMAT_PLAIN,
				array(),
				array( 17 => $formatter ),
			),
			'snak type formatters must be SnakFormatters' => array(
				SnakFormatter::FORMAT_PLAIN,
				array( 'novalue' => 17 ),
				array( 'string' => $formatter ),
			),
			'data type formatters must be SnakFormatters' => array(
				SnakFormatter::FORMAT_PLAIN,
				array(),
				array( 'string' => 17 ),
			),
			'snak type formatters mismatches output format' => array(
				SnakFormatter::FORMAT_HTML,
				array( 'novalue' => $formatter ),
				array( 'string' => $formatter ),
			),
			'data type formatters mismatches output format' => array(
				SnakFormatter::FORMAT_HTML,
				array(),
				array( 'string' => $formatter ),
			),
		);
	}

	public function provideFormatSnak() {
		$p23 = new PropertyId( 'P23' );

		return array(
			'novalue' => array(
				'NO VALUE',
				new PropertyNoValueSnak( $p23 ),
				'string'
			),
			'somevalue' => array(
				'SOME VALUE',
				new PropertySomeValueSnak( $p23 ),
				'string'
			),
			'string value' => array(
				'STRING VALUE',
				new PropertyValueSnak( $p23, new StringValue( 'dummy' ) ),
				'string'
			),
			'other value' => array(
				'OTHER VALUE',
				new PropertyValueSnak( $p23, new StringValue( 'dummy' ) ),
				'url'
			),
		);
	}

	/**
	 * @dataProvider provideFormatSnak
	 */
	public function testFormatSnak( $expected, Snak $snak, $dataType ) {
		$formattersBySnakType = array(
			'novalue' => $this->makeSnakFormatter( 'NO VALUE' ),
			'somevalue' => $this->makeSnakFormatter( 'SOME VALUE' ),
		);

		$formattersByDataType = array(
			'string' => $this->makeSnakFormatter( 'STRING VALUE' ),
			'*' => $this->makeSnakFormatter( 'OTHER VALUE' ),
		);

		$formatter = new DispatchingSnakFormatter(
			SnakFormatter::FORMAT_PLAIN,
			$this->getDataTypeLookup( $dataType ),
			$formattersBySnakType,
			$formattersByDataType
		);

		$this->assertEquals( $expected, $formatter->formatSnak( $snak ) );
	}

	public function testGetFormat() {
		$formatter = new DispatchingSnakFormatter( 'test', $this->getDataTypeLookup(), array(), array() );
		$this->assertEquals( 'test', $formatter->getFormat() );
	}

}
