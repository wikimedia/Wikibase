<?php

namespace Wikibase\QueryEngine\Tests\SQLStore;

use Ask\Language\Description\AnyValue;
use Ask\Language\Description\SomeProperty;
use Ask\Language\Option\QueryOptions;
use DataValues\PropertyValue;
use Wikibase\QueryEngine\SQLStore\Engine\DescriptionMatchFinder;

/**
 * @covers Wikibase\QueryEngine\SQLStore\Engine\DescriptionMatchFinder
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
 * @since 0.1
 *
 * @ingroup WikibaseQueryEngineTest
 *
 * @group Wikibase
 * @group WikibaseQueryEngine
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class DescriptionMatchFinderTest extends \PHPUnit_Framework_TestCase {

	public function testCanConstruct() {
		$this->newInstanceWithMocks();
		$this->assertTrue( true );
	}

	protected function newInstanceWithMocks() {
		return new DescriptionMatchFinder(
			$this->getMock( 'Wikibase\Database\QueryInterface' ),
			$this->getMockBuilder( 'Wikibase\QueryEngine\SQLStore\Schema' )
				->disableOriginalConstructor()->getMock(),
			$this->getMock( 'Wikibase\QueryEngine\SQLStore\PropertyDataValueTypeLookup' )
		);
	}

	public function testFindMatchingEntitiesReturnType() {
		$description = new AnyValue();
		$queryOptions = new QueryOptions( 100, 0 );

		$matchFinder = $this->newInstanceWithMocks();

		$matchingInternalIds = $matchFinder->findMatchingEntities( $description, $queryOptions );

		$this->assertInternalType( 'array', $matchingInternalIds );
		$this->assertContainsOnly( 'int', $matchingInternalIds );
	}

	public function testFindMatchingEntitiesWithSomePropertyAnyValue() {
		$description = new SomeProperty( new PropertyValue( 'q42' ), new AnyValue() );
		$queryOptions = new QueryOptions( 100, 0 );

		$queryEngine = $this->getMock( 'Wikibase\Database\QueryEngine' );

		$queryEngine->expects( $this->once() )
			->method( 'select' )
			->will( $this->returnValue( array() ) ); // TODO

		$schema = $this->getMock( 'Wikibase\QueryEngine\SQLStore\Schema' );

		$schema->expects( $this->once() )
			->method( 'getDataValueHandler' )
			->will( $this->returnValue( null ) ); // TODO

		$dvTypeLookup = $this->getMock( 'Wikibase\QueryEngine\SQLStore\PropertyDataValueTypeLookup' );

		$matchFinder = new DescriptionMatchFinder(
			$queryEngine,
			$schema,
			$dvTypeLookup
		);

		$matchingInternalIds = $matchFinder->findMatchingEntities( $description, $queryOptions );

	}

}
