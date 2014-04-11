<?php

namespace Wikibase\Lib\Test;

use DataValues\NumberValue;
use DataValues\StringValue;
use ValueFormatters\Exceptions\MismatchingDataValueTypeException;
use ValueFormatters\FormatterOptions;
use Wikibase\Lib\HtmlUrlFormatter;

/**
 * @covers Wikibase\Lib\HtmlUrlFormatter
 *
 * @group ValueFormatters
 * @group DataValueExtensions
 * @group WikibaseLib
 * @group Wikibase
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class HtmlUrlFormatterTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @dataProvider urlFormatProvider
	 *
	 * @covers HtmlUrlFormatter::format()
	 */
	public function testFormat( $value, $options, $pattern ) {
		$formatter = new HtmlUrlFormatter( $options );

		$html = $formatter->format( $value );
		$this->assertRegExp( $pattern, $html );
	}

	public function urlFormatProvider() {
		$options = new FormatterOptions();

		return array(
			array(
				new StringValue( 'http://acme.com' ),
				$options,
				'@<a .*href="http://acme\.com".*>.*http://acme\.com.*</a>@'
			),
		);
	}

	/**
	 * @covers HtmlUrlFormatter::format()
	 */
	public function testFormatError() {
		$formatter = new HtmlUrlFormatter( new FormatterOptions() );
		$value = new NumberValue( 23 );

		$this->setExpectedException(
			'ValueFormatters\Exceptions\MismatchingDataValueTypeException'
		);

		$formatter->format( $value );
	}
}
