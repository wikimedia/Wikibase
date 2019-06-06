<?php

namespace Wikibase\Lib\Tests\Store\Sql\Terms;

use PHPUnit\Framework\TestCase;
use Wikibase\Lib\Store\Sql\Terms\DatabaseTermIdsAcquirer;
use Wikibase\Lib\Store\Sql\Terms\InMemoryTypeIdsStore;
use Wikibase\TermStore\MediaWiki\Tests\Util\FakeLoadBalancer;
use Wikimedia\Rdbms\DatabaseSqlite;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * @covers \Wikibase\Lib\Store\Sql\Terms\DatabaseTermIdsAcquirer
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class DatabaseTermIdsAcquirerTest extends TestCase {

	/**
	 * @var IDatabase
	 */
	private $db;

	/**
	 * @var ILoadBalancer
	 */
	private $loadBalancer;

	public function setUp() {
		$this->db = $this->setUpNewDb();
		$this->loadBalancer = new FakeLoadBalancer( [
			'dbr' => $this->db
		] );
	}

	private function setUpNewDb() {
		$db = DatabaseSqlite::newStandaloneInstance( ':memory:' );
		$db->sourceFile(
			__DIR__ . '/../../../../../../repo/sql/AddNormalizedTermsTablesDDL.sql' );

		return $db;
	}

	public function testAcquireTermIdsReturnsArrayOfIdsForAllTerms() {
		$typeIdsAcquirer = new InMemoryTypeIdsStore();

		$dbTermIdsAcquirer = new DatabaseTermIdsAcquirer(
			$this->loadBalancer,
			$typeIdsAcquirer
		);

		$termsArray = [
			'label' => [
				'en' => 'same',
				'de' => 'same',
			],
			'description' => [ 'en' => 'same' ],
			'alias' => [
				'en' => [ 'same', 'same', 'another', 'yet another' ]
			]
		];

		$acquiredTermIds = $dbTermIdsAcquirer->acquireTermIds( $termsArray );

		$this->assertInternalType( 'array', $acquiredTermIds );
		$this->assertCount( 7, $acquiredTermIds );
	}

	public function testAcquireTermIdsStoresTermsInDatabase() {
		$typeIdsAcquirer = new InMemoryTypeIdsStore();
		$alreadyAcquiredTypeIds = $typeIdsAcquirer->acquireTypeIds(
			[ 'label', 'description', 'alias' ]
		);

		$dbTermIdsAcquirer = new DatabaseTermIdsAcquirer(
			$this->loadBalancer,
			$typeIdsAcquirer
		);

		$termsArray = [
			'label' => [
				'en' => 'same',
				'de' => 'same',
			],
			'description' => [ 'en' => 'same' ],
			'alias' => [
				'en' => [ 'same', 'same', 'another', 'yet another' ]
			]
		];

		$acquiredTermIds = $dbTermIdsAcquirer->acquireTermIds( $termsArray );

		$this->assertTermsArrayExistInDb( $termsArray, $alreadyAcquiredTypeIds );
	}

	public function testAcquireTermIdsStoresOnlyUniqueTexts() {
		$typeIdsAcquirer = new InMemoryTypeIdsStore();

		$dbTermIdsAcquirer = new DatabaseTermIdsAcquirer(
			$this->loadBalancer,
			$typeIdsAcquirer
		);

		$termsArray = [
			'label' => [
				'en' => 'same',
				'de' => 'same',
			],
			'description' => [ 'en' => 'same' ],
			'alias' => [
				'en' => [ 'same', 'same', 'another', 'yet another' ]
			]
		];

		$acquiredTermIds = $dbTermIdsAcquirer->acquireTermIds( $termsArray );

		$this->assertSame(
			3,
			$this->db->selectRowCount( 'wbt_text', '*' )
		);
	}

	public function testAcquireTermIdsStoresOnlyUniqueTextInLang() {
		$typeIdsAcquirer = new InMemoryTypeIdsStore();

		$dbTermIdsAcquirer = new DatabaseTermIdsAcquirer(
			$this->loadBalancer,
			$typeIdsAcquirer
		);

		$termsArray = [
			'label' => [
				'en' => 'same',
				'de' => 'same',
			],
			'description' => [ 'en' => 'same' ],
			'alias' => [
				'en' => [ 'same', 'same', 'another', 'yet another' ]
			]
		];

		$acquiredTermIds = $dbTermIdsAcquirer->acquireTermIds( $termsArray );

		$this->assertSame(
			4,
			$this->db->selectRowCount( 'wbt_text_in_lang', '*' )
		);
	}

	public function testAcquireTermIdsStoresOnlyUniqueTermInLang() {
		$typeIdsAcquirer = new InMemoryTypeIdsStore();

		$dbTermIdsAcquirer = new DatabaseTermIdsAcquirer(
			$this->loadBalancer,
			$typeIdsAcquirer
		);

		$termsArray = [
			'label' => [
				'en' => 'same',
				'de' => 'same',
			],
			'description' => [ 'en' => 'same' ],
			'alias' => [
				'en' => [ 'same', 'same', 'another', 'yet another' ]
			]
		];

		$acquiredTermIds = $dbTermIdsAcquirer->acquireTermIds( $termsArray );

		$this->assertSame(
			6,
			$this->db->selectRowCount( 'wbt_term_in_lang', '*' )
		);
	}

	public function testAcquireTermIdsReusesExistingTerms() {
		$termsArray = [
			'label' => [
				'en' => 'same',
				'de' => 'same',
			],
			'description' => [ 'en' => 'same' ],
			'alias' => [
				'en' => [ 'same', 'same', 'another', 'yet another' ]
			]
		];

		// We will populate DB with two terms that both have
		// text "same". One is of type "label" in language "en",
		// and the other is of type "alias" in language "en.
		//
		// TermIdsAcquirer should then reuse those terms for the given
		// termsArray above, meaning thoese pre-inserted terms will
		// appear (their ids) in the returned array from
		// TermIdsAcquirer::acquireTermIds( $termsArray )
		$typeIdsAcquirer = new InMemoryTypeIdsStore();
		$alreadyAcquiredTypeIds = $typeIdsAcquirer->acquireTypeIds(
			[ 'label', 'description', 'alias' ]
		);

		$this->db->insert( 'wbt_text', [ 'wbx_text' => 'same' ] );
		$sameTextId = $this->db->insertId();

		$this->db->insert(
			'wbt_text_in_lang',
			[ 'wbxl_text_id' => $sameTextId, 'wbxl_language' => 'en' ]
		);
		$enSameTextInLangId = $this->db->insertId();

		$this->db->insert(
			'wbt_term_in_lang',
			[ 'wbtl_text_in_lang_id' => $enSameTextInLangId,
			  'wbtl_type_id' => $alreadyAcquiredTypeIds['label'] ]
		);
		$labelEnSameTermInLangId = (string)$this->db->insertId();

		$this->db->insert(
			'wbt_term_in_lang',
			[ 'wbtl_text_in_lang_id' => $enSameTextInLangId,
			  'wbtl_type_id' => $alreadyAcquiredTypeIds['alias'] ]
		);
		$aliasEnSameTermInLangId = (string)$this->db->insertId();

		$dbTermIdsAcquirer = new DatabaseTermIdsAcquirer(
			$this->loadBalancer,
			$typeIdsAcquirer
		);

		$acquiredTermIds = $dbTermIdsAcquirer->acquireTermIds( $termsArray );

		$this->assertCount( 7, $acquiredTermIds );

		// We will assert that the returned ids of acquired terms contains
		// one occurence of the term id for en label "same" that already existed in db,
		// and two occurences of the term id for en alias "same" that already existed
		// in db.
		$this->assertCount(
			1,
			array_filter(
				$acquiredTermIds,
				function ( $id ) use ( $labelEnSameTermInLangId ) {
					return $id === $labelEnSameTermInLangId;
				}
			)
		);
		$this->assertCount(
			2,
			array_filter(
				$acquiredTermIds,
				function ( $id ) use ( $aliasEnSameTermInLangId ) {
					return $id === $aliasEnSameTermInLangId;
				}
			)
		);
	}

	public function testRestoresAcquiredIdsWhenDeletedInParallelBeforeReturn() {
		$typeIdsAcquirer = new InMemoryTypeIdsStore();
		$alreadyAcquiredTypeIds = $typeIdsAcquirer->acquireTypeIds(
			[ 'label', 'description', 'alias' ]
		);

		$termsArray = [
			'label' => [
				'en' => 'same',
				'de' => 'same',
			],
			'description' => [ 'en' => 'same' ],
			'alias' => [
				'en' => [ 'same', 'same', 'another', 'yet another' ]
			]
		];

		$dbTermIdsAcquirer = new DatabaseTermIdsAcquirer(
			$this->loadBalancer,
			$typeIdsAcquirer
		);

		$acquiredTermIds = $dbTermIdsAcquirer->acquireTermIds(
			$termsArray,
			function ( $acquiredIds ) {
				// This callback will delete first 3 acquired ids to mimic the case
				// in which a cleaner might be working in-parallel and deleting ids
				// that overlap with the ones returned by this acquirer.
				// The expected behavior is that acquirer will make sure to restore
				// those deleted ids after they have been passed to this callback,
				// in which they are expected to be used as foreign keys in other tables.

				$idsToDelete = array_slice( $acquiredIds, 0, 3 );
				$this->db->delete( 'wbt_term_in_lang', [ 'wbtl_id' => $idsToDelete ] );
			}
		);
		$uniqueAcquiredTermIds = array_values( array_unique( $acquiredTermIds ) );
		$persistedTermIds = $this->db->selectFieldValues( 'wbt_term_in_lang', 'wbtl_id' );

		sort( $uniqueAcquiredTermIds );
		sort( $persistedTermIds );

		$this->assertSame(
			$uniqueAcquiredTermIds,
			$persistedTermIds
		);

		$this->assertTermsArrayExistInDb( $termsArray, $alreadyAcquiredTypeIds );
	}


	public function testCallsCallbackEvenWhenAcquiringNoTerms() {
		$dbTermIdsAcquirer = new DatabaseTermIdsAcquirer(
			$this->loadBalancer,
			new InMemoryTypeIdsStore()
		);
		$called = false;

		$dbTermIdsAcquirer->acquireTermIds(
			[],
			function ( $termIds ) use ( &$called ) {
				$called = true;
				$this->assertEmpty( $termIds );
			}
		);

		$this->assertTrue( $called, 'callback should have been called' );
	}

	public function testIgnoresReplicaInRestoration() {
		$dbMaster = $this->setUpNewDb();
		$loadBalancer = new FakeLoadBalancer( [
			'dbr' => $this->db,
			'dbw' => $dbMaster
		] );

		$typeIdsAcquirer = new InMemoryTypeIdsStore();
		$alreadyAcquiredTypeIds = $typeIdsAcquirer->acquireTypeIds(
			[ 'label', 'description', 'alias' ]
		);

		$termsArray = [
			'label' => [ 'en' => 'same' ],
			'description' => [ 'en' => 'same' ],
			'alias' => [
				'en' => [ 'same', 'another' ]
			]
		];

		$dbTermIdsAcquirer = new DatabaseTermIdsAcquirer(
			$loadBalancer,
			$typeIdsAcquirer,
			function ( $acquiredIds ) use ( $dbMaster ) {
				// This is going to:
				// 1. insert into replica all records that exist in master
				//    mimicing replication.
				// 2. delete everything in master, but keep replica as-is,
				//    mimicing a state where replication of new master state
				//    has not happened yet
				// Expected behavior of the acquirer is that it will entirely
				// ignore the state of replica when restoring cleaned up ids.
				// Meaning that since no records exist in master after this callback,
				// the acquirer will restore those records in master again.

				// 1. Replicating master records into replica
				$recordsInMaster = $dbMaster->select( 'wbt_text', [ 'wbx_id', 'wbx_text' ] );
				$recordsToInsertIntoReplica = [];
				foreach ( $recordsInMaster as $record ) {
					$recordsToInsertIntoReplica[] = [
						'wbx_id' => $record->wbx_id,
						'wbx_text' => $record->wbx_text
					];
				}
				$this->db->insert( 'wbt_text', $recordsToInsertIntoReplica );

				$recordsInMaster = $dbMaster->select( 'wbt_text_in_lang', [ 'wbxl_id', 'wbxl_text_id', 'wbxl_language' ] );
				$recordsToInsertIntoReplica = [];
				foreach ( $recordsInMaster as $record ) {
					$recordsToInsertIntoReplica[] = [
						'wbxl_id' => $record->wbxl_id,
						'wbxl_text_id' => $record->wbxl_text_id,
						'wbxl_language' => $record->wbxl_language
					];
				}
				$this->db->insert( 'wbt_text_in_lang', $recordsToInsertIntoReplica );


				$recordsInMaster = $dbMaster->select( 'wbt_term_in_lang', [ 'wbtl_id', 'wbtl_text_in_lang_id', 'wbtl_type_id' ] );
				$recordsToInsertIntoReplica = [];
				foreach ( $recordsInMaster as $record ) {
					$recordsToInsertIntoReplica[] = [
						'wbtl_id' => $record->wbtl_id,
						'wbtl_text_in_lang_id' => $record->wbtl_text_in_lang_id,
						'wbtl_type_id' => $record->wbtl_type_id
					];
				}
				$this->db->insert( 'wbt_term_in_lang', $recordsToInsertIntoReplica );

				// 2. Deleting records from master
				$dbMaster->delete( 'wbt_text', '*' );
				$dbMaster->delete( 'wbt_text_in_lang', '*' );
				$dbMaster->delete( 'wbt_term_in_lang', '*' );
			}
		);

		$dbTermIdsAcquirer->acquireTermIds( $termsArray );

		$this->assertTermsArrayExistInDb( $termsArray, $alreadyAcquiredTypeIds, $dbMaster );
	}

	private function assertTermsArrayExistInDb( $termsArray, $typeIds, $db = null ) {
		$db = $db ?? $this->db;

		foreach ( $termsArray as $type => $textsPerLang ) {
			foreach ( $textsPerLang as $lang => $texts ) {
				foreach ( (array)$texts as $text ) {
					$textId = $db->selectField(
						'wbt_text',
						'wbx_id',
						[ 'wbx_text' => $text ]
					);

					$this->assertNotEmpty(
						$textId,
						"Expected record for text '$text' is not in wbt_text"
					);

					$textInLangId = $db->selectField(
						'wbt_text_in_lang',
						'wbxl_id',
						[ 'wbxl_language' => $lang, 'wbxl_text_id' => $textId ]
					);

					$this->assertNotEmpty(
						$textInLangId,
						"Expected text '$text' in language '$lang' is not in wbt_text_in_lang"
					);

					$this->assertNotEmpty(
						$db->selectField(
							'wbt_term_in_lang',
							'wbtl_id',
							[ 'wbtl_type_id' => $typeIds[$type], 'wbtl_text_in_lang_id' => $textInLangId ]
						),
						"Expected $type '$text' in language '$lang' is not in wbt_term_in_lang"
					);
				}
			}
		}
	}

	public function testAcquireTermIdsWithEmptyInput() {
		$typeIdsAcquirer = new InMemoryTypeIdsStore();
		$dbTermIdsAcquirer = new DatabaseTermIdsAcquirer(
			$this->loadBalancer,
			$typeIdsAcquirer
		);

		$acquiredTermIds = $dbTermIdsAcquirer->acquireTermIds( [] );

		$this->assertEmpty( $acquiredTermIds );
	}

}
