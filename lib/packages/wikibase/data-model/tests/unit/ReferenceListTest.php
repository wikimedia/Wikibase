<?php

namespace Wikibase\DataModel\Tests;

use Hashable;
use InvalidArgumentException;
use PHPUnit_Framework_TestCase;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\ReferenceList;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\SnakList;

/**
 * @covers Wikibase\DataModel\ReferenceList
 *
 * @group Wikibase
 * @group WikibaseDataModel
 * @group WikibaseReference
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Thiemo Mättig
 */
class ReferenceListTest extends PHPUnit_Framework_TestCase {

	public function instanceProvider() {
		return array(
			array( new ReferenceList( array() ) ),
			array( new ReferenceList( array(
				new Reference(),
				new Reference( array( new PropertyNoValueSnak( 2 ) ) ),
				new Reference( array( new PropertyNoValueSnak( 3 ) ) ),
			) ) ),
		);
	}

	public function testCanConstructWithReferenceListObject() {
		$reference = new Reference( array( new PropertyNoValueSnak( 1 ) ) );
		$original = new ReferenceList( array( $reference ) );
		$copy = new ReferenceList( $original );

		$this->assertSame( 1, $copy->count() );
		$this->assertNotNull( $copy->getReference( $reference->getHash() ) );
	}

	/**
	 * @dataProvider invalidConstructorArgumentsProvider
	 * @expectedException InvalidArgumentException
	 */
	public function testGivenInvalidConstructorArguments_constructorThrowsException( $input ) {
		new ReferenceList( $input );
	}

	public function invalidConstructorArgumentsProvider() {
		$id1 = new PropertyId( 'P1' );

		return array(
			array( null ),
			array( false ),
			array( 1 ),
			array( 0.1 ),
			array( 'string' ),
			array( $id1 ),
			array( new PropertyNoValueSnak( $id1 ) ),
			array( new Reference() ),
			array( new SnakList( array( new PropertyNoValueSnak( $id1 ) ) ) ),
			array( array( new PropertyNoValueSnak( $id1 ) ) ),
			array( array( new ReferenceList() ) ),
			array( array( new SnakList() ) ),
		);
	}

	public function testGetIterator_isTraversable() {
		$references = new ReferenceList();
		$references->addNewReference( new PropertyNoValueSnak( 1 ) );
		$iterator = $references->getIterator();

		$this->assertInstanceOf( 'Traversable', $iterator );
		$this->assertCount( 1, $iterator );
		foreach ( $references as $reference ) {
			$this->assertInstanceOf( 'Wikibase\DataModel\Reference', $reference );
		}
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testHasReferenceBeforeRemoveButNotAfter( ReferenceList $array ) {
		if ( $array->count() === 0 ) {
			$this->assertTrue( true );
			return;
		}

		/**
		 * @var Reference $hashable
		 */
		foreach ( iterator_to_array( $array ) as $hashable ) {
			$this->assertTrue( $array->hasReference( $hashable ) );
			$array->removeReference( $hashable );
			$this->assertFalse( $array->hasReference( $hashable ) );
		}
	}

	public function testGivenCloneOfReferenceInList_hasReferenceReturnsTrue() {
		$list = new ReferenceList();

		$reference = new Reference( array( new PropertyNoValueSnak( 42 ) ) );
		$sameReference = unserialize( serialize( $reference ) );

		$list->addReference( $reference );

		$this->assertTrue(
			$list->hasReference( $sameReference ),
			'hasReference should return true when a reference with the same value is present, even when its another instance'
		);
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testRemoveReference( ReferenceList $array ) {
		$elementCount = count( $array );

		/**
		 * @var Reference $element
		 */
		foreach ( iterator_to_array( $array ) as $element ) {
			$this->assertTrue( $array->hasReference( $element ) );

			$array->removeReference( $element );

			$this->assertFalse( $array->hasReference( $element ) );
			$this->assertEquals( --$elementCount, count( $array ) );
		}
	}

	public function testRemoveReferenceRemovesIdenticalObjects() {
		$reference = new Reference( array( new PropertyNoValueSnak( 1 ) ) );
		$references = new ReferenceList( array( $reference, $reference ) );

		$references->removeReference( $reference );

		$this->assertTrue( $references->isEmpty() );
	}

	public function testRemoveReferenceDoesNotRemoveCopies() {
		$reference = new Reference( array( new PropertyNoValueSnak( 1 ) ) );
		$references = new ReferenceList( array( $reference, clone $reference ) );

		$references->removeReference( $reference );

		$this->assertFalse( $references->isEmpty() );
		$this->assertTrue( $references->hasReference( $reference ) );
		$this->assertNotSame( $reference, $references->getReference( $reference->getHash() ) );
	}

	public function testAddReferenceOnEmptyList() {
		$reference = new Reference( array( new PropertyNoValueSnak( 1 ) ) );

		$references = new ReferenceList();
		$references->addReference( $reference );

		$this->assertCount( 1, $references );

		$expectedList = new ReferenceList( array( $reference ) );
		$this->assertSameReferenceOrder( $expectedList, $references );
	}

	private function assertSameReferenceOrder( ReferenceList $expectedList, ReferenceList $references ) {
		$this->assertEquals(
			iterator_to_array( $expectedList ),
			iterator_to_array( $references )
		);
	}

	public function testAddReferenceOnNonEmptyList() {
		$reference1 = new Reference( array( new PropertyNoValueSnak( 1 ) ) );
		$reference2 = new Reference( array( new PropertyNoValueSnak( 2 ) ) );
		$reference3 = new Reference( array( new PropertyNoValueSnak( 3 ) ) );

		$references = new ReferenceList( array( $reference1, $reference2 ) );
		$references->addReference( $reference3 );

		$this->assertCount( 3, $references );

		$expectedList = new ReferenceList( array( $reference1, $reference2, $reference3 ) );
		$this->assertSameReferenceOrder( $expectedList, $references );
	}

	public function testAddReferenceAtIndexZero() {
		$reference1 = new Reference( array( new PropertyNoValueSnak( 1 ) ) );
		$reference2 = new Reference( array( new PropertyNoValueSnak( 2 ) ) );
		$reference3 = new Reference( array( new PropertyNoValueSnak( 3 ) ) );

		$references = new ReferenceList( array( $reference1, $reference2 ) );
		$references->addReference( $reference3, 0 );

		$expectedList = new ReferenceList( array( $reference3, $reference1, $reference2 ) );
		$this->assertSameReferenceOrder( $expectedList, $references );
	}

	public function testAddReferenceAtNegativeIndex() {
		$reference = new Reference( array( new PropertyNoValueSnak( 1 ) ) );
		$referenceList = new ReferenceList();

		$this->setExpectedException( 'InvalidArgumentException' );
		$referenceList->addReference( $reference, -1 );
	}

	public function testGivenEmptyReference_addReferenceDoesNotAdd() {
		$reference1 = new Reference( array( new PropertyNoValueSnak( 1 ) ) );
		$reference2 = new Reference( array( new PropertyNoValueSnak( 2 ) ) );
		$emptyReference = new Reference( array() );

		$references = new ReferenceList( array( $reference1, $reference2 ) );
		$references->addReference( $emptyReference );

		$expectedList = new ReferenceList( array( $reference1, $reference2 ) );
		$this->assertSameReferenceOrder( $expectedList, $references );
	}

	public function testGivenEmptyReferenceAndIndex_addReferenceDoesNotAdd() {
		$reference1 = new Reference( array( new PropertyNoValueSnak( 1 ) ) );
		$reference2 = new Reference( array( new PropertyNoValueSnak( 2 ) ) );
		$emptyReference = new Reference( array() );

		$references = new ReferenceList( array( $reference1, $reference2 ) );
		$references->addReference( $emptyReference, 0 );

		$expectedList = new ReferenceList( array( $reference1, $reference2 ) );
		$this->assertSameReferenceOrder( $expectedList, $references );
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testIndexOf( ReferenceList $array ) {
		$this->assertFalse( $array->indexOf( new Reference() ) );

		$i = 0;
		foreach ( $array as $reference ) {
			$this->assertEquals( $i++, $array->indexOf( $reference ) );
		}
	}

	public function testIndexOf_checksForIdentity() {
		$reference1 = new Reference( array( new PropertyNoValueSnak( 1 ) ) );
		$reference2 = new Reference( array( new PropertyNoValueSnak( 1 ) ) );
		$referenceList = new ReferenceList( array( $reference1 ) );

		$this->assertNotSame( $reference1, $reference2, 'post condition' );
		$this->assertTrue( $reference1->equals( $reference2 ), 'post condition' );
		$this->assertSame( 0, $referenceList->indexOf( $reference1 ), 'identity' );
		$this->assertFalse( $referenceList->indexOf( $reference2 ), 'not equality' );
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testEquals( ReferenceList $array ) {
		$this->assertTrue( $array->equals( $array ) );
		$this->assertFalse( $array->equals( 42 ) );
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testGetValueHashReturnsString( ReferenceList $array ) {
		$this->assertInternalType( 'string', $array->getValueHash() );
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testGetValueHashIsTheSameForClone( ReferenceList $array ) {
		$copy = unserialize( serialize( $array ) );
		$this->assertEquals( $array->getValueHash(), $copy->getValueHash() );
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testHasReferenceHash( ReferenceList $references ) {
		$this->assertFalse( $references->hasReferenceHash( '~=[,,_,,]:3' ) );

		/**
		 * @var Hashable $reference
		 */
		foreach ( $references as $reference ) {
			$this->assertTrue( $references->hasReferenceHash( $reference->getHash() ) );
		}
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testGetReference( ReferenceList $references ) {
		$this->assertNull( $references->getReference( '~=[,,_,,]:3' ) );

		/**
		 * @var Reference $reference
		 */
		foreach ( $references as $reference ) {
			$this->assertTrue( $reference->equals( $references->getReference( $reference->getHash() ) ) );
		}
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testRemoveReferenceHash( ReferenceList $references ) {
		$references->removeReferenceHash( '~=[,,_,,]:3' );

		$hashes = array();

		/**
		 * @var Reference $reference
		 */
		foreach ( $references as $reference ) {
			$hashes[] = $reference->getHash();
		}

		foreach ( $hashes as $hash ) {
			$references->removeReferenceHash( $hash );
		}

		$this->assertEquals( 0, count( $references ) );
	}

	public function testRemoveReferenceHashRemovesIdenticalObjects() {
		$reference = new Reference( array( new PropertyNoValueSnak( 1 ) ) );
		$references = new ReferenceList( array( $reference, $reference ) );

		$references->removeReferenceHash( $reference->getHash() );

		$this->assertTrue( $references->isEmpty() );
	}

	public function testRemoveReferenceHashDoesNotRemoveCopies() {
		$reference = new Reference( array( new PropertyNoValueSnak( 1 ) ) );
		$references = new ReferenceList( array( $reference, clone $reference ) );

		$references->removeReferenceHash( $reference->getHash() );

		$this->assertFalse( $references->isEmpty() );
		$this->assertTrue( $references->hasReference( $reference ) );
		$this->assertNotSame( $reference, $references->getReference( $reference->getHash() ) );
	}

	public function testRemoveReferenceHashUpdatesIndexes() {
		$reference1 = new Reference( array( new PropertyNoValueSnak( 1 ) ) );
		$reference2 = new Reference( array( new PropertyNoValueSnak( 2 ) ) );
		$references = new ReferenceList( array( $reference1, $reference2 ) );

		$references->removeReferenceHash( $reference1->getHash() );

		$this->assertSame( 0, $references->indexOf( $reference2 ) );
	}

	public function testGivenOneSnak_addNewReferenceAddsSnak() {
		$references = new ReferenceList();
		$snak = new PropertyNoValueSnak( 1 );

		$references->addNewReference( $snak );
		$this->assertTrue( $references->hasReference( new Reference( array( $snak ) ) ) );
	}

	public function testGivenMultipleSnaks_addNewReferenceAddsThem() {
		$references = new ReferenceList();
		$snak1 = new PropertyNoValueSnak( 1 );
		$snak2 = new PropertyNoValueSnak( 3 );
		$snak3 = new PropertyNoValueSnak( 2 );

		$references->addNewReference( $snak1, $snak2, $snak3 );

		$expectedSnaks = array( $snak1, $snak2, $snak3 );
		$this->assertTrue( $references->hasReference( new Reference( $expectedSnaks ) ) );
	}

	public function testGivenAnArrayOfSnaks_addNewReferenceAddsThem() {
		$references = new ReferenceList();
		$snaks = array(
			new PropertyNoValueSnak( 1 ),
			new PropertyNoValueSnak( 3 ),
			new PropertyNoValueSnak( 2 )
		);

		$references->addNewReference( $snaks );
		$this->assertTrue( $references->hasReference( new Reference( $snaks ) ) );
	}

	public function testGivenNoneSnak_addNewReferenceThrowsException() {
		$references = new ReferenceList();

		$this->setExpectedException( 'InvalidArgumentException' );
		$references->addNewReference( new PropertyNoValueSnak( 1 ), null );
	}

	public function testSerializationStability() {
		$references = new ReferenceList();
		$this->assertSame( 'a:0:{}', $references->serialize() );
	}

	public function testSerializeRoundtrip() {
		$references = new ReferenceList();

		$references->addReference( new Reference() );

		$references->addReference( new Reference( array(
			new PropertyNoValueSnak( 2 ),
			new PropertyNoValueSnak( 3 ),
		) ) );

		$serialized = serialize( $references );
		$this->assertTrue( $references->equals( unserialize( $serialized ) ) );
	}

	public function testGivenEmptyList_isEmpty() {
		$references = new ReferenceList();
		$this->assertTrue( $references->isEmpty() );
	}

	public function testGivenNonEmptyList_isNotEmpty() {
		$references = new ReferenceList();
		$references->addNewReference( new PropertyNoValueSnak( 1 ) );

		$this->assertFalse( $references->isEmpty() );
	}

}
