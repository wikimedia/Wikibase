<?php

namespace Wikibase\Lib\Tests\Store\Sql\Terms;

use InvalidArgumentException;
use MediaWikiTestCase;
use WANObjectCache;
use Wikibase\DataAccess\EntitySource;
use Wikibase\DataAccess\Tests\DataAccessSettingsFactory;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermList;
use Wikibase\Lib\Store\Sql\Terms\DatabasePropertyTermStoreWriter;
use Wikibase\Lib\Store\Sql\Terms\DatabaseTermInLangIdsAcquirer;
use Wikibase\Lib\Store\Sql\Terms\DatabaseTermStoreCleaner;
use Wikibase\Lib\Store\Sql\Terms\DatabaseTypeIdsStore;
use Wikibase\Lib\Tests\Store\Sql\Terms\Util\FakeLBFactory;
use Wikibase\Lib\Tests\Store\Sql\Terms\Util\FakeLoadBalancer;
use Wikibase\StringNormalizer;
use Wikibase\WikibaseSettings;

/**
 * @covers \Wikibase\Lib\Store\Sql\Terms\DatabasePropertyTermStoreWriter
 *
 * @group Database
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class DatabasePropertyTermStoreWriterTest extends MediaWikiTestCase {

	use DatabaseTermStoreWriterTestGetTermsTrait;

	/** @var PropertyId */
	private $p1;

	/** @var Fingerprint */
	private $fingerprint1;

	/** @var Fingerprint */
	private $fingerprint2;

	/** @var Fingerprint */
	private $fingerprintEmpty;

	protected function setUp() : void {
		if ( !WikibaseSettings::isRepoEnabled() ) {
			$this->markTestSkipped( "Skipping because WikibaseClient doesn't have local term store tables." );
		}

		parent::setUp();
		$this->tablesUsed[] = 'wbt_type';
		$this->tablesUsed[] = 'wbt_text';
		$this->tablesUsed[] = 'wbt_text_in_lang';
		$this->tablesUsed[] = 'wbt_term_in_lang';
		$this->tablesUsed[] = 'wbt_property_terms';

		$this->p1 = new PropertyId( 'P1' );
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

	private function getPropertyTermStoreWriter(
		?EntitySource $propertySourceOverride = null
	) : DatabasePropertyTermStoreWriter {
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
		return new DatabasePropertyTermStoreWriter(
			$loadBalancer,
			new DatabaseTermInLangIdsAcquirer(
				$lbFactory,
				$typeIdsStore
			),
			new DatabaseTermStoreCleaner(
				$loadBalancer
			),
			new StringNormalizer(),
			$propertySourceOverride ?: $this->getPropertySource(),
			DataAccessSettingsFactory::entitySourceBasedFederation()
		);
	}

	private function getPropertySource() {
		return new EntitySource( 'test', false, [ 'property' => [ 'namespaceId' => 123, 'slot' => 'main' ] ], '', '', '', '' );
	}

	private function getNonLocalPropertySource() {
		return new EntitySource( 'remote', 'someDb', [ 'item' => [ 'namespaceId' => 100, 'slot' => 'main' ] ], '', '', '', '' );
	}

	public function testStoreTerms_throwsForNonLocalItemSource() {
		$store = $this->getPropertyTermStoreWriter( $this->getNonLocalPropertySource() );

		$this->expectException( InvalidArgumentException::class );
		$store->storeTerms( new PropertyId( 'P1' ), $this->fingerprintEmpty );
	}

	public function testStoreAndGetTerms() {
		$store = $this->getPropertyTermStoreWriter();

		$store->storeTerms(
			$this->p1,
			$this->fingerprint1
		);

		$fingerprint = $this->getTermsForProperty( $this->p1 );

		$this->assertEquals( $this->fingerprint1, $fingerprint );
	}

	public function testStoreEmptyAndGetTerms() {
		$store = $this->getPropertyTermStoreWriter();

		$store->storeTerms(
			$this->p1,
			$this->fingerprintEmpty
		);

		$fingerprint = $this->getTermsForProperty( $this->p1 );

		$this->assertTrue( $fingerprint->isEmpty() );
	}

	public function testDeleteTermsWithoutStore() {
		$store = $this->getPropertyTermStoreWriter();

		$store->deleteTerms( $this->p1 );
		$this->assertTrue( true, 'did not throw an error' );
	}

	public function testStoreSameFingerprintTwiceAndGetTerms() {
		$store = $this->getPropertyTermStoreWriter();

		$store->storeTerms(
			$this->p1,
			$this->fingerprint1
		);
		$store->storeTerms(
			$this->p1,
			$this->fingerprint1
		);

		$fingerprint = $this->getTermsForProperty( $this->p1 );

		$this->assertEquals( $this->fingerprint1, $fingerprint );
	}

	public function testStoreTwoFingerprintsAndGetTerms() {
		$store = $this->getPropertyTermStoreWriter();

		$store->storeTerms(
			$this->p1,
			$this->fingerprint1
		);
		$store->storeTerms(
			$this->p1,
			$this->fingerprint2
		);

		$fingerprint = $this->getTermsForProperty( $this->p1 );

		$this->assertEquals( $this->fingerprint2, $fingerprint );
	}

	public function testStoreAndDeleteAndGetTerms() {
		$store = $this->getPropertyTermStoreWriter();

		$store->storeTerms(
			$this->p1,
			$this->fingerprint1
		);

		$store->deleteTerms( $this->p1 );

		$fingerprint = $this->getTermsForProperty( $this->p1 );

		$this->assertTrue( $fingerprint->isEmpty() );
	}

	public function testStoreTermsCleansUpRemovedTerms() {
		$store = $this->getPropertyTermStoreWriter();

		$store->storeTerms(
			$this->p1,
			new Fingerprint(
				new TermList( [ new Term( 'en', 'The real name of UserName is John Doe' ) ] )
			)
		);
		$store->storeTerms(
			$this->p1,
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
		$store = $this->getPropertyTermStoreWriter();

		$store->storeTerms(
			$this->p1,
			new Fingerprint(
				new TermList( [ new Term( 'en', 'The real name of UserName is John Doe' ) ] )
			)
		);
		$store->deleteTerms( $this->p1 );

		$this->assertSelect(
			'wbt_text',
			'wbx_text',
			[ 'wbx_text' => 'The real name of UserName is John Doe' ],
			[ /* empty */ ]
		);
	}

	public function testStoreTerms_throwsForNonPropertyEntitySource() {
		$store = $this->getTermStoreNotHandlingProperties();

		$this->expectException( InvalidArgumentException::class );

		$store->storeTerms( new PropertyId( 'P1' ), $this->fingerprintEmpty );
	}

	public function testDeleteTerms_throwsForNonPropertyEntitySource() {
		$store = $this->getTermStoreNotHandlingProperties();

		$this->expectException( InvalidArgumentException::class );
		$store->deleteTerms( new PropertyId( 'P1' ) );
	}

	private function getTermStoreNotHandlingProperties() {
		$loadBalancer = new FakeLoadBalancer( [
			'dbr' => $this->db,
		] );
		$typeIdsStore = new DatabaseTypeIdsStore(
			$loadBalancer,
			WANObjectCache::newEmpty()
		);

		return new DatabasePropertyTermStoreWriter(
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
			new EntitySource( 'test', false, [ 'item' => [ 'namespaceId' => 123, 'slot' => 'main' ] ], '', '', '', '' ),
			DataAccessSettingsFactory::entitySourceBasedFederation()
		);
	}

	public function testStoresAndGetsUTF8Text() {
		$store = $this->getPropertyTermStoreWriter();

		$this->fingerprint1->setDescription(
			'utf8',
			'ఒక వ్యక్తి లేదా సంస్థ సాధించిన రికార్డు. ఈ రికార్డును సాధించిన కోల్పోయిన తేదీలను చూపేందుకు క్'
		);

		$store->storeTerms(
			$this->p1,
			$this->fingerprint1
		);

		$fingerprint = $this->getTermsForProperty( $this->p1 );

		$this->assertEquals( $this->fingerprint1, $fingerprint );
	}

}
