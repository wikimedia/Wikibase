<?php

namespace Wikibase\Lib\Formatters\Test;

use DataValues\StringValue;
use DataValues\UnDeserializableValue;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Lib\Formatters\WikitextExternalIdentifierFormatter;
use Wikibase\Lib\SnakUrlExpander;

/**
 * @covers Wikibase\Lib\Formatters\WikitextExternalIdentifierFormatter
 *
 * @group ValueFormatters
 * @group DataValueExtensions
 * @group WikibaseLib
 * @group Wikibase
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class WikitextExternalIdentifierFormatterTest extends \PHPUnit_Framework_TestCase {

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
				new PropertyValueSnak( new PropertyId( 'P1' ), new StringValue( 'abc\'\'123' ) ),
				'[http://acme.test/stuff/abc%27%27123 abc&#39;&#39;123]'
			),
			'unknown property' => array(
				$formatterUrlExpander,
				new PropertyValueSnak( new PropertyId( 'P2' ), new StringValue( 'abc\'\'123' ) ),
				'abc&#39;&#39;123'
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
		$formatter = new WikitextExternalIdentifierFormatter( $urlExpander );
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
		$formatter = new WikitextExternalIdentifierFormatter( $urlExpander );

		$this->setExpectedException( 'Wikimedia\Assert\ParameterTypeException' );
		$formatter->formatSnak( $snak );
	}

}
