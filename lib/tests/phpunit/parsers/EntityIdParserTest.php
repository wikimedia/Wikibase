<?php

namespace Wikibase\Lib\Test;
use ValueParsers\Result;

/**
 * Unit test Wikibase\Lib\EntityIdParser class.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @since 0.4
 *
 * @ingroup WikibaseLibTest
 *
 * @group ValueParsers
 * @group Wikibase
 * @group EntityIdParserTest
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class EntityIdParserTest extends \ValueParsers\Test\StringValueParserTest {

	/**
	 * @see ValueParserTestBase::parseProvider
	 *
	 * @since 0.4
	 *
	 * @return array
	 */
	public function parseProvider() {
		$argLists = array();

		$parser = new \Wikibase\Lib\EntityIdParser( new \ValueParsers\ParserOptions( array(
			\Wikibase\Lib\EntityIdParser::OPT_PREFIX_MAP => array(
				'a' => 'entity-type-a',
				'b' => 'entity-type-b',
				'x' => 'entity-type-a',
			) )
		) );

		$valid = array(
			'a1' => array( 'entity-type-a', 1 ),
			'a2'=> array( 'entity-type-a', 2 ),
			'A3'=> array( 'entity-type-a', 3 ),
			'b4'=> array( 'entity-type-b', 4 ),
			'b9001'=> array( 'entity-type-b', 9001 ),
			'b7201010'=> array( 'entity-type-b', 7201010 ),
			'x1'=> array( 'entity-type-a', 1 ),
			'x42'=> array( 'entity-type-a', 42 ),
		);

		foreach ( $valid as $value => $expected ) {
			$expected = new \Wikibase\EntityId( $expected[0], $expected[1] );
			$argLists[] = array( (string)$value, Result::newSuccess( $expected ), $parser );
		}

		$invalid = array(
			'foo',
			'2',
			'c2',
			'a-1',
			'1a',
			'a1a',
			'01a',
			'a 1',
			'a1 ',
			' a1',
		);

		foreach ( $invalid as $value ) {
			$argLists[] = array( $value, Result::newErrorText( '' ), $parser );
		}

		return array_merge( $argLists );
	}

	/**
	 * @see ValueParserTestBase::getParserClass
	 *
	 * @since 0.4
	 *
	 * @return string
	 */
	protected function getParserClass() {
		return 'Wikibase\Lib\EntityIdParser';
	}

	/**
	 * @see ValueParserTestBase::requireDataValue
	 *
	 * @since 0.4
	 *
	 * @return boolean
	 */
	protected function requireDataValue() {
		return false;
	}

}
