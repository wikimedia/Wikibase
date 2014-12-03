<?php

namespace Wikibase\Test;

use HashBagOStuff;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\PropertyLabelResolver;
use Wikibase\Term;
use Wikibase\TermPropertyLabelResolver;

/**
 * @covers Wikibase\TermPropertyLabelResolver
 *
 * @group Wikibase
 * @group WikibaseLib
 * @group WikibaseStore
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class TermPropertyLabelResolverTest extends \MediaWikiTestCase {

	/**
	 * @param string $lang
	 * @param Term[] $terms
	 *
	 * @return PropertyLabelResolver
	 */
	public function getResolver( $lang, $terms ) {
		$resolver = new TermPropertyLabelResolver(
			$lang,
			new MockTermIndex( $terms ),
			new HashBagOStuff(),
			3600,
			'testrepo:WBL\0.5alpha'
		);

		return $resolver;
	}

	/**
	 * @dataProvider provideGetPropertyIdsForLabels
	 */
	public function testGetPropertyIdsForLabels( $lang, array $terms, array $labels, array $expected ) {
		$resolver = $this->getResolver( $lang, $terms );

		// check we are getting back the expected map of labels to IDs
		$actual = $resolver->getPropertyIdsForLabels( $labels );
		$this->assertArrayEquals( $expected, $actual, false, true );

		// check again, so we also hit the "stuff it cached" code path
		$actual = $resolver->getPropertyIdsForLabels( $labels );
		$this->assertArrayEquals( $expected, $actual, false, true );
	}

	public function provideGetPropertyIdsForLabels() {
		$terms = array(
			// de
			new Term( array(
				'termType' => 'label',
				'termLanguage' => 'de',
				'entityId' => 1,
				'entityType' => 'property',
				'termText' => 'Eins',
			) ),
			new Term( array(
				'termType' => 'label',
				'termLanguage' => 'de',
				'entityId' => 2,
				'entityType' => 'property',
				'termText' => 'Zwei',
			) ),
			new Term( array(
				'termType' => 'label',
				'termLanguage' => 'de',
				'entityId' => 3,
				'entityType' => 'property',
				'termText' => 'Drei',
			) ),
			new Term( array(
				'termType' => 'label',
				'termLanguage' => 'de',
				'entityId' => 4,
				'entityType' => 'property',
				'termText' => 'vier', // lower case
			) ),

			// en
			new Term( array(
				'termType' => 'label',
				'termLanguage' => 'en',
				'entityId' => 1,
				'entityType' => 'property',
				'termText' => 'One',
			) ),
			new Term( array(
				'termType' => 'label',
				'termLanguage' => 'en',
				'entityId' => 2,
				'entityType' => 'item', // not a property
				'termText' => 'Two',
			) ),
			new Term( array(
				'termType' => 'alias', // not a label
				'termLanguage' => 'en',
				'entityId' => 3,
				'entityType' => 'property',
				'termText' => 'Three',
			) ),
			new Term( array(
				'termType' => 'description', // not a label
				'termLanguage' => 'en',
				'entityId' => 4,
				'entityType' => 'property',
				'termText' => 'Four',
			) ),
		);

		return array(
			array( // #0
				'de',   // lang
				$terms, // terms
				array(),  // labels
				array(),  // expected
			),
			array( // #1
				'de',   // lang
				$terms, // terms
				array(  // labels
					'Eins',
					'Zwei'
				),
				array(  // expected
					'Eins' => new PropertyId( 'P1' ),
					'Zwei' => new PropertyId( 'P2' ),
				)
			),
			array( // #2
				'de',   // lang
				$terms, // terms
				array(  // labels
					'Drei',
					'Vier'
				),
				array(  // expected
					'Drei' => new PropertyId( 'P3' ),
				)
			),
			array( // #3
				'en',   // lang
				$terms, // terms
				array(  // labels
					'Eins',
					'Zwei'
				),
				array()  // expected
			),
			array( // #4
				'en',   // lang
				$terms, // terms
				array(  // labels
					'One',
					'Two',
					'Three',
					'Four'
				),
				array(  // expected
					'One' => new PropertyId( 'P1' ),
				)
			),
		);
	}

}
