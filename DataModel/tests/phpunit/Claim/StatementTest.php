<?php

namespace Wikibase\Test;
use Wikibase\Statement;
use Wikibase\Claim;
use Wikibase\Reference;
use \DataValues\StringValue;

/**
 * Tests for the Wikibase\Statement class.
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
 * @ingroup WikibaseLib
 * @ingroup Test
 *
 * @group Wikibase
 * @group WikibaseDataModel
 * @group WikibaseStatement
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class StatementTest extends ClaimTest {

	public function instanceProvider() {
		$instances = array();

		$id42 = new \Wikibase\EntityId( \Wikibase\Property::ENTITY_TYPE, 42 );

		$baseInstance = new Statement( new \Wikibase\PropertyNoValueSnak( $id42 ) );

		$instances[] = $baseInstance;

		$instance = clone $baseInstance;
		$instance->setRank( Statement::RANK_PREFERRED );

		$instances[] = $instance;

		$newInstance = clone $instance;

		$instances[] = $newInstance;

		$instance = clone $baseInstance;

		$instance->setReferences( new \Wikibase\ReferenceList( array(
			new Reference( new \Wikibase\SnakList(
				new \Wikibase\PropertyValueSnak( new \Wikibase\EntityId( \Wikibase\Property::ENTITY_TYPE, 1 ), new StringValue( 'a' ) )
			) )
		) ) );

		$instances[] = $instance;

		return $this->arrayWrap( $instances );
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testGetReferences( Statement $statement ) {
		$this->assertInstanceOf( '\Wikibase\References', $statement->getReferences() );
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testSetReferences( Statement $statement ) {
		$references = new \Wikibase\ReferenceList( array(
			new Reference( new \Wikibase\SnakList(
				new \Wikibase\PropertyValueSnak(
					new \Wikibase\EntityId( \Wikibase\Property::ENTITY_TYPE, 1 ),
					new StringValue( 'a' )
				)
			) ) )
		);


		$statement->setReferences( $references );

		$this->assertEquals( $references, $statement->getReferences() );
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testGetRank( Statement $statement ) {
		$rank = $statement->getRank();
		$this->assertInternalType( 'integer', $rank );

		$ranks = array( Statement::RANK_DEPRECATED, Statement::RANK_NORMAL, Statement::RANK_PREFERRED );
		$this->assertTrue( in_array( $rank, $ranks ), true );
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testSetRank( Statement $statement ) {
		$statement->setRank( Statement::RANK_DEPRECATED );
		$this->assertEquals( Statement::RANK_DEPRECATED, $statement->getRank() );

		$pokemons = null;

		try {
			$statement->setRank( 9001 );
		}
		catch ( \Exception $pokemons ) {}

		$this->assertInstanceOf( '\MWException', $pokemons );
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testIsClaim( Statement $statement ) {
		$this->assertInstanceOf( '\Wikibase\Claim', $statement );
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testGetPropertyId( Claim $statement ) {
		$this->assertEquals(
			$statement->getMainSnak()->getPropertyId(),
			$statement->getPropertyId()
		);
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testToArrayRoundrip( Claim $claim ) {
		$data = $claim->toArray();

		$this->assertInternalType( 'array', $data );

		$copy = Statement::newFromArray( $data );

		$this->assertEquals( $claim->getHash(), $copy->getHash(), 'toArray newFromArray roundtrip should not affect hash' );
	}

	public function testGetHash() {
		$guidGenerator = new \Wikibase\Lib\ClaimGuidGenerator( new \Wikibase\EntityId( \Wikibase\Item::ENTITY_TYPE, 31 ) );
		$guid = $guidGenerator->newGuid();

		$claim0 = new Statement( new \Wikibase\PropertyNoValueSnak( 42 ) );
		$claim0->setGuid( $guid );
		$claim0->setRank( Statement::RANK_DEPRECATED );

		$claim1 = new Statement( new \Wikibase\PropertyNoValueSnak( 42 ) );
		$claim1->setGuid( $guid );
		$claim1->setRank( Statement::RANK_DEPRECATED );

		$this->assertEquals( $claim0->getHash(), $claim1->getHash() );
	}

}
