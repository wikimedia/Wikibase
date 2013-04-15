<?php

namespace Wikibase\Tests\Query\SQLStore\SnakStore;

use DataValues\StringValue;
use Wikibase\PropertyNoValueSnak;
use Wikibase\PropertySomeValueSnak;
use Wikibase\PropertyValueSnak;
use Wikibase\QueryEngine\SQLStore\SnakStore\NoValueSnakStore;
use Wikibase\QueryEngine\SQLStore\SnakRow;
use Wikibase\SnakRole;

/**
 * Unit tests for the Wikibase\QueryEngine\SQLStore\SnakStore\NoValueSnakStore class.
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
 * @group WikibaseSnakStore
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class NoValueSnakStoreTest extends SnakStoreTest {

	protected function getInstance() {
		return new NoValueSnakStore(
			$this->getMock( 'Wikibase\Database\QueryInterface' ),
			$this->getTableDefinition()
		);
	}

	protected function getTableDefinition() {
		return $this->newStoreSchema()->getValuelessSnaksTable();
	}

	public function canStoreProvider() {
		$argLists = array();

		$argLists[] = array( new SnakRow(
			new PropertyNoValueSnak( 1 ),
			1,
			1,
			SnakRole::QUALIFIER
		) );

		$argLists[] = array( new SnakRow(
			new PropertyNoValueSnak( 31337 ),
			1,
			1,
			SnakRole::MAIN_SNAK
		) );

		return $argLists;
	}

	public function cannotStoreProvider() {
		$argLists = array();

		$argLists[] = array( new SnakRow(
			new PropertyValueSnak( 42, new StringValue( 'nyan' ) ),
			1,
			1,
			SnakRole::QUALIFIER
		) );

		$argLists[] = array( new SnakRow(
			new PropertyValueSnak( 9001, new StringValue( 'nyan' ) ),
			1,
			1,
			SnakRole::MAIN_SNAK
		) );

		$argLists[] = array( new SnakRow(
			new PropertySomeValueSnak( 2 ),
			1,
			1,
			SnakRole::QUALIFIER
		) );

		$argLists[] = array( new SnakRow(
			new PropertySomeValueSnak( 720101 ),
			1,
			1,
			SnakRole::MAIN_SNAK
		) );

		return $argLists;
	}

	/**
	 * @dataProvider canStoreProvider
	 */
	public function testStoreSnak( SnakRow $snakRow ) {
		$queryInterface = $this->getMock( 'Wikibase\Database\QueryInterface' );

		$queryInterface->expects( $this->once() )
			->method( 'insert' )
			->with(
				$this->equalTo( $this->getTableDefinition()->getName() ),
				$this->equalTo(
					array(
						 'claim_id' => $snakRow->getInternalClaimId(),
						 'property_id' => $snakRow->getInternalPropertyId(),
						 'type' => $snakRow->getSnak()->getType(),
						 'level' => $snakRow->getSnakRole(),
					)
				)
			);

		$store = new NoValueSnakStore(
			$queryInterface,
			$this->getTableDefinition()
		);

		$store->storeSnakRow( $snakRow );
	}

}
