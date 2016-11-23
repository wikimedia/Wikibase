<?php

namespace Wikibase\Lib\Tests;

use MWException;
use PHPUnit_Framework_TestCase;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\LegacyIdInterpreter;
use Wikibase\DataModel\Term\Term;
use Wikibase\TermIndexEntry;

/**
 * @covers Wikibase\TermIndexEntry
 *
 * @group Wikibase
 * @group WikibaseTerm
 * @group WikibaseLib
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler <daniel.kinzler@wikimedia.de>
 */
class TermIndexEntryTest extends PHPUnit_Framework_TestCase {

	public function provideConstructor() {
		return [
			[
				[
					'entityType' => 'item',
					'entityId' => new ItemId( 'Q23' ),
					'termType' => TermIndexEntry::TYPE_LABEL,
					'termLanguage' => 'en',
					'termText' => 'foo',
					'termWeight' => 1.234,
				]
			],
			[
				[
					'termType' => TermIndexEntry::TYPE_LABEL,
					'termLanguage' => 'en',
					'termText' => 'foo',
				]
			],
			[
				[
					'entityType' => 'item',
					'entityId' => new ItemId( 'Q23' ),
				]
			],
		];
	}

	/**
	 * @dataProvider provideConstructor
	 */
	public function testConstructor( $fields ) {
		$term = new TermIndexEntry( $fields );

		$this->assertEquals( isset( $fields['entityType'] ) ? $fields['entityType'] : null, $term->getEntityType() );
		$this->assertEquals( isset( $fields['entityId'] ) ? $fields['entityId'] : null, $term->getEntityId() );
		$this->assertEquals( isset( $fields['termType'] ) ? $fields['termType'] : null, $term->getType() );
		$this->assertEquals( isset( $fields['termLanguage'] ) ? $fields['termLanguage'] : null, $term->getLanguage() );
		$this->assertEquals( isset( $fields['termText'] ) ? $fields['termText'] : null, $term->getText() );
		$this->assertEquals( isset( $fields['termWeight'] ) ? $fields['termWeight'] : null, $term->getWeight() );
	}

	public function testGivenInvalidField_constructorThrowsException() {
		$this->setExpectedException( MWException::class );
		new TermIndexEntry( [ 'fooField' => 'bar' ] );
	}

	public function testGivenEntityTypeMismatch_constructorThrowsException() {
		$this->setExpectedException( MWException::class );
		new TermIndexEntry( [ 'entityId' => new ItemId( 'Q222' ), 'entityType' => 'property' ] );
	}

	public function testClone() {
		$term = new TermIndexEntry( [ 'termText' => 'Foo' ] );

		$clone = clone $term;
		$this->assertEquals( $term, $clone, 'clone must be equal to original' );
	}

	/**
	 * @param array $extraFields
	 *
	 * @return TermIndexEntry
	 */
	private function newInstance( array $extraFields = [] ) {
		return new TermIndexEntry( $extraFields + [
				'entityType' => 'item',
				'entityId' => new ItemId( 'Q23' ),
				'termType' => TermIndexEntry::TYPE_LABEL,
				'termLanguage' => 'en',
				'termText' => 'foo',
			] );
	}

	public function provideCompare() {
		$term = $this->newInstance();

		return [
			'empty' => [
				new TermIndexEntry(),
				new TermIndexEntry(),
				true
			],
			'clone' => [
				$term,
				clone $term,
				true
			],
			'other text' => [
				$term,
				$this->newInstance( [ 'termText' => 'bar' ] ),
				false
			],
			'other entity id' => [
				$term,
				$this->newInstance( [ 'entityType' => 'property', 'entityId' => new PropertyId( 'P11' ) ] ),
				false
			],
			'other language' => [
				$term,
				$this->newInstance( [ 'termLanguage' => 'fr' ] ),
				false
			],
			'other term type' => [
				$term,
				$this->newInstance( [ 'termType' => TermIndexEntry::TYPE_DESCRIPTION ] ),
				false
			],
		];
	}

	/**
	 * @dataProvider provideCompare
	 * @depends testClone
	 */
	public function testCompare( TermIndexEntry $a, TermIndexEntry $b, $equal ) {
		$ab = TermIndexEntry::compare( $a, $b );
		$ba = TermIndexEntry::compare( $b, $a );

		if ( $equal ) {
			$this->assertEquals( 0, $ab, 'Comparison of equal terms is expected to return 0' );
			$this->assertEquals( 0, $ba, 'Comparison of equal terms is expected to return 0' );
		} else {
			// NOTE: We don't know or care whether this is larger or smaller
			$this->assertNotEquals( 0, $ab, 'Comparison of unequal terms is expected to not return 0' );
			$this->assertEquals( -$ab, $ba, 'Comparing A to B should return the inverse of comparing B to A' );
		}
	}

	public function testGetTerm() {
		$termIndexEntry = new TermIndexEntry( [
			'termLanguage' => 'en',
			'termText' => 'foo',
		] );
		$expectedTerm = new Term( 'en', 'foo' );
		$this->assertEquals( $expectedTerm, $termIndexEntry->getTerm() );
	}

	public function provideTermIndexEntryData() {
		return [
			[
				[ 'termText' => 'foo' ]
			],
			[
				[ 'termLanguage' => 'en' ]
			],
		];
	}

	/**
	 * @dataProvider provideTermIndexEntryData
	 */
	public function testGetTerm_throwsException( $termIndexEntryData ) {
		$termIndexEntry = new TermIndexEntry( $termIndexEntryData );
		$this->setExpectedException( MWException::class, 'Can not construct Term from partial TermIndexEntry' );
		$termIndexEntry->getTerm();
	}

}
