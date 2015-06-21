<?php

namespace Wikibase\Test;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Term\AliasGroupList;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\TermList;
use Wikibase\StringNormalizer;
use Wikibase\TermIndexEntry;
use Wikibase\TermSqlIndex;

/**
 * @covers Wikibase\TermSqlIndex
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseStore
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 * @author Thiemo Mättig
 */
class TermSqlIndexTest extends TermIndexTest {

	protected function setUp() {
		parent::setUp();

		$this->tablesUsed[] = 'wb_terms';
	}

	/**
	 * @return TermSqlIndex
	 */
	public function getTermIndex() {
		$normalizer = new StringNormalizer();
		return new TermSqlIndex( $normalizer );
	}

	public function termProvider() {
		$argLists = array();

		$argLists[] = array( 'en', 'FoO', 'fOo', true );
		$argLists[] = array( 'ru', 'Берлин', 'берлин', true );

		$argLists[] = array( 'en', 'FoO', 'bar', false );
		$argLists[] = array( 'ru', 'Берлин', 'бе55585рлин', false );

		return $argLists;
	}

	/**
	 * @dataProvider termProvider
	 */
	public function testGetMatchingTerms2( $languageCode, $termText, $searchText, $matches ) {
		$termIndex = $this->getTermIndex();
		$termIndex->clear();

		$item = new Item( new ItemId( 'Q42' ) );
		$item->setLabel( $languageCode, $termText );

		$termIndex->saveTermsOfEntity( $item );

		$term = new TermIndexEntry();
		$term->setLanguage( $languageCode );
		$term->setText( $searchText );

		$options = array(
			'caseSensitive' => false,
		);

		//FIXME: test with arrays for term types and entity types!
		$obtainedTerms = $termIndex->getMatchingTerms( array( $term ), TermIndexEntry::TYPE_LABEL, Item::ENTITY_TYPE, $options );

		$this->assertEquals( $matches ? 1 : 0, count( $obtainedTerms ) );

		if ( $matches ) {
			$obtainedTerm = array_shift( $obtainedTerms );

			$this->assertEquals( $termText, $obtainedTerm->getText() );
		}
	}

	/**
	 * @dataProvider labelWithDescriptionConflictProvider
	 */
	public function testGetLabelWithDescriptionConflicts( $entities, $entityType, $labels, $descriptions, $expected ) {

		if ( wfGetDB( DB_MASTER )->getType() === 'mysql' ) {
			// Mysql fails (http://bugs.mysql.com/bug.php?id=10327), so we cannot test this properly when using MySQL.
			$this->markTestSkipped( 'Can\'t test self-joins on MySQL' );

			return;
		}

		parent::testGetLabelWithDescriptionConflicts( $entities, $entityType, $labels, $descriptions, $expected );
	}

	public function getMatchingTermsOptionsProvider() {
		$labels = array(
			'en' => new \Wikibase\DataModel\Term\Term( 'en', 'Foo' ),
			'de' => new \Wikibase\DataModel\Term\Term( 'de', 'Fuh' ),
		);

		$descriptions = array(
			'en' => new \Wikibase\DataModel\Term\Term( 'en', 'Bar' ),
			'de' => new \Wikibase\DataModel\Term\Term( 'de', 'Bär' ),
		);

		$fingerprint = new Fingerprint(
			new TermList( $labels ),
			new TermList( $descriptions ),
			new AliasGroupList()
		);

		$labelFooEn = new TermIndexEntry( array( 'termLanguage' => 'en', 'termType' => TermIndexEntry::TYPE_LABEL, 'termText' => 'Foo' ) );
		$descriptionBarEn = new TermIndexEntry( array( 'termLanguage' => 'en', 'termType' => TermIndexEntry::TYPE_DESCRIPTION, 'termText' => 'Bar' ) );

		return array(
			'no options' => array(
				$fingerprint,
				array( $labelFooEn ),
				array(),
				array( $labelFooEn ),
			),
			'LIMIT options' => array(
				$fingerprint,
				array( $labelFooEn, $descriptionBarEn ),
				array( 'LIMIT' => 1 ),
				array( $descriptionBarEn ), // FIXME: This is not really well defined. Could be either of the two.
			)
		);
	}

	/**
	 * @dataProvider getMatchingTermsOptionsProvider
	 *
*@param Fingerprint $fingerprint
	 * @param TermIndexEntry[] $queryTerms
	 * @param array $options
	 * @param TermIndexEntry[] $expected
	 */
	public function testGetMatchingTerms_options( Fingerprint $fingerprint, array $queryTerms, array $options, array $expected ) {
		$termIndex = $this->getTermIndex();
		$termIndex->clear();

		$item = new Item( new ItemId( 'Q42' ) );
		$item->setFingerprint( $fingerprint );

		$termIndex->saveTermsOfEntity( $item );

		$actual = $termIndex->getMatchingTerms( $queryTerms, null, null, $options );

		$this->assertSameSize( $expected, $actual );

		foreach ( $expected as $key => $expectedTerm ) {
			$this->assertArrayHasKey( $key, $actual );

			$actualTerm = $actual[$key];
			$this->assertEquals( $expectedTerm->getType(), $actualTerm->getType(), 'termType' );
			$this->assertEquals( $expectedTerm->getLanguage(), $actualTerm->getLanguage(), 'termLanguage' );
			$this->assertEquals( $expectedTerm->getText(), $actualTerm->getText(), 'termText' );
		}
	}

	/**
	 * @dataProvider termProvider
	 */
	public function testGetMatchingTermsWeights( $languageCode, $termText, $searchText, $matches ) {
		$termIndex = $this->getTermIndex();

		$termIndex->clear();

		$item1 = new Item( new ItemId( 'Q42' ) );
		$item1->setLabel( $languageCode, $termText );
		$item1->getSiteLinkList()->addNewSiteLink( 'enwiki', 'A' );

		$termIndex->saveTermsOfEntity( $item1 );

		$item2 = new Item( new ItemId( 'Q23' ) );
		$item2->setLabel( $languageCode, $termText );
		$item2->getSiteLinkList()->addNewSiteLink( 'enwiki', 'B' );
		$item2->getSiteLinkList()->addNewSiteLink( 'dewiki', 'B' );
		$item2->getSiteLinkList()->addNewSiteLink( 'hrwiki', 'B' );
		$item2->getSiteLinkList()->addNewSiteLink( 'uzwiki', 'B' );

		$termIndex->saveTermsOfEntity( $item2 );

		// The number of labels counts too
		$item3 = new Item( new ItemId( 'Q108' ) );
		$item3->setLabel( $languageCode, $termText );
		$item3->setLabel( 'qxy', $termText );
		$item3->setLabel( 'qxz', $termText );

		$termIndex->saveTermsOfEntity( $item3 );

		$term = new TermIndexEntry();
		$term->setLanguage( $languageCode );
		$term->setText( $searchText );

		$options = array(
			'caseSensitive' => false,
		);

		$obtainedIDs = $termIndex->getMatchingIDs( array( $term ), Item::ENTITY_TYPE, $options );

		$this->assertEquals( $matches ? 3 : 0, count( $obtainedIDs ) );

		if ( $matches ) {
			$expectedResult = array( $item2->getId(), $item3->getId(), $item1->getId() );
			$this->assertArrayEquals( $expectedResult, $obtainedIDs, true );
		}
	}

	/**
	 * @dataProvider termProvider
	 */
	public function testGetMatchingIDs_withoutEntityType( $languageCode, $termText, $searchText, $matches ) {
		$termIndex = $this->getTermIndex();
		$termIndex->clear();

		$item1 = new Item( new ItemId( 'Q42' ) );
		$item1->setLabel( $languageCode, $termText );

		$termIndex->saveTermsOfEntity( $item1 );

		$term = new TermIndexEntry();
		$term->setLanguage( $languageCode );
		$term->setText( $termText );

		$obtainedIDs = $termIndex->getMatchingIDs( array( $term ) );

		$this->assertNotEmpty( $obtainedIDs );
	}

	/**
	 * @dataProvider termProvider
	 */
	public function testPrefixSearch( $languageCode, $termText, $searchText, $matches ) {
		$termIndex = $this->getTermIndex();
		$termIndex->clear();

		$item1 = new Item( new ItemId( 'Q42' ) );
		$item1->setLabel( $languageCode, $termText );

		$termIndex->saveTermsOfEntity( $item1 );

		$term = new TermIndexEntry();
		$term->setLanguage( $languageCode );
		$term->setText( substr( $termText, 0, -1 ) ); //last character stripped

		$options = array(
			'caseSensitive' => false,
			'prefixSearch' => true,
		);

		$obtainedIDs = $termIndex->getMatchingIDs( array( $term ), Item::ENTITY_TYPE, $options );

		$this->assertNotEmpty( $obtainedIDs );
	}

	/**
	 * @dataProvider termProvider
	 */
	public function testPrefixSearchQuoting( $languageCode, $termText, $searchText, $matches ) {
		$termIndex = $this->getTermIndex();
		$termIndex->clear();

		$item1 = new Item( new ItemId( 'Q42' ) );
		$item1->setLabel( $languageCode, $termText );

		$termIndex->saveTermsOfEntity( $item1 );

		$term = new TermIndexEntry();
		$term->setLanguage( $languageCode );
		$term->setText( '%' . $termText ); //must be used as a character and no LIKE placeholder

		$options = array(
			'caseSensitive' => false,
			'prefixSearch' => true,
		);

		$obtainedIDs = $termIndex->getMatchingIDs( array( $term ), Item::ENTITY_TYPE, $options );

		$this->assertEmpty( $obtainedIDs );
	}

	public function provideGetSearchKey() {
		return array(
			array( // #0
				'foo', // raw
				'foo', // normalized
			),

			array( // #1
				'  foo  ', // raw
				'foo', // normalized
			),

			array( // #2: lower case of non-ascii character
				'ÄpFEl', // raw
				'äpfel', // normalized
			),

			array( // #3: lower case of decomposed character
				"A\xCC\x88pfel", // raw
				'äpfel', // normalized
			),

			array( // #4: lower case of cyrillic character
				'Берлин', // raw
				'берлин', // normalized
			),

			array( // #5: lower case of greek character
				'Τάχιστη', // raw
				'τάχιστη', // normalized
			),

			array( // #6: nasty unicode whitespace
				// ZWNJ: U+200C \xE2\x80\x8C
				// RTLM: U+200F \xE2\x80\x8F
				// PSEP: U+2029 \xE2\x80\xA9
				"\xE2\x80\x8F\xE2\x80\x8Cfoo\xE2\x80\x8Cbar\xE2\x80\xA9", // raw
				"foo bar", // normalized
			),
		);
	}

	/**
	 * @dataProvider provideGetSearchKey
	 */
	public function testGetSearchKey( $raw, $normalized ) {
		$index = $this->getTermIndex();

		$key = $index->getSearchKey( $raw );
		$this->assertEquals( $normalized, $key );
	}

	/**
	 * @dataProvider getEntityTermsProvider
	 */
	public function testGetEntityTerms( $expectedTerms, EntityDocument $entity ) {
		$termIndex = $this->getTermIndex();
		$wikibaseTerms = $termIndex->getEntityTerms( $entity );

		$this->assertEquals( $expectedTerms, $wikibaseTerms );
	}

	public function getEntityTermsProvider() {
		$fingerprint = new Fingerprint();
		$fingerprint->setLabel( 'en', 'kittens!!!:)' );
		$fingerprint->setDescription( 'es', 'es un gato!' );
		$fingerprint->setAliasGroup( 'en', array( 'kitten-alias' ) );

		$item = new Item( new ItemId( 'Q999' ) );
		$item->setFingerprint( $fingerprint );

		$expectedTerms = array(
			new TermIndexEntry( array(
				'entityId' => 999,
				'entityType' => 'item',
				'termText' => 'es un gato!',
				'termLanguage' => 'es',
				'termType' => 'description'
			) ),
			new TermIndexEntry( array(
				'entityId' => 999,
				'entityType' => 'item',
				'termText' => 'kittens!!!:)',
				'termLanguage' => 'en',
				'termType' => 'label'
			) ),
			new TermIndexEntry( array(
				'entityId' => 999,
				'entityType' => 'item',
				'termText' => 'kitten-alias',
				'termLanguage' => 'en',
				'termType' => 'alias'
			) )
		);

		return array(
			array( $expectedTerms, $item ),
			array( array(), new Item() ),
			array( array(), $this->getMock( 'Wikibase\DataModel\Entity\EntityDocument' ) )
		);
	}

}
