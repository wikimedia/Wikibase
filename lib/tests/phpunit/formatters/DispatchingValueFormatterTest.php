<?php
namespace Wikibase\Lib\Test;

use DataValues\StringValue;
use ValueFormatters\FormatterOptions;
use ValueFormatters\StringFormatter;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\DispatchingValueFormatter;
use Wikibase\Lib\SnakFormatter;

/**
 * @covers Wikibase\Lib\DispatchingValueFormatter
 *
 * @since 0.5
 *
 * @ingroup WikibaseLibTest
 *
 * @group ValueFormatters
 * @group DataValueExtensions
 * @group WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class DispatchingValueFormatterTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @dataProvider constructorErrorsProvider
	 *
	 * @param $format
	 * @param $formatters
	 * @param $error
	 */
	public function testConstructorErrors( $formatters, $error ) {
		$this->setExpectedException( $error );

		new DispatchingValueFormatter( $formatters );
	}

	public function constructorErrorsProvider() {
		$stringFormatter = new StringFormatter( new FormatterOptions() );

		return array(
			'keys must be strings' => array(
				array( 17 => $stringFormatter ),
				'InvalidArgumentException'
			),
			'keys must have prefix' => array(
				array( 'foo' => $stringFormatter ),
				'InvalidArgumentException'
			),
			'formatters must be instances of ValueFormatter' => array(
				array( 'novalue' => 17 ),
				'InvalidArgumentException'
			),
		);
	}

	/**
	 * @dataProvider formatProvider
	 * @covers DispatchingValueFormatter::format()
	 */
	public function testFormat( $value, $formatters, $expected ) {
		$formatter = new DispatchingValueFormatter(
			$formatters
		);

		$this->assertEquals( $expected, $formatter->format( $value ) );
	}

	public function formatProvider() {
		$stringFormatter = $this->getMock( 'ValueFormatters\ValueFormatter' );
		$stringFormatter->expects( $this->any() )
			->method( 'format' )
			->will( $this->returnValue( 'VT:string' ) );

		$mediaFormatter = $this->getMock( 'ValueFormatters\ValueFormatter' );
		$mediaFormatter->expects( $this->any() )
			->method( 'format' )
			->will( $this->returnValue( 'VT:wikibase-entityid' ) );

		$formatters = array(
			'VT:string' => $stringFormatter,
			'VT:wikibase-entityid' => $mediaFormatter,
		);

		return array(
			'match PT' => array(
				new EntityIdValue( new ItemId( 'Q13' ) ),
				$formatters,
				'VT:wikibase-entityid'
			),

			'match VT' => array(
				new StringValue( 'something' ),
				$formatters,
				'VT:string'
			),
		);
	}

	/**
	 * @dataProvider formatValueProvider
	 * @covers DispatchingValueFormatter::formatValue()
	 */
	public function testFormatValue( $value, $type, $formatters, $expected ) {
		$formatter = new DispatchingValueFormatter(
			$formatters
		);

		$this->assertEquals( $expected, $formatter->formatValue( $value, $type ) );
	}

	public function formatValueProvider() {
		$stringFormatter = $this->getMock( 'ValueFormatters\ValueFormatter' );
		$stringFormatter->expects( $this->any() )
			->method( 'format' )
			->will( $this->returnValue( 'VT:string' ) );

		$mediaFormatter = $this->getMock( 'ValueFormatters\ValueFormatter' );
		$mediaFormatter->expects( $this->any() )
			->method( 'format' )
			->will( $this->returnValue( 'PT:commonsMedia' ) );

		$formatters = array(
			'VT:string' => $stringFormatter,
			'PT:commonsMedia' => $mediaFormatter,
		);

		return array(
			'match PT' => array(
				new StringValue( 'Foo.jpg' ),
				'commonsMedia',
				$formatters,
				'PT:commonsMedia'
			),

			'match VT' => array(
				new StringValue( 'something' ),
				'someStuff',
				$formatters,
				'VT:string'
			),
		);
	}
}
