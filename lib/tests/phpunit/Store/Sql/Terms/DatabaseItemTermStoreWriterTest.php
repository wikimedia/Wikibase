<?php

namespace Wikibase\Lib\Tests\Store\Sql\Terms;

use InvalidArgumentException;
use MediaWikiTestCase;
use WANObjectCache;
use Wikibase\DataAccess\EntitySource;
use Wikibase\DataAccess\Tests\DataAccessSettingsFactory;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermList;
use Wikibase\Lib\Store\Sql\Terms\DatabaseItemTermStoreWriter;
use Wikibase\Lib\Store\Sql\Terms\DatabasePropertyTermStoreWriter;
use Wikibase\Lib\Store\Sql\Terms\DatabaseTermInLangIdsAcquirer;
use Wikibase\Lib\Store\Sql\Terms\DatabaseTermStoreCleaner;
use Wikibase\Lib\Store\Sql\Terms\DatabaseTypeIdsStore;
use Wikibase\Lib\Tests\Store\Sql\Terms\Util\FakeLBFactory;
use Wikibase\Lib\Tests\Store\Sql\Terms\Util\FakeLoadBalancer;
use Wikibase\StringNormalizer;
use Wikibase\WikibaseSettings;

/**
 * @covers \Wikibase\Lib\Store\Sql\Terms\DatabaseItemTermStoreWriter
 *
 * @group Database
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class DatabaseItemTermStoreWriterTest extends MediaWikiTestCase {

	use DatabaseTermStoreWriterTestGetTermsTrait;

	/** @var ItemId */
	private $i1;

	/** @var Fingerprint */
	private $fingerprint1;

	/** @var Fingerprint */
	private $fingerprint2;

	/** @var Fingerprint */
	private $fingerprintEmpty;

	protected function setUp() : void {
		parent::setUp();
		if ( !WikibaseSettings::isRepoEnabled() ) {
			$this->markTestSkipped( "Skipping because WikibaseClient doesn't have local term store tables." );
		}

		$this->tablesUsed[] = 'wbt_type';
		$this->tablesUsed[] = 'wbt_text';
		$this->tablesUsed[] = 'wbt_text_in_lang';
		$this->tablesUsed[] = 'wbt_term_in_lang';
		$this->tablesUsed[] = 'wbt_item_terms';
		$this->tablesUsed[] = 'wbt_property_terms';

		$this->i1 = new ItemId( 'Q1' );

		$this->fingerprint1 = new Fingerprint(
			new TermList( [ new Term( 'en', 'some label' ) ] ),
			new TermList( [ new Term( 'en', 'description' ) ] )
		);
		$this->fingerprint2 = new Fingerprint(
			new TermList( [ new Term( 'en', 'another label' ) ] ),
			new TermList( [ new Term( 'en', 'description' ) ] )
		);
		$this->fingerprintEmpty = new Fingerprint();
	}

	private function getItemTermStoreWriter(
		?EntitySource $itemSourceOverride = null
	) : DatabaseItemTermStoreWriter {
		$loadBalancer = new FakeLoadBalancer( [
			'dbr' => $this->db,
		] );
		$lbFactory = new FakeLBFactory( [
			'lb' => $loadBalancer
		] );
		$typeIdsStore = new DatabaseTypeIdsStore(
			$loadBalancer,
			WANObjectCache::newEmpty()
		);

		return new DatabaseItemTermStoreWriter(
			$loadBalancer,
			new DatabaseTermInLangIdsAcquirer(
				$lbFactory,
				$typeIdsStore
			),
			new DatabaseTermStoreCleaner(
				$loadBalancer
			),
			new StringNormalizer(),
			$itemSourceOverride ?: $this->getItemSource(),
			DataAccessSettingsFactory::entitySourceBasedFederation()
		);
	}

	private function getItemSource() {
		return new EntitySource( 'test', false, [ 'item' => [ 'namespaceId' => 100, 'slot' => 'main' ] ], '', '', '', '' );
	}

	private function getNonLocalItemSource() {
		return new EntitySource( 'remote', 'someDb', [ 'item' => [ 'namespaceId' => 100, 'slot' => 'main' ] ], '', '', '', '' );
	}

	public function testStoreTerms_throwsForNonLocalItemSource() {
		$store = $this->getItemTermStoreWriter( $this->getNonLocalItemSource() );

		$this->expectException( InvalidArgumentException::class );
		$store->storeTerms( new ItemId( 'Q1' ), $this->fingerprintEmpty );
	}

	public function testStoreAndGetTerms() {
		$store = $this->getItemTermStoreWriter();

		$store->storeTerms(
			$this->i1,
			$this->fingerprint1
		);

		$fingerprint = $this->getTermsForItem( $this->i1 );

		$this->assertEquals( $this->fingerprint1, $fingerprint );
	}

	public function testStoreEmptyAndGetTerms() {
		$store = $this->getItemTermStoreWriter();

		$store->storeTerms(
			$this->i1,
			$this->fingerprintEmpty
		);

		$fingerprint = $this->getTermsForItem( $this->i1 );

		$this->assertTrue( $fingerprint->isEmpty() );
	}

	public function testDeleteTermsWithoutStore() {
		$store = $this->getItemTermStoreWriter();

		$store->deleteTerms( $this->i1 );
		$this->assertTrue( true, 'did not throw an error' );
	}

	public function testStoreSameFingerprintTwiceAndGetTerms() {
		$store = $this->getItemTermStoreWriter();

		$store->storeTerms(
			$this->i1,
			$this->fingerprint1
		);
		$store->storeTerms(
			$this->i1,
			$this->fingerprint1
		);

		$fingerprint = $this->getTermsForItem( $this->i1 );

		$this->assertEquals( $this->fingerprint1, $fingerprint );
	}

	public function testStoreTwoFingerprintsAndGetTerms() {
		$store = $this->getItemTermStoreWriter();

		$store->storeTerms(
			$this->i1,
			$this->fingerprint1
		);
		$store->storeTerms(
			$this->i1,
			$this->fingerprint2
		);

		$fingerprint = $this->getTermsForItem( $this->i1 );

		$this->assertEquals( $this->fingerprint2, $fingerprint );
	}

	public function testStoreAndDeleteAndGetTerms() {
		$store = $this->getItemTermStoreWriter();

		$store->storeTerms(
			$this->i1,
			$this->fingerprint1
		);

		$store->deleteTerms( $this->i1 );

		$fingerprint = $this->getTermsForItem( $this->i1 );

		$this->assertTrue( $fingerprint->isEmpty() );
	}

	public function testRemovingSharedTermDoesNotGetUndulyDeleted() {
		$store = $this->getItemTermStoreWriter();
		$sharedFingerprint = new Fingerprint(
			new TermList( [ new Term( 'en', 'Cat' ) ] )
		);
		$item1 = new ItemId( 'Q1' );
		$item2 = new ItemId( 'Q2' );
		$store->storeTerms( $item1, $sharedFingerprint );
		$store->storeTerms( $item2, $sharedFingerprint );

		$store->storeTerms( $item1, $this->fingerprintEmpty );

		$this->assertTrue( $this->getTermsForItem( $item2 )->equals( $sharedFingerprint ) );
	}

	public function testRemovingSharedAndUnsharedTermDoesntRemoveUsedTerms() {
		$store = $this->getItemTermStoreWriter();
		$sharedTerm = new Term( 'en', 'Cat' );
		$item1Fingerprint = new Fingerprint(
			new TermList( [ $sharedTerm ] ),
			new TermList( [ new Term( 'en', 'Dog' ) ] )
		);
		$item2Fingerprint = new Fingerprint(
			new TermList( [ $sharedTerm ] ),
			new TermList( [ new Term( 'en', 'Goat' ) ] )
		);
		$item1 = new ItemId( 'Q1' );
		$item2 = new ItemId( 'Q2' );
		$store->storeTerms( $item1, $item1Fingerprint );
		$store->storeTerms( $item2, $item2Fingerprint );

		$store->storeTerms( $item1, $this->fingerprintEmpty );

		$this->assertTrue( $this->getTermsForItem( $item2 )->equals( $item2Fingerprint ) );
	}

	public function testStoreTermsCleansUpRemovedTerms() {
		$store = $this->getItemTermStoreWriter();

		$store->storeTerms(
			$this->i1,
			new Fingerprint(
				new TermList( [ new Term( 'en', 'The real name of UserName is John Doe' ) ] )
			)
		);
		$store->storeTerms(
			$this->i1,
			$this->fingerprintEmpty
		);

		$this->assertSelect(
			'wbt_text',
			'wbx_text',
			[ 'wbx_text' => 'The real name of UserName is John Doe' ],
			[ /* empty */ ]
		);
	}

	public function testDeleteTermsCleansUpRemovedTerms() {
		$store = $this->getItemTermStoreWriter();

		$store->storeTerms(
			$this->i1,
			new Fingerprint(
				new TermList( [ new Term( 'en', 'The real name of UserName is John Doe' ) ] )
			)
		);
		$store->deleteTerms( $this->i1 );

		$this->assertSelect(
			'wbt_text',
			'wbx_text',
			[ 'wbx_text' => 'The real name of UserName is John Doe' ],
			[ /* empty */ ]
		);
	}

	public function testStoreTerms_throwsForNonItemEntitySource() {
		$store = $this->getTermStoreNotHandlingItems();

		$this->expectException( InvalidArgumentException::class );
		$store->storeTerms( new ItemId( 'Q1' ), $this->fingerprintEmpty );
	}

	public function testDeleteTerms_throwsForNonItemEntitySource() {
		$store = $this->getTermStoreNotHandlingItems();

		$this->expectException( InvalidArgumentException::class );
		$store->deleteTerms( new ItemId( 'Q1' ) );
	}

	private function getTermStoreNotHandlingItems() {
		$loadBalancer = new FakeLoadBalancer( [
			'dbr' => $this->db,
		] );
		$typeIdsStore = new DatabaseTypeIdsStore(
			$loadBalancer,
			WANObjectCache::newEmpty()
		);

		return new DatabaseItemTermStoreWriter(
			$loadBalancer,
			new DatabaseTermInLangIdsAcquirer(
				new FakeLBFactory( [
					'lb' => $loadBalancer
				] ),
				$typeIdsStore
			),
			new DatabaseTermStoreCleaner(
				$loadBalancer
			),
			new StringNormalizer(),
			$this->getPropertySource(),
			DataAccessSettingsFactory::entitySourceBasedFederation()
		);
	}

	public function testStoresAndGetsUTF8Text() {
		$store = $this->getItemTermStoreWriter();

		$this->fingerprint1->setDescription(
			'utf8',
			'ఒక వ్యక్తి లేదా సంస్థ సాధించిన రికార్డు. ఈ రికార్డును సాధించిన కోల్పోయిన తేదీలను చూపేందుకు క్'
		);

		$store->storeTerms(
			$this->i1,
			$this->fingerprint1
		);

		$fingerprint = $this->getTermsForItem( $this->i1 );

		$this->assertEquals( $this->fingerprint1, $fingerprint );
	}

	public function testT237984UnexpectedMissingTextRow() {
		$itemStoreWriter = $this->getItemTermStoreWriter();

		$loadBalancer = new FakeLoadBalancer( [
			'dbr' => $this->db,
		] );
		$lbFactory = new FakeLBFactory( [
			'lb' => $loadBalancer
		] );
		$typeIdsStore = new DatabaseTypeIdsStore(
			$loadBalancer,
			WANObjectCache::newEmpty()
		);
		$propertyTermStoreWriter = new DatabasePropertyTermStoreWriter(
			$loadBalancer,
			new DatabaseTermInLangIdsAcquirer(
				$lbFactory,
				$typeIdsStore
			),
			new DatabaseTermStoreCleaner(
				$loadBalancer
			),
			new StringNormalizer(),
			$this->getPropertySource(),
			DataAccessSettingsFactory::entitySourceBasedFederation()
		);

		$propertyTermStoreWriter->storeTerms( new PropertyId( 'P12' ), new Fingerprint(
			new TermList( [ new Term( 'nl', 'van' ) ] )
		) );
		$itemStoreWriter->storeTerms( new ItemId( 'Q99' ), new Fingerprint(
			new TermList(),
			new TermList( [ new Term( 'af', 'van' ) ] )
		) );

		// Store with empty fingerprint (will delete things)
		$itemStoreWriter->storeTerms( new ItemId( 'Q99' ), new Fingerprint() );

		$r = $this->getTermsForProperty( new PropertyId( 'P12' ) );
		$this->assertTrue( $r->hasLabel( 'nl' ) );
		$this->assertEquals( 'van', $r->getLabel( 'nl' )->getText() );
	}

	private function getPropertySource() {
		return new EntitySource( 'test', false, [ 'property' => [ 'namespaceId' => 123, 'slot' => 'main' ] ], '', '', '', '' );
	}

}
