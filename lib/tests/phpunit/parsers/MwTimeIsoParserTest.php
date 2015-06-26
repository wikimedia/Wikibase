<?php

namespace Wikibase\Lib\Parsers\Test;

use DataValues\TimeValue;
use ValueParsers\Test\StringValueParserTest;
use Wikibase\Lib\Parsers\MWTimeIsoParser;

/**
 * @covers Wikibase\Lib\Parsers\MWTimeIsoParser
 *
 * @group ValueParsers
 * @group WikibaseLib
 * @group Wikibase
 * @group TimeParsers
 *
 * @licence GNU GPL v2+
 * @author Adam Shorland
 */
class MWTimeIsoParserTest extends StringValueParserTest {

	/**
	 * @deprecated since 0.3, just use getInstance.
	 */
	protected function getParserClass() {
		throw new \LogicException( 'Should not be called, use getInstance' );
	}

	/**
	 * @see ValueParserTestBase::getInstance
	 *
	 * @return MWTimeIsoParser
	 */
	protected function getInstance() {
		return new MWTimeIsoParser();
	}

	/**
	 * @see ValueParserTestBase::validInputProvider
	 */
	public function validInputProvider() {
		$gregorian = 'http://www.wikidata.org/entity/Q1985727';
		$julian = 'http://www.wikidata.org/entity/Q1985786';

		$argLists = array();

		$valid = array(
			// + dates
			'13 billion years CE' =>
				array( '+0000013000000000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1G, $gregorian ),
			'130 billion years CE' =>
				array( '+0000130000000000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1G, $gregorian ),
			'13000 billion years CE' =>
				array( '+0013000000000000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1G, $gregorian ),
			'13,000 billion years CE' =>
				array( '+0013000000000000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1G, $gregorian ),
			'13,000 million years CE' =>
				array( '+0000013000000000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1G, $gregorian ),
			'13,800 million years CE' =>
				array( '+0000013800000000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR100M, $gregorian ),
			'100 million years CE' =>
				array( '+0000000100000000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR100M, $gregorian ),
			'70 million years CE' =>
				array( '+0000000070000000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR10M, $gregorian ),
			'77 million years CE' =>
				array( '+0000000077000000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1M, $gregorian ),
			'13 million years CE' =>
				array( '+0000000013000000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1M, $gregorian ),
			'1 million years CE' =>
				array( '+0000000001000000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1M, $gregorian ),
			'100000 years CE' =>
				array( '+0000000000100000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR100K, $gregorian ),
			'100,000 years CE' =>
				array( '+0000000000100000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR100K, $gregorian ),
			'10000 years CE' =>
				array( '+0000000000010000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR10K, $gregorian ),
			'99000 years CE' =>
				array( '+0000000000099000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1K, $gregorian ),
			'99,000 years CE' =>
				array( '+0000000000099000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1K, $gregorian ),
			'5. millennium' =>
				array( '+0000000000005000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1K, $gregorian ),
			'55. millennium' =>
				array( '+0000000000055000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1K, $gregorian ),
			'10. century' =>
				array( '+0000000000001000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR100, $julian ),
			'12. century' =>
				array( '+0000000000001200-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR100, $julian ),
			'1980s' =>
				array( '+0000000000001980-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR10, $gregorian ),
			'2000s' =>
				array( '+0000000000002000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR10, $gregorian ),
			'10s' =>
				array( '+0000000000000010-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR10, $julian ),
			'12s' =>
				array( '+0000000000000012-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR10, $julian ),

			// - dates
			'13 billion years BCE' =>
				array( '-0000013000000000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1G, $julian ),
			'130 billion years BCE' =>
				array( '-0000130000000000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1G, $julian ),
			'13000 billion years BCE' =>
				array( '-0013000000000000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1G, $julian ),
			'13,000 billion years BCE' =>
				array( '-0013000000000000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1G, $julian ),
			'13,000 million years BCE' =>
				array( '-0000013000000000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1G, $julian ),
			'13,800 million years BCE' =>
				array( '-0000013800000000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR100M, $julian ),
			'100 million years BCE' =>
				array( '-0000000100000000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR100M, $julian ),
			'70 million years BCE' =>
				array( '-0000000070000000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR10M, $julian ),
			'77 million years BCE' =>
				array( '-0000000077000000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1M, $julian ),
			'13 million years BCE' =>
				array( '-0000000013000000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1M, $julian ),
			'1 million years BCE' =>
				array( '-0000000001000000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1M, $julian ),
			'100000 years BCE' =>
				array( '-0000000000100000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR100K, $julian ),
			'100,000 years BCE' =>
				array( '-0000000000100000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR100K, $julian ),
			'10000 years BCE' =>
				array( '-0000000000010000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR10K, $julian ),
			'99000 years BCE' =>
				array( '-0000000000099000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1K, $julian ),
			'99,000 years BCE' =>
				array( '-0000000000099000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1K, $julian ),
			'5. millennium BCE' =>
				array( '-0000000000005000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1K, $julian ),
			'55. millennium BCE' =>
				array( '-0000000000055000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1K, $julian ),
			'10. century BCE' =>
				array( '-0000000000001000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR100, $julian ),
			'12. century BCE' =>
				array( '-0000000000001200-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR100, $julian ),
			'10s BCE' =>
				array( '-0000000000000010-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR10, $julian ),
			'12s BCE' =>
				array( '-0000000000000012-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR10, $julian ),
			// also parse BC
			'5. millennium BC' =>
				array( '-0000000000005000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1K, $julian ),
			'55. millennium BC' =>
				array( '-0000000000055000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR1K, $julian ),
			'10. century BC' =>
				array( '-0000000000001000-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR100, $julian ),
			'12. century BC' =>
				array( '-0000000000001200-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR100, $julian ),
			'10s BC' =>
				array( '-0000000000000010-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR10, $julian ),
			'12s BC' =>
				array( '-0000000000000012-00-00T00:00:00Z', 0, 0, 0, TimeValue::PRECISION_YEAR10, $julian ),
		);

		foreach ( $valid as $value => $expected ) {
			// $time, $timezone, $before, $after, $precision, $calendarModel
			$expected = new TimeValue( $expected[0], $expected[1], $expected[2], $expected[3], $expected[4], $expected[5]  );
			$argLists[] = array( (string)$value, $expected );
		}

		return $argLists;
	}

	/**
	 * @see StringValueParserTest::invalidInputProvider
	 */
	public function invalidInputProvider() {
		$argLists = parent::invalidInputProvider();

		$invalid = array(
			//These are just wrong!
			'June June June',
			'111 111 111',
			'Jann 2014',

			//Not within the scope of this parser
			'200000000',
			'1 June 2013',
			'June 2013',
			'2000',
			'1980x',
			'1980ss',
		);

		foreach ( $invalid as $value ) {
			$argLists[] = array( $value );
		}

		return $argLists;
	}

}
