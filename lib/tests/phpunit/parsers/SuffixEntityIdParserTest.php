<?php

namespace Wikibase\Lib\Test;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\EntityId\BasicEntityIdParser;
use Wikibase\Lib\Parsers\SuffixEntityIdParser;

/**
 * @covers Wikibase\Lib\Parsers\SuffixEntityIdParser
 *
 * @group ValueParsers
 * @group WikibaseLib
 * @group Wikibase
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class SuffixEntityIdParserTest extends \PHPUnit_Framework_TestCase {

	public function provideParse() {
		return array(
			'base URI' => array( 'http://acme.test/entity/', 'http://acme.test/entity/Q14', new ItemId( 'Q14' ) ),
			'interwiki prefix' => array( 'wikidata:', 'wikidata:P14', new PropertyId( 'P14' ) ),
		);
	}

	/**
	 * @dataProvider provideParse
	 */
	public function testParse( $prefix, $input, $expected ) {
		$parser = new SuffixEntityIdParser( $prefix, new BasicEntityIdParser() );
		$this->assertEquals( $expected, $parser->parse( $input ) );
	}

	public function provideParse_invalid() {
		return array(
			'mismatching prefix' => array( 'http://acme.test/entity/', 'http://www.wikidata.org/entity/Q14' ),
			'incomplete prefix' => array( 'http://acme.test/entity/', 'http://acme.test/Q14' ),
			'bad ID after prefix' => array( 'http://acme.test/entity/', 'http://acme.test/entity/XYYZ' ),
			'extra stuff after ID' => array( 'http://acme.test/entity/', 'http://acme.test/entity/Q14#foo' ),
			'input is shorter than prefix' => array( 'http://acme.test/entity/', 'http://acme.test/' ),
			'input is same as prefix' => array( 'http://acme.test/entity/', 'http://acme.test/entity/' ),
			'input is lexicographically smaller than prefix' => array( 'http://acme.test/entity/', 'http://aaaa.test/entity/Q14' ),
			'input is lexicographically greater than prefix' => array( 'http://acme.test/entity/', 'http://cccc.test/entity/Q14' ),
		);
	}

	/**
	 * @dataProvider provideParse_invalid
	 */
	public function testParse_invalid( $prefix, $input ) {
		$parser = new SuffixEntityIdParser( $prefix, new BasicEntityIdParser() );

		$this->setExpectedException( 'Wikibase\DataModel\Services\EntityId\EntityIdParsingException' );
		$parser->parse( $input );
	}

}
