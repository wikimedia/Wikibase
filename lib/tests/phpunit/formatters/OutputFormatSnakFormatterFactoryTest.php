<?php

namespace Wikibase\Lib\Test;

use DataTypes\DataTypeFactory;
use DataValues\DataValue;
use DataValues\StringValue;
use Language;
use ValueFormatters\FormatterOptions;
use ValueFormatters\StringFormatter;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\OutputFormatSnakFormatterFactory;
use Wikibase\Lib\OutputFormatValueFormatterFactory;
use Wikibase\Lib\SnakFormatter;

/**
 * @covers Wikibase\Lib\OutputFormatSnakFormatterFactory
 *
 * @group ValueFormatters
 * @group DataValueExtensions
 * @group WikibaseLib
 * @group Wikibase
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class OutputFormatSnakFormatterFactoryTest extends \PHPUnit_Framework_TestCase {

	private function newOutputFormatSnakFormatterFactory( $dataType = 'string' ) {
		$snakFormatterCallbacks = array(
			'PT:commonsMedia' => function( $format, FormatterOptions $options ) {
				return $this->makeMockSnakFormatter( $format );
			},
		);

		$valueFormatterCallbacks = array(
			'VT:string' => function( $format, FormatterOptions $options ) {
				return $this->makeMockValueFormatter( $format );
			},
		);
		$valueFormatterFactory = new OutputFormatValueFormatterFactory(
			$valueFormatterCallbacks,
			Language::factory( 'en' ),
			new LanguageFallbackChainFactory()
		);

		$dataTypeLookup = $this->getMock(
			'Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup'
		);
		$dataTypeLookup->expects( $this->any() )
			->method( 'getDataTypeIdForProperty' )
			->will( $this->returnValue( $dataType ) );

		return new OutputFormatSnakFormatterFactory(
			$snakFormatterCallbacks,
			$valueFormatterFactory,
			$dataTypeLookup,
			new DataTypeFactory( array( 'string' => 'string', 'commonsMedia' => 'string' ) )
		);
	}

	public function makeMockValueFormatter( $format ) {
		$mock = $this->getMock( 'ValueFormatters\ValueFormatter' );

		$mock->expects( $this->any() )
			->method( 'format' )
			->will( $this->returnCallback(
				function( DataValue $value ) use ( $format ) {
					return strval( $value->getValue() ) . ' (' . $format . ')';
				}
			) );

		return $mock;
	}

	public function makeMockSnakFormatter( $format ) {
		$mock = $this->getMock( 'Wikibase\Lib\SnakFormatter' );

		$mock->expects( $this->any() )
			->method( 'formatSnak' )
			->will( $this->returnCallback(
				function( Snak $snak ) use ( $format ) {
					$s = $snak->getType() . '/' . $snak->getPropertyId();

					if ( $snak instanceof PropertyValueSnak ) {
						$s .= '=' . strval( $snak->getDataValue()->getValue() );
					}

					return $s . ' (' . $format . ')';
				}
			) );

		$mock->expects( $this->any() )
			->method( 'getFormat' )
			->will( $this->returnValue( $format ) );

		return $mock;
	}

	public function getSnakFormatterProvider() {
		return array(
			'plain value' => array(
				SnakFormatter::FORMAT_PLAIN,
				'string',
				new StringValue( 'foo' ),
				'foo (text/plain)',
			),
			'html value' => array(
				SnakFormatter::FORMAT_HTML,
				'string',
				new StringValue( 'foo' ),
				'foo (text/html)',
			),
			'plain snak' => array(
				SnakFormatter::FORMAT_PLAIN,
				'commonsMedia', // the mock has a SnakFormatter for commonsMedia
				new StringValue( 'foo.jpg' ),
				'value/P5=foo.jpg (text/plain)',
			),
			'html snak' => array(
				SnakFormatter::FORMAT_HTML,
				'commonsMedia', // the mock has a SnakFormatter for commonsMedia
				new StringValue( 'foo.jpg' ),
				'value/P5=foo.jpg (text/html)',
			),
		);
	}

	/**
	 * @dataProvider getSnakFormatterProvider
	 */
	public function testGetSnakFormatter( $format, $dataType, DataValue $value, $expected ) {
		$factory = $this->newOutputFormatSnakFormatterFactory( $dataType );
		$formatter = $factory->getSnakFormatter( $format, new FormatterOptions() );

		$this->assertInstanceOf( 'Wikibase\Lib\SnakFormatter', $formatter );
		$this->assertEquals( $format, $formatter->getFormat() );

		$snak = new PropertyValueSnak( new PropertyId( 'P5' ), $value );
		$this->assertEquals( $expected, $formatter->formatSnak( $snak ) );
	}

	public function getSnakFormatterProvider_options() {
		return array(
			'default' => array(
				array(),
				'Wikibase\Lib\Formatters\ErrorHandlingSnakFormatter'
			),
			'OPT_ON_ERROR => ON_ERROR_WARN' => array(
				array( SnakFormatter::OPT_ON_ERROR => SnakFormatter::ON_ERROR_WARN ),
				'Wikibase\Lib\Formatters\ErrorHandlingSnakFormatter'
			),
			'OPT_ON_ERROR => ON_ERROR_FAIL' => array(
				array( SnakFormatter::OPT_ON_ERROR => SnakFormatter::ON_ERROR_FAIL ),
				'Wikibase\Lib\DispatchingSnakFormatter'
			),
		);
	}

	/**
	 * @dataProvider getSnakFormatterProvider_options
	 */
	public function testGetSnakFormatter_options( array $options, $expectedType ) {
		$factory = $this->newOutputFormatSnakFormatterFactory();
		$formatter = $factory->getSnakFormatter(
			SnakFormatter::FORMAT_WIKI,
			new FormatterOptions( $options )
		);

		$this->assertInstanceOf( $expectedType, $formatter );
	}

	public function testGetSnakFormatter_languageOption() {
		$callbacks = array(
			'VT:string' => function( $format, FormatterOptions $options ) {
				$this->assertSame( 'de', $options->getOption( ValueFormatter::OPT_LANG ) );
				return new StringFormatter( $options );
			},
		);
		$valueFormatterFactory = new OutputFormatValueFormatterFactory(
			$callbacks,
			Language::factory( 'de' ),
			new LanguageFallbackChainFactory()
		);

		$factory = new OutputFormatSnakFormatterFactory(
			array(),
			$valueFormatterFactory,
			$this->getMock( 'Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup' ),
			new DataTypeFactory( array() )
		);
		$factory->getSnakFormatter( SnakFormatter::FORMAT_PLAIN, new FormatterOptions() );
	}

}
