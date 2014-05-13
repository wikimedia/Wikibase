<?php

namespace Wikibase\Formatters\Test;

use DataValues\MonolingualTextValue;
use ValueFormatters\FormatterOptions;
use Wikibase\Formatters\MonolingualTextFormatter;

/**
 * @covers Wikibase\Formatters\MonolingualTextFormatter
 *
 * @group ValueFormatters
 * @group DataValueExtensions
 * @group WikibaseLib
 * @group Wikibase
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class MonolingualTextFormatterTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @dataProvider urlFormatProvider
	 *
	 * @covers HtmlUrlFormatter::format()
	 */
	public function testFormat( $value, $options, $pattern ) {
		$formatter = new MonolingualTextFormatter( $options );

		$text = $formatter->format( $value );
		$this->assertRegExp( $pattern, $text );
	}

	public function urlFormatProvider() {
		$options = new FormatterOptions();

		return array(
			array(
				new MonolingualTextValue( 'de', 'Hallo Welt' ),
				$options,
				'@^Hallo Welt$@'
			),
		);
	}

}
