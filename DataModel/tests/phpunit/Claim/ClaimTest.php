<?php

namespace Wikibase\Test;
use Wikibase\Claim, Wikibase\Snak, Wikibase\SnakList, Wikibase\Snaks;
use DataValues\StringValue;

/**
 * Tests for the Wikibase\Claim class.
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
 * @group WikibaseClaim
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ClaimTest extends \MediaWikiTestCase {

	public function constructorProvider() {
		$argLists = array();

		$id42 = new \Wikibase\EntityId( \Wikibase\Property::ENTITY_TYPE, 42 );

		$argLists[] = array( new \Wikibase\PropertyNoValueSnak( $id42 ) );

		$argLists[] = array( new \Wikibase\PropertyNoValueSnak( $id42 ), new SnakList() );

		$argLists[] = array(
			new \Wikibase\PropertyNoValueSnak( $id42 ),
			new \Wikibase\SnakList( array(
				new \Wikibase\PropertyValueSnak( $id42, new StringValue( 'a' ) ),
				new \Wikibase\PropertySomeValueSnak( new \Wikibase\EntityId( \Wikibase\Property::ENTITY_TYPE, 1 ) ),
				new \Wikibase\PropertyNoValueSnak( new \Wikibase\EntityId( \Wikibase\Property::ENTITY_TYPE, 2 ) )
			) )
		);

		return $argLists;
	}

	public function instanceProvider() {
		return array_map(
			function( array $arguments ) {
				$snak = $arguments[0];
				$qualifiers = array_key_exists( 1, $arguments ) ? $arguments[1] : null;
				return array( new Claim( $snak, $qualifiers ) );
			},
			$this->constructorProvider()
		);
	}

	/**
	 * @dataProvider constructorProvider
	 *
	 * @param \Wikibase\Snak $snak
	 * @param null|\Wikibase\Snaks $qualifiers
	 */
	public function testConstructor( Snak $snak, Snaks $qualifiers = null ) {
		$claim = new Claim( $snak, $qualifiers );

		$this->assertInstanceOf( '\Wikibase\Claim', $claim );

		$this->assertEquals( $snak, $claim->getMainSnak() );

		if ( $qualifiers === null ) {
			$this->assertEquals( 0, count( $claim->getQualifiers() ) );
		}
		else {
			$this->assertEquals(
				$qualifiers,
				$claim->getQualifiers()
			);
		}
	}

	public function testSetMainSnak() {
		$id42 = new \Wikibase\EntityId( \Wikibase\Property::ENTITY_TYPE, 42 );

		$claim = new Claim( new \Wikibase\PropertyNoValueSnak( $id42 ) );

		$snak = new \Wikibase\PropertyNoValueSnak( new \Wikibase\EntityId( \Wikibase\Property::ENTITY_TYPE, 41 ) );
		$claim->setMainSnak( $snak );
		$this->assertEquals( $snak, $claim->getMainSnak() );

		$snak = new \Wikibase\PropertyValueSnak( new \Wikibase\EntityId( \Wikibase\Property::ENTITY_TYPE, 43 ), new StringValue( 'a' ) );
		$claim->setMainSnak( $snak );
		$this->assertEquals( $snak, $claim->getMainSnak() );

		$snak = new \Wikibase\PropertyNoValueSnak( $id42 );
		$claim->setMainSnak( $snak );
		$this->assertEquals( $snak, $claim->getMainSnak() );
	}

	public function testSetQualifiers() {
		$id42 = new \Wikibase\EntityId( \Wikibase\Property::ENTITY_TYPE, 42 );

		$claim = new Claim( new \Wikibase\PropertyNoValueSnak( $id42 ) );

		$qualifiers = new SnakList();
		$claim->setQualifiers( $qualifiers );
		$this->assertEquals( $qualifiers, $claim->getQualifiers() );

		$qualifiers = new SnakList( array( new \Wikibase\PropertyValueSnak( $id42, new StringValue( 'a' ) ) ) );
		$claim->setQualifiers( $qualifiers );
		$this->assertEquals( $qualifiers, $claim->getQualifiers() );

		$qualifiers = new SnakList( array(
			new \Wikibase\PropertyValueSnak( $id42, new StringValue( 'a' ) ),
			new \Wikibase\PropertySomeValueSnak( new \Wikibase\EntityId( \Wikibase\Property::ENTITY_TYPE, 2 ) ),
			new \Wikibase\PropertyNoValueSnak( new \Wikibase\EntityId( \Wikibase\Property::ENTITY_TYPE, 3 ) )
		) );
		$claim->setQualifiers( $qualifiers );
		$this->assertEquals( $qualifiers, $claim->getQualifiers() );
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testGetPropertyId( Claim $claim ) {
		$this->assertEquals(
			$claim->getMainSnak()->getPropertyId(),
			$claim->getPropertyId()
		);
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testSetGuid( Claim $claim ) {
		$claim->setGuid( 'foo-bar-baz' );
		$this->assertEquals( 'foo-bar-baz', $claim->getGuid() );
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testGetGuid( Claim $claim ) {
		$guid = $claim->getGuid();
		$this->assertTypeOrValue( 'string', $guid, null );
		$this->assertEquals( $guid, $claim->getGuid() );

		$claim->setGuid( 'foobar' );
		$this->assertEquals( 'foobar', $claim->getGuid() );
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testSerialize( Claim $claim ) {
		$copy = unserialize( serialize( $claim ) );

		$this->assertEquals( $claim->getHash(), $copy->getHash(), 'Serialization roundtrip should not affect hash' );
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testToArrayRoundrip( Claim $claim ) {
		$data = $claim->toArray();

		$this->assertInternalType( 'array', $data );

		$copy = Claim::newFromArray( $data );

		$this->assertEquals( $claim->getHash(), $copy->getHash(), 'toArray newFromArray roundtrip should not affect hash' );
	}

	public function testGetHashStability() {
		$guidGenerator = new \Wikibase\Lib\ClaimGuidGenerator( new \Wikibase\EntityId( \Wikibase\Item::ENTITY_TYPE, 31 ) );
		$guid = $guidGenerator->newGuid();

		$claim0 = new Claim( new \Wikibase\PropertyNoValueSnak( 42 ) );
		$claim0->setGuid( $guid );

		$claim1 = new Claim( new \Wikibase\PropertyNoValueSnak( 42 ) );
		$claim1->setGuid( $guid );

		$this->assertEquals( $claim0->getHash(), $claim1->getHash() );
	}

}
