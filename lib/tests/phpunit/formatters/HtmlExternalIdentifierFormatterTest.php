<?php

namespace Wikibase\Lib\Formatters\Test;

use DataValues\StringValue;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Lib\Formatters\HtmlExternalIdentifierFormatter;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Lib\SnakUrlExpander;

/**
 * @covers Wikibase\Lib\Formatters\HtmlExternalIdentifierFormatter
 *
 * @group ValueFormatters
 * @group DataValueExtensions
 * @group WikibaseLib
 * @group Wikibase
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class HtmlExternalIdentifierFormatterTest extends \PHPUnit_Framework_TestCase {

	public function provideFormatSnak() {
		$formatterUrlExpander = $this->getMock( 'Wikibase\Lib\SnakUrlExpander' );

		$formatterUrlExpander->expects( $this->any() )
			->method( 'expandUrl' )
			->will( $this->returnCallback( function( PropertyValueSnak $snak ) {
				$id = $snak->getDataValue()->getValue();

				if ( $snak->getPropertyId()->getSerialization() === 'P1' ) {
					return 'http://acme.test/stuff/' . urlencode( $id );
				} else {
					return null;
				}
			} ) );

		return array(
			'formatter URL' => array(
				$formatterUrlExpander,
				new PropertyValueSnak( new PropertyId( 'P1' ), new StringValue( 'abc&123' ) ),
				'<a class="wb-external-id" href="http://acme.test/stuff/abc%26123">abc&amp;123</a>'
			),
			'unknown property' => array(
				$formatterUrlExpander,
				new PropertyValueSnak( new PropertyId( 'P2' ), new StringValue( 'abc&123' ) ),
				'<span class="wb-external-id">abc&amp;123</span>'
			),
		);
	}

	/**
	 * @dataProvider provideFormatSnak
	 */
	public function testFormatSnak(
		SnakUrlExpander $urlExpander,
		PropertyValueSnak $snak,
		$expected
	) {
		$formatter = new HtmlExternalIdentifierFormatter( $urlExpander );
		$text = $formatter->formatSnak( $snak );
		$this->assertEquals( $expected, $text );
	}

	public function provideFormatSnak_ParameterTypeException() {
		return array(
			'bad snak type' => array(
				new PropertyNoValueSnak( new PropertyId( 'P7' ) )
			),
		);
	}

	/**
	 * @dataProvider provideFormatSnak_ParameterTypeException
	 */
	public function testFormatSnak_ParameterTypeException( $snak ) {
		$urlExpander = $this->getMock( 'Wikibase\Lib\SnakUrlExpander' );
		$formatter = new HtmlExternalIdentifierFormatter( $urlExpander );

		$this->setExpectedException( 'Wikimedia\Assert\ParameterTypeException' );
		$formatter->formatSnak( $snak );
	}

	public function testGetFormat() {
		$urlExpander = $this->getMock( 'Wikibase\Lib\SnakUrlExpander' );
		$formatter = new HtmlExternalIdentifierFormatter( $urlExpander );

		$this->assertSame( SnakFormatter::FORMAT_HTML, $formatter->getFormat() );
	}

}
