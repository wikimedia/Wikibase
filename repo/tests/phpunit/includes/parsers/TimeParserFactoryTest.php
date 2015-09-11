<?php

namespace Wikibase\Lib\Tests\Parsers;

use DataValues\TimeValue;
use Language;
use PHPUnit_Framework_TestCase;
use ValueParsers\ParserOptions;
use ValueParsers\ValueParser;
use Wikibase\Lib\Parsers\TimeParserFactory;

/**
 * @covers Wikibase\Lib\Parsers\TimeParserFactory
 *
 * @group ValueParsers
 * @group WikibaseLib
 * @group Wikibase
 * @group TimeParsers
 *
 * @licence GNU GPL v2+
 * @author Adam Shorland
 * @author Thiemo Mättig
 */
class TimeParserFactoryTest extends PHPUnit_Framework_TestCase {

	public function testGetTimeParser() {
		$factory = new TimeParserFactory();
		$parser = $factory->getTimeParser();

		$this->assertInstanceOf( 'ValueParsers\ValueParser', $parser );
	}

	/**
	 * @dataProvider validInputProvider
	 */
	public function testParse( $value, TimeValue $expected ) {
		$factory = new TimeParserFactory();
		$parser = $factory->getTimeParser();

		$this->assertTrue( $expected->equals( $parser->parse( $value ) ) );
	}

	public function validInputProvider() {
		$gregorian = 'http://www.wikidata.org/entity/Q1985727';
		$julian = 'http://www.wikidata.org/entity/Q1985786';

		$valid = array(
			/**
			 * @see Wikibase\Lib\Parsers\YearTimeParser
			 * @see Wikibase\Lib\Parsers\Test\YearTimeParserTest
			 */
			'1999' =>
				array( '+1999-00-00T00:00:00Z', TimeValue::PRECISION_YEAR ),
			'2000' =>
				array( '+2000-00-00T00:00:00Z', TimeValue::PRECISION_YEAR ),
			'2010' =>
				array( '+2010-00-00T00:00:00Z', TimeValue::PRECISION_YEAR ),
			'1980 ' =>
				array( '+1980-00-00T00:00:00Z', TimeValue::PRECISION_YEAR ),
			'1' =>
				array( '+0001-00-00T00:00:00Z', TimeValue::PRECISION_YEAR, $julian ),
			'-1000000001' =>
				array( '-1000000001-00-00T00:00:00Z', TimeValue::PRECISION_YEAR, $julian ),
			'+1000000001' =>
				array( '+1000000001-00-00T00:00:00Z', TimeValue::PRECISION_YEAR ),
			'1BC' =>
				array( '-0001-00-00T00:00:00Z', TimeValue::PRECISION_YEAR, $julian ),
			'1CE' =>
				array( '+0001-00-00T00:00:00Z', TimeValue::PRECISION_YEAR, $julian ),
			'1 1999 BC' =>
				array( '-11999-00-00T00:00:00Z', TimeValue::PRECISION_YEAR, $julian ),
			'1,000,000 BC' =>
				array( '-1000000-00-00T00:00:00Z', TimeValue::PRECISION_YEAR1M, $julian ),

			/**
			 * @see Wikibase\Lib\Parsers\YearMonthTimeParser
			 * @see Wikibase\Lib\Parsers\Test\YearMonthTimeParserTest
			 */
			'1 1999' =>
				array( '+1999-01-00T00:00:00Z', TimeValue::PRECISION_MONTH ),
			'March 1999' =>
				array( '+1999-03-00T00:00:00Z', TimeValue::PRECISION_MONTH ),
			'1999 March' =>
				array( '+1999-03-00T00:00:00Z', TimeValue::PRECISION_MONTH ),

			/**
			 * @see ValueParsers\IsoTimestampParser
			 * @see ValueParsers\Test\IsoTimestampParserTest
			 */
			'+0000000000000000-01-01T00:00:00Z (Gregorian)' =>
				array( '+0000-01-01T00:00:00Z' ),
			'+0-00-20T00:00:00Z' =>
				array( '+0000-00-20T00:00:00Z', TimeValue::PRECISION_DAY, $julian ),
			'-10100-02-29' =>
				array( '-10100-02-29T00:00:00Z', TimeValue::PRECISION_DAY, $julian ),
			'+2015-01-00T00:00:00Z' =>
				array( '+2015-01-00T00:00:00Z', TimeValue::PRECISION_MONTH ),
			'+2015-00-00T00:00:00Z' =>
				array( '+2015-00-00T00:00:00Z', TimeValue::PRECISION_YEAR ),
			'2015-01-00' =>
				array( '+2015-01-00T00:00:00Z', TimeValue::PRECISION_MONTH ),
			'2015-00-00' =>
				array( '+2015-00-00T00:00:00Z', TimeValue::PRECISION_YEAR ),

			/**
			 * @see Wikibase\Lib\Parsers\MWTimeIsoParser
			 * @see Wikibase\Lib\Parsers\Test\MWTimeIsoParserTest
			 */
			'13 billion years CE' =>
				array( '+13000000000-00-00T00:00:00Z', TimeValue::PRECISION_YEAR1G ),
			'13,000 million years CE' =>
				array( '+13000000000-00-00T00:00:00Z', TimeValue::PRECISION_YEAR1G ),
			'13,000 million years BCE' =>
				array( '-13000000000-00-00T00:00:00Z', TimeValue::PRECISION_YEAR1G, $julian ),
			'1980s' =>
				array( '+1980-00-00T00:00:00Z', TimeValue::PRECISION_YEAR10 ),

			/**
			 * @see ValueParsers\PhpDateTimeParser
			 * @see ValueParsers\Test\PhpDateTimeParserTest
			 */
			'10/10/10' =>
				array( '+0010-10-10T00:00:00Z', TimeValue::PRECISION_DAY, $julian ),
			'1 July 2013' =>
				array( '+2013-07-01T00:00:00Z' ),
			'1 Jul 2013' =>
				array( '+2013-07-01T00:00:00Z' ),
			'1 Jul 2013 BC' =>
				array( '-2013-07-01T00:00:00Z', TimeValue::PRECISION_DAY, $julian ),
			'1 Jul 2013CE' =>
				array( '+2013-07-01T00:00:00Z' ),
			'+1 Jul 2013' =>
				array( '+2013-07-01T00:00:00Z' ),
			'-1 Jul 2013' =>
				array( '-2013-07-01T00:00:00Z', TimeValue::PRECISION_DAY, $julian ),
			'-1.11.111' =>
				array( '-0111-11-01T00:00:00Z', TimeValue::PRECISION_DAY, $julian ),
			'1.11.111 BC' =>
				array( '-0111-11-01T00:00:00Z', TimeValue::PRECISION_DAY, $julian ),
			'1,11,111 BC' =>
				array( '-0111-11-01T00:00:00Z', TimeValue::PRECISION_DAY, $julian ),
			'1 11 111 BC' =>
				array( '-0111-11-01T00:00:00Z', TimeValue::PRECISION_DAY, $julian ),

			/**
			 * @see https://phabricator.wikimedia.org/T104862
			 */
			'1991 1 1' =>
				array( '+1991-01-01T00:00:00Z' ),
			'1991 1 20' =>
				array( '+1991-01-20T00:00:00Z' ),
			'2001 1 1' =>
				array( '+2001-01-01T00:00:00Z' ),
		);

		$argLists = array();

		foreach ( $valid as $value => $expected ) {
			$timestamp = $expected[0];
			$precision = isset( $expected[1] ) ? $expected[1] : TimeValue::PRECISION_DAY;
			$calendarModel = isset( $expected[2] ) ? $expected[2] : $gregorian;

			$argLists[] = array(
				(string)$value,
				new TimeValue( $timestamp, 0, 0, 0, $precision, $calendarModel )
			);
		}

		return $argLists;
	}

	/**
	 * @dataProvider invalidInputProvider
	 * @expectedException \ValueParsers\ParseException
	 */
	public function testParseThrowsException( $value ) {
		$factory = new TimeParserFactory();
		$parser = $factory->getTimeParser();

		$parser->parse( $value );
	}

	public function invalidInputProvider() {
		return array(
			// Stuff that's not even a string
			array( true ),
			array( false ),
			array( null ),
			array( 4.2 ),
			array( array() ),
			array( 42 ),

			// Strings that should not be recognozed as date time values
			array( 'June June June' ),
			array( '111 111 111' ),
			array( 'Jann 2014' ),
			array( '1980x' ),
			array( '1980ss' ),
			array( '1980UTC' ),
			array( '1980 America/New_York' ),
			array( '1980America/New_York' ),
			// A format YDM does not exist.
			array( '1991 20 1' ),
			// No date parser should ever magically turn HMS times into dates.
			array( '12:31:59' ),
			array( '23:12:31' ),
			array( '23:12:59' ),
		);
	}

	/**
	 * @dataProvider localizedMonthNameProvider
	 */
	public function testMonthNameUnlocalizer( $date, $languageCode, $expected ) {
		$options = new ParserOptions();
		$options->setOption( ValueParser::OPT_LANG, $languageCode );
		$factory = new TimeParserFactory( $options );
		$unlocalizer = $factory->getMonthNameUnlocalizer();

		$this->assertEquals( $expected, $unlocalizer->unlocalize( $date ) );
	}

	public function localizedMonthNameProvider() {
		$testCases = array(
			// Nominative month names.
			array( '1 Juli 2013', 'de', '1 July 2013' ),
			array( '1 Januarie 1999', 'af', '1 January 1999' ),
			array( '16 Jenna 1999', 'bar', '16 January 1999' ),
			array( '12 Jänner 2013', 'de-at', '12 January 2013' ),

			// Genitive month names.
			array( '1 Julis 2013', 'de', '1 July 2013' ),
			array( '31 Decembris 2013', 'la', '31 December 2013' ),

			// Abbreviations.
			array( '1 Jan 1999', 'af', '1 January 1999' ),
			array( '1 Mär. 1999', 'de', '1 March 1999' ),

			// Nothing to do in English.
			array( '1 June 2013', 'en', '1 June 2013' ),
			array( '1 Jan 2013', 'en', '1 Jan 2013' ),
			array( '1 January 1999', 'en', '1 January 1999' ),

			// No localized month name found.
			array( '16 FooBarBarxxx 1999', 'bar', '16 FooBarBarxxx 1999' ),
			array( '16 Martii 1999', 'de', '16 Martii 1999' ),
			array( 'Jann 2013', 'de', 'Jann 2013' ),
			array( '16 May 1999', 'de', '16 May 1999' ),
			array( '16 Dezember 1999', 'la', '16 Dezember 1999' ),

			// Replace the longest unlocalized substring first.
			array( 'Juli Januar', 'de', 'Juli January' ),
			array( 'Juli Mai', 'de', 'July Mai' ),
			array( 'Juli December', 'de', 'July December' ),
			array( 'July Dezember', 'de', 'July December' ),
			array( 'Januar Mär Dez', 'de', 'January Mär Dez' ),

			// Do not mess with already unlocalized month names.
			array( 'January', 'de', 'January' ),
			array( 'April', 'la', 'April' ),
			array( 'Dec', 'de', 'Dec' ),
			array( '15 March 44 BC', 'nrm', '15 March 44 BC' ),
			array( 'Juni June', 'de', 'Juni June' ),
			array( 'July Jul', 'de', 'July Jul' ),

			// But shortening is ok even if a substring looks like it's already unlocalized.
			array( 'Mayo', 'war', 'May' ),
			array( 'July Julis', 'de', 'July July' ),

			// Do not mess with strings that are clearly not a valid date.
			array( 'Juli Juli', 'de', 'Juli Juli' ),

			// Word boundaries currently do not prevent unlocalization on purpose.
			array( 'Mai2013', 'de', 'May2013' ),
			array( 'Februarii', 'de', 'Februaryii' ),

			// Capitalization is currently significant. This may need to depend on the languages.
			array( '1 juli 2013', 'de', '1 juli 2013' ),
		);

		// Loop through some other languages
		$languageCodes = array( 'war', 'ceb', 'uk', 'ru', 'de' );
		$en = Language::factory( 'en' );

		foreach ( $languageCodes as $languageCode ) {
			$language = Language::factory( $languageCode );

			for ( $i = 1; $i <= 12; $i++ ) {
				$expected = $en->getMonthName( $i );

				$testCases[] = array( $language->getMonthName( $i ), $languageCode, $expected );
				$testCases[] = array( $language->getMonthNameGen( $i ), $languageCode, $expected );
				$testCases[] = array( $language->getMonthAbbreviation( $i ), $languageCode, $expected );
			}
		}

		return $testCases;
	}

	/**
	 * @dataProvider localizedMonthName_withLanguageChainProvider
	 */
	public function testMonthNameUnlocalizer_withLanguageChain( $date, array $languageCodes, $expected ) {
		$options = new ParserOptions();

		foreach ( $languageCodes as $languageCode ) {
			$options->setOption( ValueParser::OPT_LANG, $languageCode );
			$factory = new TimeParserFactory( $options );
			$unlocalizer = $factory->getMonthNameUnlocalizer();
			$date = $unlocalizer->unlocalize( $date );
		}

		$this->assertEquals( $expected, $date );
	}

	public function localizedMonthName_withLanguageChainProvider() {
		return array(
			// First language contains the word.
			array( 'Feb.', array( 'de', 'la' ), 'February' ),

			// Second language contains the word.
			array( 'February', array( 'de', 'en' ), 'February' ),
			array( 'Februar', array( 'en', 'de' ), 'February' ),
			array( 'Feb', array( 'de', 'la' ), 'February' ),
			array( 'Jun', array( 'de', 'ms' ), 'June' ),

			// No language contains the word.
			array( 'Jun', array( 'de', 'la' ), 'Jun' ),
		);
	}

}
