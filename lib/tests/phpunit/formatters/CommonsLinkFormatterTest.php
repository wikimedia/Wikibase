<?php

namespace Wikibase\Lib\Test;

use DataValues\NumberValue;
use DataValues\StringValue;
use ValueFormatters\FormatterOptions;
use Wikibase\Lib\CommonsLinkFormatter;

/**
 * @covers Wikibase\Lib\CommonsLinkFormatter
 *
 * @group ValueFormatters
 * @group DataValueExtensions
 * @group WikibaseLib
 * @group Wikibase
 * @group Database
 *
 * @license GPL-2.0+
 * @author Adrian Lang
 */
class CommonsLinkFormatterTest extends \MediaWikiTestCase {

	public function commonsLinkFormatProvider() {
		return array(
			array(
				new StringValue( 'example.jpg' ), // Lower-case file name
				'@<a .*href="//commons.wikimedia.org/wiki/File:Example.jpg".*>.*Example.jpg.*</a>@'
			),
			array(
				new StringValue( 'Example.jpg' ),
				'@<a .*href="//commons.wikimedia.org/wiki/File:Example.jpg".*>.*Example.jpg.*</a>@'
			),
			array(
				new StringValue( 'Example space.jpg' ),
				'@<a .*href="//commons.wikimedia.org/wiki/File:Example_space.jpg".*>.*Example space.jpg.*</a>@'
			),
			array(
				new StringValue( 'Example_underscore.jpg' ),
				'@<a .*href="//commons.wikimedia.org/wiki/File:Example_underscore.jpg".*>.*Example underscore.jpg.*</a>@'
			),
			array(
				new StringValue( 'Example+plus.jpg' ),
				'@<a .*href="//commons.wikimedia.org/wiki/File:Example%2Bplus.jpg".*>.*Example\+plus.jpg.*</a>@'
			),
			array(
				new StringValue( '[[File:Invalid_title.mid]]' ),
				'@^\[\[File:Invalid_title.mid\]\]$@'
			),
			array(
				new StringValue( '<a onmouseover=alert(0xF000)>ouch</a>' ),
				'@^&lt;a onmouseover=alert\(0xF000\)&gt;ouch&lt;/a&gt;$@'
			),
			array(
				new StringValue( '' ),
				'@^$@'
			),
		);
	}

	/**
	 * @dataProvider commonsLinkFormatProvider
	 */
	public function testFormat( StringValue $value, $pattern, FormatterOptions $options = null ) {
		$formatter = new CommonsLinkFormatter( $options );

		$html = $formatter->format( $value );
		$this->assertRegExp( $pattern, $html );
	}

	public function testFormatError() {
		$formatter = new CommonsLinkFormatter();
		$value = new NumberValue( 23 );

		$this->setExpectedException( 'InvalidArgumentException' );
		$formatter->format( $value );
	}

}
