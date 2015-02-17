<?php

namespace Wikibase\Test;

use Wikibase\DataModel\LegacyIdInterpreter;
use Wikibase\Term;

/**
 * @covers Wikibase\Term
 *
 * @group Wikibase
 * @group WikibaseTerm
 * @group WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler <daniel.kinzler@wikimedia.de>
 */
class TermTest extends \MediaWikiTestCase {

	public function provideContructor() {
		return array(
			array( // #0
				array(
					'entityType' => 'item',
					'entityId' => 23,
					'termType' => Term::TYPE_LABEL,
					'termLanguage' => 'en',
					'termText' => 'foo',
				)
			),
			array( // #1
				array(
					'termType' => Term::TYPE_LABEL,
					'termLanguage' => 'en',
					'termText' => 'foo',
				)
			),
			array( // #2
				array(
					'entityType' => 'item',
					'entityId' => 23,
				)
			),
		);
	}

	/**
	 * @dataProvider provideContructor
	 */
	public function testConstructor( $fields ) {
		$term = new Term( $fields );

		$entityId = null;
		if ( isset( $fields['entityType'] ) && isset( $fields['entityId'] ) ) {
			// FIXME: This must be removed once we got rid of all legacy numeric ids.
			$entityId = LegacyIdInterpreter::newIdFromTypeAndNumber( $fields['entityType'], $fields['entityId'] );
		}

		$this->assertEquals( isset( $fields['entityType'] ) ? $fields['entityType'] : null, $term->getEntityType() );
		$this->assertEquals( $entityId, $term->getEntityId() );
		$this->assertEquals( isset( $fields['termType'] ) ? $fields['termType'] : null, $term->getType() );
		$this->assertEquals( isset( $fields['termLanguage'] ) ? $fields['termLanguage'] : null, $term->getLanguage() );
		$this->assertEquals( isset( $fields['termText'] ) ? $fields['termText'] : null, $term->getText() );
	}

	public function testClone() {
		$term = new Term( array(
			'termText' => 'Foo'
		) );

		$clone = clone $term;
		$clone->setText( 'Bar' );

		$this->assertEquals( 'Bar', $clone->getText(), "clone must change when modified" ); // sanity
		$this->assertEquals( 'Foo', $term->getText(), "original must stay the same when clone is modified" );

		$clone = clone $term;
		$this->assertEquals( $term, $clone, "clone must be equal to original" );
	}

	public function provideCompare() {
		$tests = array();

		$tests[] = array( // #0
			new Term(),
			new Term(),
			true
		);

		$term = new Term( array(
			'entityType' => 'item',
			'entityId' => 23,
			'termType' => Term::TYPE_LABEL,
			'termLanguage' => 'en',
			'termText' => 'foo',
		) );

		$other = clone $term;
		$tests[] = array( // #1
			$term,
			$other,
			true
		);

		$other = clone $term;
		$other->setText( 'bar' );
		$tests[] = array( // #2
			$term,
			$other,
			false
		);

		$other = new Term( array(
			'entityType' => 'property',
			'entityId' => 11,
			'termType' => Term::TYPE_LABEL,
			'termLanguage' => 'en',
			'termText' => 'foo',
		) );
		$tests[] = array( // #3
			$term,
			$other,
			false
		);

		$other = new Term( array(
			'entityType' => 'property',
			'entityId' => 23,
			'termType' => Term::TYPE_LABEL,
			'termLanguage' => 'en',
			'termText' => 'foo',
		) );
		$tests[] = array( // #4
			$term,
			$other,
			false
		);

		$other = clone $term;
		$other->setLanguage( 'fr' );
		$tests[] = array( // #5
			$term,
			$other,
			false
		);

		$other = clone $term;
		$other->setType( Term::TYPE_DESCRIPTION  );
		$tests[] = array( // #6
			$term,
			$other,
			false
		);

		return $tests;
	}

	/**
	 * @dataProvider provideCompare
	 * @depends testClone
	 */
	public function testNotEquals( Term $a, Term $b, $equal ) {
		$ab = Term::notEquals( $a, $b );
		$ba = Term::notEquals( $b, $a );

		if ( $equal ) {
			$this->assertFalse( $ab );
			$this->assertFalse( $ba );
		} else {
			$this->assertTrue( $ab );
			$this->assertTrue( $ba );
		}
	}

}
