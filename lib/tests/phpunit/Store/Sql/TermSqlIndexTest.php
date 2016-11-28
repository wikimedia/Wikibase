<?php

namespace Wikibase\Lib\Tests\Store;

use MWException;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Term\AliasGroupList;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermList;
use Wikibase\Lib\EntityIdComposer;
use Wikibase\Lib\Store\TermIndexSearchCriteria;
use Wikibase\StringNormalizer;
use Wikibase\TermIndexEntry;
use Wikibase\TermSqlIndex;
use Wikimedia\Assert\ParameterAssertionException;

/**
 * @covers Wikibase\TermSqlIndex
 *
 * @group Wikibase
 * @group WikibaseLib
 * @group WikibaseStore
 * @group Database
 *
 * @license GPL-2.0+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 * @author Thiemo Mättig
 */
class TermSqlIndexTest extends TermIndexTest {

	protected function setUp() {
		parent::setUp();

		if ( !defined( 'WB_VERSION' ) ) {
			$this->markTestSkipped( "Skipping because a local wb_terms table"
				. " is not available on a WikibaseClient only instance." );
		}

		$this->tablesUsed[] = 'wb_terms';
	}

	public function provideInvalidRepositoryNames() {
		return [
			'repository name containing colon' => [ 'foo:bar' ],
			'non-string as repository name' => [ 12345 ],
		];
	}

	/**
	 * @dataProvider provideInvalidRepositoryNames
	 */
	public function testGivenInvalidRepositoryName_constructorThrowsException( $repositoryName ) {
		$this->setExpectedException( ParameterAssertionException::class );
		new TermSqlIndex(
			new StringNormalizer(),
			new EntityIdComposer( [
				'item' => function( $repositoryName, $uniquePart ) {
					return new ItemId( 'Q' . $uniquePart );
				},
			] ),
			false,
			$repositoryName
		);
	}

	/**
	 * @return TermSqlIndex
	 */
	public function getTermIndex() {
		return new TermSqlIndex(
			new StringNormalizer(),
			new EntityIdComposer( [
				'item' => function( $repositoryName, $uniquePart ) {
					return new ItemId( 'Q' . $uniquePart );
				},
				'property' => function( $repositoryName, $uniquePart ) {
					return new PropertyId( 'P' . $uniquePart );
				},
			] )
		);
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

		$term = new TermIndexSearchCriteria( [ 'termLanguage' => $languageCode, 'termText' => $searchText ] );

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
	 * Returns a fake term index configured for the given repository which uses the local database.
	 *
	 * @param string $repository
	 * @return TermSqlIndex
	 */
	private function getTermIndexForRepository( $repository ) {
		return new TermSqlIndex(
			new StringNormalizer(),
			new EntityIdComposer( [
				'item' => function( $repositoryName, $uniquePart ) {
					return new ItemId( EntityId::joinSerialization( [ $repositoryName, '', 'Q' . $uniquePart ] ) );
				},
				'property' => function( $repositoryName, $uniquePart ) {
					return new PropertyId( EntityId::joinSerialization( [ $repositoryName, '', 'P' . $uniquePart ] ) );
				},
			] ),
			false,
			$repository
		);
	}

	public function testGivenForeignRepositoryName_getMatchingTermsReturnsEntityIdWithTheRepositoryPrefix() {
		$localTermIndex = $this->getTermIndex();

		$item = new Item( new ItemId( 'Q300' ) );
		$item->setLabel( 'en', 'Foo' );

		$localTermIndex->saveTermsOfEntity( $item );

		$fooTermIndex = $this->getTermIndexForRepository( 'foo' );

		$results = $fooTermIndex->getMatchingTerms( [ new TermIndexSearchCriteria( [ 'termText' => 'Foo' ] ) ] );

		$this->assertCount( 1, $results );

		$termIndexEntry = $results[0];

		$this->assertTrue( $termIndexEntry->getEntityId()->equals( new ItemId( 'foo:Q300' ) ) );
		$this->assertEquals( 'Foo', $termIndexEntry->getText() );
	}

	/**
	 * @dataProvider labelWithDescriptionConflictProvider
	 */
	public function testGetLabelWithDescriptionConflicts(
		array $entities,
		$entityType,
		array $labels,
		array $descriptions,
		array $expected
	) {
		$this->markTestSkippedOnMySql();

		parent::testGetLabelWithDescriptionConflicts( $entities, $entityType, $labels, $descriptions, $expected );
	}

	public function getMatchingTermsOptionsProvider() {
		$labels = array(
			'en' => new Term( 'en', 'Foo' ),
			'de' => new Term( 'de', 'Fuh' ),
		);

		$descriptions = array(
			'en' => new Term( 'en', 'Bar' ),
			'de' => new Term( 'de', 'Bär' ),
		);

		$fingerprint = new Fingerprint(
			new TermList( $labels ),
			new TermList( $descriptions ),
			new AliasGroupList()
		);

		$labelFooEn = new TermIndexSearchCriteria( array(
			'termType' => TermIndexEntry::TYPE_LABEL,
			'termLanguage' => 'en',
			'termText' => 'Foo',
		) );
		$descriptionBarEn = new TermIndexSearchCriteria( array(
			'termType' => TermIndexEntry::TYPE_DESCRIPTION,
			'termLanguage' => 'en',
			'termText' => 'Bar',
		) );

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
				// This is not really well defined. Could be either of the two.
				// So use null to show we want something but don't know what it is
				array( null ),
			)
		);
	}

	/**
	 * @dataProvider getMatchingTermsOptionsProvider
	 *
	 * @param Fingerprint $fingerprint
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
			if ( $expectedTerm instanceof TermIndexEntry ) {
				$actualTerm = $actual[$key];
				$this->assertEquals( $expectedTerm->getTermType(), $actualTerm->getTermType(), 'termType' );
				$this->assertEquals( $expectedTerm->getLanguage(), $actualTerm->getLanguage(), 'termLanguage' );
				$this->assertEquals( $expectedTerm->getText(), $actualTerm->getText(), 'termText' );
			}
		}
	}

	public function provideGetSearchKey() {
		return array(
			'basic' => array(
				'foo', // raw
				'foo', // normalized
			),

			'trailing newline' => array(
				"foo \n",
				'foo',
			),

			'whitespace' => array(
				'  foo  ', // raw
				'foo', // normalized
			),

			'lower case of non-ascii character' => array(
				'ÄpFEl', // raw
				'äpfel', // normalized
			),

			'lower case of decomposed character' => array(
				"A\xCC\x88pfel", // raw
				'äpfel', // normalized
			),

			'lower case of cyrillic character' => array(
				'Берлин', // raw
				'берлин', // normalized
			),

			'lower case of greek character' => array(
				'Τάχιστη', // raw
				'τάχιστη', // normalized
			),

			'nasty unicode whitespace' => array(
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
		$id = new ItemId( 'Q999' );

		$fingerprint = new Fingerprint();
		$fingerprint->setLabel( 'en', 'kittens!!!:)' );
		$fingerprint->setDescription( 'es', 'es un gato!' );
		$fingerprint->setAliasGroup( 'en', array( 'kitten-alias' ) );

		$item = new Item( $id );
		$item->setFingerprint( $fingerprint );

		$expectedTerms = array(
			new TermIndexEntry( array(
				'entityId' => new ItemId( 'Q999' ),
				'termText' => 'es un gato!',
				'termLanguage' => 'es',
				'termType' => 'description'
			) ),
			new TermIndexEntry( array(
				'entityId' => new ItemId( 'Q999' ),
				'termText' => 'kittens!!!:)',
				'termLanguage' => 'en',
				'termType' => 'label'
			) ),
			new TermIndexEntry( array(
				'entityId' => new ItemId( 'Q999' ),
				'termText' => 'kitten-alias',
				'termLanguage' => 'en',
				'termType' => 'alias'
			) )
		);

		$entityWithoutTerms = $this->getMock( EntityDocument::class );
		$entityWithoutTerms->expects( $this->any() )
			->method( 'getId' )
			->will( $this->returnValue( $id ) );

		return [
			[ $expectedTerms, $item ],
			[ [], new Item( $id ) ],
			[ [], $entityWithoutTerms ]
		];
	}

	/**
	 * @see http://bugs.mysql.com/bug.php?id=10327
	 * @see EditEntityTest::markTestSkippedOnMySql
	 */
	private function markTestSkippedOnMySql() {
		if ( $this->db->getType() === 'mysql' ) {
			$this->markTestSkipped( 'MySQL doesn\'t support self-joins on temporary tables' );
		}
	}

	public function testGivenForeignRepositoryName_getTermsOfEntitiesReturnsEntityIdsWithRepositoryPrefix() {
		$localTermIndex = $this->getTermIndex();

		$item = new Item( new ItemId( 'Q300' ) );
		$item->setLabel( 'en', 'Foo' );

		$localTermIndex->saveTermsOfEntity( $item );

		$fooTermIndex = $this->getTermIndexForRepository( 'foo' );

		$results = $fooTermIndex->getTermsOfEntities( [ new ItemId( 'foo:Q300' ) ] );

		$this->assertCount( 1, $results );

		$termIndexEntry = $results[0];

		$this->assertTrue( $termIndexEntry->getEntityId()->equals( new ItemId( 'foo:Q300' ) ) );
		$this->assertEquals( 'Foo', $termIndexEntry->getText() );
	}

	public function testGivenEntityIdFromAnotherRepository_getTermsOfEntitiesThrowsException() {
		$fooTermIndex = $this->getTermIndexForRepository( 'foo' );

		$this->setExpectedException( MWException::class );

		$fooTermIndex->getTermsOfEntities( [ new ItemId( 'Q300' ) ] );
	}

	public function testGivenEntityIdFromAnotherRepository_getTermsOfEntityThrowsException() {
		$fooTermIndex = $this->getTermIndexForRepository( 'foo' );

		$this->setExpectedException( MWException::class );

		$fooTermIndex->getTermsOfEntity( new ItemId( 'Q300' ) );
	}

	public function testGivenEntityFromAnotherRepository_getEntityTermsThrowsException() {
		$fooTermIndex = $this->getTermIndexForRepository( 'foo' );

		$this->setExpectedException( MWException::class );

		$fooTermIndex->getEntityTerms( new Item( new ItemId( 'Q300' ) ) );
	}

	public function testGivenEntityFromAnotherRepository_saveTermsOfEntityThrowsException() {
		$fooTermIndex = $this->getTermIndexForRepository( 'foo' );

		$item = new Item( new ItemId( 'Q300' ) );
		$item->setLabel( 'en', 'Foo' );

		$this->setExpectedException( MWException::class );

		$fooTermIndex->saveTermsOfEntity( $item );
	}

	public function testGivenEntityFromAnotherRepository_deleteTermsOfEntityThrowsException() {
		$fooTermIndex = $this->getTermIndexForRepository( 'foo' );

		$this->setExpectedException( MWException::class );

		$fooTermIndex->deleteTermsOfEntity( new ItemId( 'Q300' ) );
	}

}
