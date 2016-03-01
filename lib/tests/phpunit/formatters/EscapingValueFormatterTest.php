<?php

namespace Wikibase\Lib\Test;

use DataValues\StringValue;
use ValueFormatters\FormatterOptions;
use ValueFormatters\StringFormatter;
use Wikibase\Lib\EscapingValueFormatter;

/**
 * @covers Wikibase\Lib\EscapingValueFormatter
 *
 * @group ValueFormatters
 * @group DataValueExtensions
 * @group WikibaseLib
 * @group Wikibase
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class EscapingValueFormatterTest extends \PHPUnit_Framework_TestCase {

	public function testFormat() {
		$formatter = new EscapingValueFormatter( new StringFormatter( new FormatterOptions() ), 'htmlspecialchars' );
		$value = new StringValue( '3 < 5' );

		$this->assertEquals( '3 &lt; 5', $formatter->format( $value ) );
	}

}
