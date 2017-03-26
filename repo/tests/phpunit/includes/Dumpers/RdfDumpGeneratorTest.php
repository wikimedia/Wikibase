<?php

namespace Wikibase\Repo\Tests\Dumpers;

use MediaWikiTestCase;
use MWException;
use Site;
use SiteList;
use Title;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Entity\NullEntityPrefetcher;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\Dumpers\RdfDumpGenerator;
use Wikibase\EntityRevision;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Lib\Store\RevisionedUnresolvedRedirectException;
use Wikibase\Rdf\RdfVocabulary;
use Wikibase\Repo\Tests\Rdf\NTriplesRdfTestHelper;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Repo\Tests\Rdf\RdfBuilderTest;
use Wikibase\Repo\Tests\Rdf\RdfBuilderTestData;

/**
 * @covers Wikibase\Dumpers\RdfDumpGenerator
 * @covers Wikibase\Dumpers\DumpGenerator
 *
 * @group Wikibase
 * @group WikibaseRdf
 *
 * @license GPL-2.0+
 * @author Stas Malyshev
 */
class RdfDumpGeneratorTest extends MediaWikiTestCase {

	const URI_BASE = 'http://acme.test/';
	const URI_DATA = 'http://data.acme.test/';

	/**
	 * @var NTriplesRdfTestHelper
	 */
	private $helper;

	protected function setUp() {
		parent::setUp();

		$this->helper = new NTriplesRdfTestHelper(
			new RdfBuilderTestData(
				__DIR__ . '/../../data/rdf/entities',
				__DIR__ . '/../../data/rdf/RdfDumpGenerator'
			)
		);
	}

	/**
	 * @return SiteList
	 */
	public function getSiteList() {
		$list = new SiteList();

		$wiki = new Site();
		$wiki->setGlobalId( 'enwiki' );
		$wiki->setLanguageCode( 'en' );
		$wiki->setLinkPath( 'http://enwiki.acme.test/$1' );
		$list['enwiki'] = $wiki;

		$wiki = new Site();
		$wiki->setGlobalId( 'ruwiki' );
		$wiki->setLanguageCode( 'ru' );
		$wiki->setLinkPath( 'http://ruwiki.acme.test/$1' );
		$list['ruwiki'] = $wiki;

		$wiki = new Site();
		$wiki->setGlobalId( 'test' );
		$wiki->setLanguageCode( 'test' );
		$wiki->setGroup( 'acmetest' );
		$wiki->setLinkPath( 'http://test.acme.test/$1' );
		$list['test'] = $wiki;

		return $list;
	}

	private function getTestData() {
		return $this->helper->getTestData();
	}

	/**
	 * @return EntityTitleLookup
	 */
	private function getEntityTitleLookup() {
		$entityTitleLookup = $this->getMock( EntityTitleLookup::class );
		$entityTitleLookup->expects( $this->any() )
			->method( 'getTitleForId' )
			->will( $this->returnCallback( function( EntityId $entityId ) {
				return Title::newFromText( $entityId->getSerialization() );
			} ) );

		return $entityTitleLookup;
	}

	/**
	 * @param string $flavor
	 * @param EntityDocument[] $entities
	 * @param EntityId[] $redirects
	 *
	 * @return RdfDumpGenerator
	 * @throws MWException
	 */
	protected function newDumpGenerator( $flavor, array $entities = [], array $redirects = [] ) {
		$out = fopen( 'php://output', 'w' );

		$entityLookup = $this->getMock( EntityLookup::class );
		$entityRevisionLookup = $this->getMock( EntityRevisionLookup::class );

		$dataTypeLookup = $this->getTestData()->getMockRepository();

		$entityLookup->expects( $this->any() )
			->method( 'getEntity' )
			->will( $this->returnCallback( function( EntityId $id ) use ( $entities, $redirects ) {
				$key = $id->getSerialization();

				if ( isset( $redirects[$key] ) ) {
					throw new RevisionedUnresolvedRedirectException( $id, $redirects[$key] );
				}

				if ( isset( $entities[$key] ) ) {
					return $entities[$key];
				}

				return null;
			} ) );

		$entityRevisionLookup->expects( $this->any() )
			->method( 'getEntityRevision' )
			->will( $this->returnCallback( function( EntityId $id ) use ( $entityLookup ) {
				/** @var EntityLookup $entityLookup */
				$entity = $entityLookup->getEntity( $id );
				if ( !$entity ) {
					return null;
				}
				return new EntityRevision( $entity, 12, wfTimestamp( TS_MW, 1000000 ) );
			}
		) );

		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		// Note: we test against the actual RDF bindings here, so we get actual RDF.
		$rdfBuilderFactory = $wikibaseRepo->getValueSnakRdfBuilderFactory();

		$entityRdfBuilderFactory = $wikibaseRepo->getEntityRdfBuilderFactory();

		return RdfDumpGenerator::createDumpGenerator(
			'ntriples',
			$out,
			$flavor,
			$this->getSiteList(),
			$entityRevisionLookup,
			$dataTypeLookup,
			$rdfBuilderFactory,
			$entityRdfBuilderFactory,
			new NullEntityPrefetcher(),
			new RdfVocabulary(
				self::URI_BASE,
				self::URI_DATA,
				[ 'test' => 'en-x-test' ]
			),
			$this->getEntityTitleLookup()
		);
	}

	public function idProvider() {
		$p10 = new PropertyId( 'P10' );
		$q30 = new ItemId( 'Q30' );
		$q40 = new ItemId( 'Q40' );
		$q4242 = new ItemId( 'Q4242' ); // hardcoded to be a redirect

		return [
			'full empty' => [ [], 'full-dump', 'empty' ],
			'full some entities' => [ [ $p10, $q30, $q40 ], 'full-dump', 'entities' ],
			'full redirect' => [ [ $p10, $q4242 ], 'full-dump', 'redirect' ],
			'truthy empty' => [ [], 'truthy-dump', 'empty' ],
			'truthy some entities' => [ [ $p10, $q30, $q40 ], 'truthy-dump', 'entities' ],
			'truthy redirect' => [ [ $p10, $q4242 ], 'truthy-dump', 'redirect' ],
		];
	}

	/**
	 * @dataProvider idProvider
	 */
	public function testGenerateDump( array $ids, $flavor, $dumpname ) {
		$jsonTest = new JsonDumpGeneratorTest();
		$entities = $jsonTest->makeEntities( $ids );
		$redirects = [ 'Q4242' => new ItemId( 'Q42' ) ];
		$dumper = $this->newDumpGenerator( $flavor, $entities, $redirects );
		$dumper->setTimestamp( 1000000 );
		$jsonTest = new JsonDumpGeneratorTest();
		$pager = $jsonTest->makeIdPager( $ids );

		ob_start();
		$dumper->generateDump( $pager );
		$actual = ob_get_clean();
		$expected = $this->getTestData()->getNTriples( $flavor . '-' . $dumpname );

		$this->helper->assertNTriplesEquals( $expected, $actual );
	}

	public function loadDataProvider() {
		return [
			'references' => [ [ new ItemId( 'Q7' ), new ItemId( 'Q9' ) ], 'refs' ],
		];
	}

	/**
	 * @dataProvider loadDataProvider
	 * @param EntityId[] $ids
	 * @param string $dumpname
	 */
	public function testReferenceDedup( array $ids, $dumpname ) {
		$entities = [];

		foreach ( $ids as $id ) {
			$id = $id->getSerialization();
			$entities[$id] = $this->getTestData()->getEntity( $id );
		}

		$dumper = $this->newDumpGenerator( 'full-dump', $entities );
		$dumper->setTimestamp( 1000000 );
		$jsonTest = new JsonDumpGeneratorTest();
		$pager = $jsonTest->makeIdPager( $ids );

		ob_start();
		$dumper->generateDump( $pager );
		$actual = ob_get_clean();
		$this->helper->assertNTriplesEqualsDataset( $dumpname, $actual );
	}

}
