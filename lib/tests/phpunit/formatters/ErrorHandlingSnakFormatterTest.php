<?php

namespace Wikibase\Lib\Formatters\Test;

use DataValues\StringValue;
use DataValues\UnDeserializableValue;
use Exception;
use Language;
use ValueFormatters\Exceptions\MismatchingDataValueTypeException;
use ValueFormatters\FormatterOptions;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookupException;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\Lib\Formatters\ErrorHandlingSnakFormatter;
use Wikibase\Lib\SnakFormatter;

/**
 * @covers Wikibase\Lib\ErrorHandlingSnakFormatter
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
class ErrorHandlingSnakFormatterTest extends \MediaWikiTestCase {

	/**
	 * @param Exception|null $throw
	 *
	 * @return SnakFormatter
	 */
	private function getSnakFormatter( $throw = null ) {
		$formatter = $this->getMock( 'Wikibase\Lib\SnakFormatter' );

		$formatter->expects( $this->any() )
			->method( 'getFormat' )
			->will( $this->returnValue( SnakFormatter::FORMAT_HTML ) );

		if ( $throw ) {
			$formatter->expects( $this->any() )
				->method( 'formatSnak' )
				->will( $this->throwException( $throw ) );
		} else {
			$formatter->expects( $this->any() )
				->method( 'formatSnak' )
				->will( $this->returnValue( 'SNAK' ) );
		}

		return $formatter;
	}

	/**
	 * @return ValueFormatter
	 */
	private function getValueFormatter() {
		$formatter = $this->getMock( 'ValueFormatters\ValueFormatter' );

		$formatter->expects( $this->any() )
			->method( 'format' )
			->will( $this->returnValue( 'VALUE' ) );

		return $formatter;
	}

	public function testFormatSnak_good() {
		$formatter = new ErrorHandlingSnakFormatter( $this->getSnakFormatter(), null, null );

		$snak = new PropertyNoValueSnak(
			new PropertyId( 'P1' )
		);

		$text = $formatter->formatSnak( $snak );
		$this->assertEquals( 'SNAK', $text );
	}

	public function provideFormatSnak_error() {
		$p1 = new PropertyId( 'P1' );

		$valueFormatter = $this->getValueFormatter();

		return array(
			'MismatchingDataValueTypeException' => array(
				'<span class="error wb-format-error">(wikibase-snakformatter-valuetype-mismatch: string, number)</span>',
				new PropertyValueSnak( $p1, new StringValue( 'foo' ) ),
				new MismatchingDataValueTypeException( 'number', 'string' ),
			),
			'MismatchingDataValueTypeException+fallback' => array(
				'VALUE <span class="error wb-format-error">(wikibase-snakformatter-valuetype-mismatch: string, number)</span>',
				new PropertyValueSnak( $p1, new StringValue( 'foo' ) ),
				new MismatchingDataValueTypeException( 'number', 'string' ),
				$valueFormatter
			),
			'MismatchingDataValueTypeException+UnDeserializableValue' => array(
				'<span class="error wb-format-error">(wikibase-undeserializable-value)</span>',
				new PropertyValueSnak( $p1, new UnDeserializableValue( 'string', 'XYZ', 'test' ) ),
				new MismatchingDataValueTypeException( 'number', UnDeserializableValue::getType() ),
			),
			'MismatchingDataValueTypeException+UnDeserializableValue+fallback' => array(
				'VALUE <span class="error wb-format-error">(wikibase-undeserializable-value)</span>',
				new PropertyValueSnak( $p1, new UnDeserializableValue( 'string', 'XYZ', 'test' ) ),
				new MismatchingDataValueTypeException( 'number', UnDeserializableValue::getType() ),
				$valueFormatter
			),
			'PropertyDataTypeLookupException' => array(
				'<span class="error wb-format-error">(wikibase-snakformatter-property-not-found: P1)</span>',
				new PropertyValueSnak( $p1, new StringValue( 'foo' ) ),
				new PropertyDataTypeLookupException( new PropertyId( 'P1' ) ),

			),
			'PropertyDataTypeLookupException+fallback' => array(
				'VALUE <span class="error wb-format-error">(wikibase-snakformatter-property-not-found: P1)</span>',
				new PropertyValueSnak( $p1, new StringValue( 'foo' ) ),
				new PropertyDataTypeLookupException( new PropertyId( 'P1' ) ),
				$valueFormatter
			),
		);
	}

	/**
	 * @dataProvider provideFormatSnak_error
	 */
	public function testFormatSnak_error(
		$expected,
		PropertyValueSnak $snak,
		Exception $ex,
		ValueFormatter $fallbackFormatter = null
	) {
		$formatter = new ErrorHandlingSnakFormatter(
			$this->getSnakFormatter( $ex ),
			$fallbackFormatter,
			Language::factory( 'qqx' )
		);

		$text = $formatter->formatSnak( $snak );
		$this->assertEquals( $expected, $text );
	}

}
