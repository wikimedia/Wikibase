<?php

namespace Wikibase\Test;

use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Settings;
use Wikibase\Term;
use Wikibase\TermIndex;

/**
 * Base class for tests for classes implementing Wikibase\TermIndex and
 * Wikibase\Lib\Store\LabelConflictFinder. This should probably be split.
 *
 * @group Wikibase
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Anja Jentzsch < anja.jentzsch@wikimedia.de >
 * @author Daniel Kinzler
 */
abstract class TermIndexTest extends \MediaWikiTestCase {

	/**
	 * @return TermIndex
	 */
	public abstract function getTermIndex();

	public function testGetMatchingIDs() {
		$lookup = $this->getTermIndex();

		$id0 = new ItemId( 'Q10' );
		$item0 = new Item( $id0 );

		$item0->setLabel( 'en', 'foobar' );
		$item0->setLabel( 'de', 'foobar' );
		$item0->setLabel( 'nl', 'baz' );
		$lookup->saveTermsOfEntity( $item0 );

		$item1 = $item0->copy();
		$id1 = new ItemId( 'Q11' );
		$item1->setId( $id1 );

		$item1->setLabel( 'nl', 'o_O' );
		$item1->setDescription( 'en', 'foo bar baz' );
		$lookup->saveTermsOfEntity( $item1 );

		$foobar = new Term( array( 'termType' => Term::TYPE_LABEL, 'termText' => 'foobar' ) );
		$bazNl= new Term( array( 'termType' => Term::TYPE_LABEL, 'termText' => 'baz', 'termLanguage' => 'nl' ) );
		$froggerNl = new Term( array( 'termType' => Term::TYPE_LABEL, 'termText' => 'o_O', 'termLanguage' => 'nl' ) );

		$ids = $lookup->getMatchingIDs( array( $foobar ), Item::ENTITY_TYPE );
		$this->assertInternalType( 'array', $ids );
		$this->assertContainsOnlyInstancesOf( '\Wikibase\DataModel\Entity\ItemId', $ids );
		$this->assertArrayEquals( array( $id0, $id1 ), $ids );

		$ids = $lookup->getMatchingIDs( array( $bazNl ), Item::ENTITY_TYPE );
		$this->assertInternalType( 'array', $ids );
		$this->assertContainsOnlyInstancesOf( '\Wikibase\DataModel\Entity\ItemId', $ids );
		$this->assertArrayEquals( array( $id0 ), $ids );

		$ids = $lookup->getMatchingIDs( array( $froggerNl ), Item::ENTITY_TYPE );
		$this->assertInternalType( 'array', $ids );
		$this->assertContainsOnlyInstancesOf( '\Wikibase\DataModel\Entity\ItemId', $ids );
		$this->assertArrayEquals( array( $id1 ), $ids );
	}

	public function testGetMatchingTerms() {
		$lookup = $this->getTermIndex();

		$item0 = new Item( new ItemId( 'Q10' ) );
		$id0 = $item0->getId()->getSerialization();

		$item0->setLabel( 'en', 'getmatchingterms-0' );
		$lookup->saveTermsOfEntity( $item0 );

		$item1 = new Item( new ItemId( 'Q11' )  );
		$id1 = $item1->getId()->getSerialization();

		$item1->setLabel( 'nl', 'getmatchingterms-1' );
		$item1->setLabel( 'de', 'GeTMAtchingterms-2' );
		$lookup->saveTermsOfEntity( $item1 );

		$terms = array(
			$id0 => new Term( array(
				'termLanguage' => 'en',
				'termText' => 'getmatchingterms-0',
			) ),
			$id1 => new Term( array(
				'termText' => 'getmatchingterms-1',
			) ),
			new Term( array(
				'termText' => 'getmatchingterms-2',
			) ),
		);

		$actual = $lookup->getMatchingTerms( $terms );

		$this->assertInternalType( 'array', $actual );
		$this->assertCount( 2, $actual );

		/**
		 * @var Term $term
		 * @var Term $expected
		 */
		foreach ( $actual as $term ) {
			$id = $term->getEntityId()->getSerialization();

			$this->assertContains( $id, array( $id0, $id1 ) );

			$expected = $terms[$id];

			if ( $expected->getText() !== null ) {
				$this->assertEquals( $expected->getText(), $term->getText() );
			}

			if ( $expected->getLanguage() !== null ) {
				$this->assertEquals( $expected->getLanguage(), $term->getLanguage() );
			}
		}
	}

	public function testGetMatchingPrefixTerms() {
		$lookup = $this->getTermIndex();

		$item0 = new Item( new ItemId( 'Q10' ) );
		$item0->setLabel( 'en', 'prefix' );
		$id0 = $item0->getId()->getSerialization();
		$lookup->saveTermsOfEntity( $item0 );

		$item1 = new Item( new ItemId( 'Q11' ) );
		$item1->setLabel( 'nl', 'postfix' );
		$id1 = $item1->getId()->getSerialization();
		$lookup->saveTermsOfEntity( $item1 );

		/** @var Term[] $terms */
		$terms = array(
			$id0 => new Term( array(
				'termLanguage' => 'en',
				'termText' => 'preF',
			) ),
			$id1 => new Term( array(
				'termText' => 'post',
			) ),
		);

		/** @var Term[] $expectedTerms */
		$expectedTerms = array(
			$id0 => new Term( array(
				'termLanguage' => 'en',
				'termText' => 'prefix',
			) ),
			$id1 => new Term( array(
				'termText' => 'postfix',
			) )
		);

		$options = array(
			'caseSensitive' => false,
			'prefixSearch' => true,
		);

		$actual = $lookup->getMatchingTerms( $terms, null, null, $options );

		$terms[$id1]->setLanguage( 'nl' );
		$expectedTerms[$id1]->setLanguage( 'nl' );

		$this->assertInternalType( 'array', $actual );
		$this->assertEquals( count( $expectedTerms ), count( $actual ) );

		/**
		 * @var Term $term
		 * @var Term $expected
		 */
		foreach ( $actual as $term ) {
			$id = $term->getEntityId()->getSerialization();

			$this->assertContains( $id, array( $id0, $id1 ) );

			$expected = $expectedTerms[$id];

			$this->assertEquals( $expected->getText(), $term->getText() );
			$this->assertEquals( $expected->getLanguage(), $term->getLanguage() );
		}
	}

	public function testDeleteTermsForEntity() {
		$lookup = $this->getTermIndex();

		$id = new ItemId( 'Q10' );
		$item = new Item( $id );

		$item->setLabel( 'en', 'abc' );
		$item->setLabel( 'de', 'def' );
		$item->setLabel( 'nl', 'ghi' );
		$item->setDescription( 'en', 'testDeleteTermsForEntity' );
		$item->setAliases( 'fr', array( 'o', '_', 'O' ) );

		$lookup->saveTermsOfEntity( $item );

		$this->assertTermExists( $lookup, 'testDeleteTermsForEntity' );

		$this->assertTrue( $lookup->deleteTermsOfEntity( $item->getId() ) !== false );

		$this->assertNotTermExists( $lookup, 'testDeleteTermsForEntity' );

		$abc = new Term( array( 'termType' => Term::TYPE_LABEL, 'termText' => 'abc' ) );
		$ids = $lookup->getMatchingIDs( array( $abc ), Item::ENTITY_TYPE );

		$this->assertNotContains( $id, $ids );
	}

	public function testSaveTermsOfEntity() {
		$lookup = $this->getTermIndex();

		$item = new Item( new ItemId( 'Q568431314' ) );

		$item->setLabel( 'en', 'abc' );
		$item->setLabel( 'de', 'def' );
		$item->setLabel( 'nl', 'ghi' );
		$item->setDescription( 'en', 'testDeleteTermsForEntity' );
		$item->setAliases( 'fr', array( 'o', '_', 'O' ) );

		$this->assertTrue( $lookup->saveTermsOfEntity( $item ) );

		$this->assertTermExists( $lookup,
			'testDeleteTermsForEntity',
			Term::TYPE_DESCRIPTION,
			'en',
			Item::ENTITY_TYPE
		);

		$this->assertTermExists( $lookup,
			'ghi',
			Term::TYPE_LABEL,
			'nl',
			Item::ENTITY_TYPE
		);

		$this->assertTermExists( $lookup,
			'o',
			Term::TYPE_ALIAS,
			'fr',
			Item::ENTITY_TYPE
		);

		// save again - this should hit an optimized code path
		// that avoids re-saving the terms if they are the same as before.
		$this->assertTrue( $lookup->saveTermsOfEntity( $item ) );

		$this->assertTermExists( $lookup,
			'testDeleteTermsForEntity',
			Term::TYPE_DESCRIPTION,
			'en',
			Item::ENTITY_TYPE
		);

		$this->assertTermExists( $lookup,
			'ghi',
			Term::TYPE_LABEL,
			'nl',
			Item::ENTITY_TYPE
		);

		$this->assertTermExists( $lookup,
			'o',
			Term::TYPE_ALIAS,
			'fr',
			Item::ENTITY_TYPE
		);

		// modify and save again - this should NOT skip saving,
		// and make sure the modified term is in the database.
		$item->setLabel( 'nl', 'xyz' );
		$this->assertTrue( $lookup->saveTermsOfEntity( $item ) );

		$this->assertTermExists( $lookup,
			'testDeleteTermsForEntity',
			Term::TYPE_DESCRIPTION,
			'en',
			Item::ENTITY_TYPE
		);

		$this->assertTermExists( $lookup,
			'xyz',
			Term::TYPE_LABEL,
			'nl',
			Item::ENTITY_TYPE
		);

		$this->assertTermExists( $lookup,
			'o',
			Term::TYPE_ALIAS,
			'fr',
			Item::ENTITY_TYPE
		);
	}

	public function testUpdateTermsOfEntity() {
		$item = new Item( new ItemId( 'Q568431314' ) );

		// save original set of terms
		$item->setLabel( 'en', 'abc' );
		$item->setLabel( 'de', 'def' );
		$item->setLabel( 'nl', 'ghi' );
		$item->setDescription( 'en', '-abc-' );
		$item->setDescription( 'de', '-def-' );
		$item->setDescription( 'nl', '-ghi-' );
		$item->setAliases( 'en', array( 'ABC', '_', 'X' ) );
		$item->setAliases( 'de', array( 'DEF', '_', 'Y' ) );
		$item->setAliases( 'nl', array( 'GHI', '_', 'Z' ) );

		$lookup = $this->getTermIndex();
		$lookup->saveTermsOfEntity( $item );

		// modify the item and save new set of terms
		$item->setLabel( 'en', 'abc' );
		$item->removeLabel( 'de' );
		$item->setLabel( 'nl', 'jke' );
		$item->setDescription( 'it', '-xyz-' );
		$item->setAliases( 'en', array( 'ABC', 'X', '_' ) );
		$item->setAliases( 'de', array( 'DEF', 'Y' ) );
		$item->setAliases( 'nl', array( '_', 'Z', 'foo' ) );
		$item->setDescription( 'it', 'ABC' );
		$lookup->saveTermsOfEntity( $item );

		// check that the stored terms are the ones in the modified items
		$expectedTerms = $lookup->getEntityTerms( $item );
		$actualTerms = $lookup->getTermsOfEntity( $item->getId() );

		$missingTerms = array_udiff( $expectedTerms, $actualTerms, 'Wikibase\Term::compare' );
		$extraTerms =   array_udiff( $actualTerms, $expectedTerms, 'Wikibase\Term::compare' );

		$this->assertEmpty( $missingTerms, 'Missing terms' );
		$this->assertEmpty( $extraTerms, 'Extra terms' );
	}

	private function getTermConflictEntities() {
		$deFooBar1 = new Item( new ItemId( 'Q1' ) );
		$deFooBar1->setLabel( 'de', 'Foo' );
		$deFooBar1->setDescription( 'de', 'Bar' );

		$deBarFoo2 = new Item( new ItemId( 'Q2' ) );
		$deBarFoo2->setLabel( 'de', 'Bar' );
		$deBarFoo2->setDescription( 'de', 'Foo' );

		$enFooBar3 = new Item( new ItemId( 'Q3' ) );
		$enFooBar3->setLabel( 'en', 'Foo' );
		$enFooBar3->setDescription( 'en', 'Bar' );

		$enBarFoo4 = new Item( new ItemId( 'Q4' ) );
		$enBarFoo4->setLabel( 'en', 'Bar' );
		$enBarFoo4->setDescription( 'en', 'Foo' );

		$deFooQuux5 = new Item( new ItemId( 'Q5' ) );
		$deFooQuux5->setLabel( 'de', 'Foo' );
		$deFooQuux5->setDescription( 'de', 'Quux' );

		$deFooBarP6 = Property::newFromType( 'string' );
		$deFooBarP6->setId( new PropertyId( 'P6' ) );
		$deFooBarP6->setLabel( 'de', 'Foo' );
		$deFooBarP6->setDescription( 'de', 'Bar' );

		$entities = array(
			$deFooBar1,
			$deBarFoo2,
			$enFooBar3,
			$enBarFoo4,
			$deFooQuux5,
			$deFooBarP6,
		);

		return $entities;
	}

	public function labelConflictProvider() {
		$entities = $this->getTermConflictEntities();

		return array(
			'by label' => array(
				$entities,
				Property::ENTITY_TYPE,
				array( 'de' => 'Foo' ),
				true,
				array( 'P6' ),
			),
			'by label, different case, case insensitive' => array(
				$entities,
				Property::ENTITY_TYPE,
				array( 'de' => 'fOO' ),
				false,
				array( 'P6' ),
			),
			'by label mismatch' => array(
				$entities,
				Item::ENTITY_TYPE,
				array( 'de' => 'Nope' ),
				true,
				array(),
			),
			'two languages for label' => array(
				$entities,
				Item::ENTITY_TYPE,
				array( 'de' => 'Foo', 'en' => 'Foo' ),
				true,
				array( 'Q1', 'Q3', 'Q5' ),
			),
		);
	}

	/**
	 * @dataProvider labelConflictProvider
	 */
	public function testGetLabelConflicts( $entities, $entityType, $labels, $caseSensitive, $expected ) {
		$termIndex = $this->getTermIndex();
		$termIndex->clear();

		foreach ( $entities as $entity ) {
			$termIndex->saveTermsOfEntity( $entity );
		}

		$matches = $termIndex->getLabelConflicts( $entityType, $labels, $caseSensitive );
		$actual = $this->getEntityIdStrings( $matches );

		$this->assertArrayEquals( $expected, $actual, false, false );
	}

	public function labelWithDescriptionConflictProvider() {
		$entities = $this->getTermConflictEntities();

		return array(
			'by label, empty descriptions' => array(
				$entities,
				Item::ENTITY_TYPE,
				array( 'de' => 'Foo' ),
				array(),
				true,
				array(),
			),
			'by label, mismatching description' => array(
				$entities,
				Item::ENTITY_TYPE,
				array( 'de' => 'Foo' ),
				array( 'de' => 'XYZ' ),
				true,
				array(),
			),
			'by label and description' => array(
				$entities,
				Item::ENTITY_TYPE,
				array( 'de' => 'Foo' ),
				array( 'de' => 'Bar' ),
				true,
				array( 'Q1' ),
			),
			'by label and description, different label capitalization, case insensitive' => array(
				$entities,
				Property::ENTITY_TYPE,
				array( 'de' => 'fOO' ),
				array( 'de' => 'Bar' ),
				false,
				array( 'P6' ),
			),
			'by label and description, different description capitalization, case insensitive' => array(
				$entities,
				Item::ENTITY_TYPE,
				array( 'de' => 'Foo' ),
				array( 'de' => 'bAR' ),
				false,
				array( 'Q1' ),
			),
			'two languages for label and description' => array(
				$entities,
				Item::ENTITY_TYPE,
				array( 'de' => 'Foo', 'en' => 'Foo' ),
				array( 'de' => 'Bar', 'en' => 'Bar' ),
				true,
				array( 'Q1', 'Q3' ),
			),
		);
	}

	/**
	 * @dataProvider labelWithDescriptionConflictProvider
	 */
	public function testGetLabelWithDescriptionConflicts( $entities, $entityType, $labels, $descriptions, $caseSensitive, $expected ) {
		$termIndex = $this->getTermIndex();
		$termIndex->clear();

		foreach ( $entities as $entity ) {
			$termIndex->saveTermsOfEntity( $entity );
		}

		$matches = $termIndex->getLabelWithDescriptionConflicts( $entityType, $labels, $descriptions, $caseSensitive );
		$actual = $this->getEntityIdStrings( $matches );

		$this->assertArrayEquals( $expected, $actual, false, false );
	}

	private function getEntityIdStrings( array $terms ) {
		return array_map( function( Term $term ) {
			$id = $term->getEntityId();
			return $id->getSerialization();
		}, $terms );
	}

	public function testGetTermsOfEntity() {
		$lookup = $this->getTermIndex();

		$item = new Item( new ItemId( 'Q568234314' ) );

		$item->setLabel( 'en', 'abc' );
		$item->setLabel( 'de', 'def' );
		$item->setLabel( 'nl', 'ghi' );
		$item->setDescription( 'en', 'testGetTermsOfEntity' );
		$item->setAliases( 'fr', array( 'o', '_', 'O' ) );

		$this->assertTrue( $lookup->saveTermsOfEntity( $item ) );

		$labelTerms = $lookup->getTermsOfEntity( $item->getId(), array( 'label' ) );
		$this->assertEquals( 3, count( $labelTerms ), "expected 3 labels" );

		$englishTerms = $lookup->getTermsOfEntity( $item->getId(), null, array( 'en' ) );
		$this->assertEquals( 2, count( $englishTerms ), "expected 2 English terms" );

		$germanLabelTerms = $lookup->getTermsOfEntity( $item->getId(), array( 'label' ), array( 'de' ) );
		$this->assertEquals( 1, count( $germanLabelTerms ), "expected 1 German label" );

		$noTerms = $lookup->getTermsOfEntity( $item->getId(), array( 'label' ), array() );
		$this->assertEmpty( $noTerms, "expected no labels" );

		$noTerms = $lookup->getTermsOfEntity( $item->getId(), array(), array( 'de' ) );
		$this->assertEmpty( $noTerms, "expected no labels" );

		$terms = $lookup->getTermsOfEntity( $item->getId() );
		$this->assertEquals( 7, count( $terms ), "expected 7 terms for item" );

		// make list of strings for easy checking
		$term_keys = array();
		foreach ( $terms as $t ) {
			$term_keys[] = $t->getType() . '/' .  $t->getLanguage() . '/' . $t->getText();
		}

		$k = Term::TYPE_LABEL . '/en/abc';
		$this->assertContains( $k, $term_keys,
			"expected to find $k in terms for item" );

		$k = Term::TYPE_DESCRIPTION . '/en/testGetTermsOfEntity';
		$this->assertContains( $k, $term_keys,
			"expected to find $k in terms for item" );

		$k = Term::TYPE_ALIAS . '/fr/_';
		$this->assertContains( $k, $term_keys,
			"expected to find $k in terms for item" );
	}

	public function testGetTermsOfEntities() {
		$lookup = $this->getTermIndex();

		$item1 = new Item( new ItemId( 'Q568234314' ) );

		$item1->setLabel( 'en', 'abc' );
		$item1->setLabel( 'de', 'def' );
		$item1->setLabel( 'nl', 'ghi' );
		$item1->setDescription( 'en', 'one description' );
		$item1->setAliases( 'fr', array( 'o', '_', 'O' ) );

		$item2 = new Item( new ItemId( 'Q87236423' ) );

		$item2->setLabel( 'en', 'xyz' );
		$item2->setLabel( 'de', 'uvw' );
		$item2->setLabel( 'nl', 'rst' );
		$item2->setDescription( 'en', 'another description' );
		$item2->setAliases( 'fr', array( 'X', '~', 'x' ) );

		$this->assertTrue( $lookup->saveTermsOfEntity( $item1 ) );
		$this->assertTrue( $lookup->saveTermsOfEntity( $item2 ) );

		$itemIds = array( $item1->getId(), $item2->getId() );

		$labelTerms = $lookup->getTermsOfEntities( $itemIds, array( Term::TYPE_LABEL ) );
		$this->assertEquals( 6, count( $labelTerms ), "expected 3 labels" );

		$englishTerms = $lookup->getTermsOfEntities( $itemIds, null, array( 'en' ) );
		$this->assertEquals( 4, count( $englishTerms ), "expected 2 English terms" );

		$englishTerms = $lookup->getTermsOfEntities( array( $item1->getId() ), null, array( 'en' ) );
		$this->assertEquals( 2, count( $englishTerms ), "expected 2 English terms" );

		$germanLabelTerms = $lookup->getTermsOfEntities( $itemIds, array( Term::TYPE_LABEL ), array( 'de' ) );
		$this->assertEquals( 2, count( $germanLabelTerms ), "expected 1 German label" );

		$noTerms = $lookup->getTermsOfEntities( $itemIds, array( Term::TYPE_LABEL ), array() );
		$this->assertEmpty( $noTerms, "expected no labels" );

		$noTerms = $lookup->getTermsOfEntities( $itemIds, array(), array( 'de' ) );
		$this->assertEmpty( $noTerms, "expected no labels" );

		$terms = $lookup->getTermsOfEntities( $itemIds );
		$this->assertEquals( 14, count( $terms ), "expected 7 terms for item" );

		// make list of strings for easy checking
		$term_keys = array();
		foreach ( $terms as $t ) {
			$term_keys[] = $t->getType() . '/' .  $t->getLanguage() . '/' . $t->getText();
		}

		$k = Term::TYPE_LABEL . '/en/abc';
		$this->assertContains( $k, $term_keys,
			"expected to find $k in terms for item" );

		$k = Term::TYPE_LABEL . '/en/xyz';
		$this->assertContains( $k, $term_keys,
			"expected to find $k in terms for item" );

		$k = Term::TYPE_DESCRIPTION . '/en/another description';
		$this->assertContains( $k, $term_keys,
			"expected to find $k in terms for item" );

		$k = Term::TYPE_ALIAS . '/fr/x';
		$this->assertContains( $k, $term_keys,
			"expected to find $k in terms for item" );
	}

	protected function assertTermExists( TermIndex $termIndex, $text, $termType = null, $language = null, $entityType = null ) {
		$this->assertTrue( $this->termExists( $termIndex, $text, $termType, $language, $entityType ) );
	}

	protected function assertNotTermExists( TermIndex $termIndex, $text, $termType = null, $language = null, $entityType = null ) {
		$this->assertFalse( $this->termExists( $termIndex, $text, $termType, $language, $entityType ) );
	}

	private function termExists( TermIndex $termIndex, $text, $termType = null, $language = null, $entityType = null ) {
		$termFields = array();
		$termFields['termText'] = $text;

		if ( $language !== null ) {
			$termFields['termLanguage'] = $language;
		}

		$matches = $termIndex->getMatchingTerms( array( new Term( $termFields ) ), $termType, $entityType );
		return !empty( $matches );
	}

}
