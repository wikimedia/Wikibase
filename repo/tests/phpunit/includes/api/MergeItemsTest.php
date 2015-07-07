<?php

namespace Wikibase\Test\Repo\Api;

use Language;
use Status;
use TestSites;
use User;
use Wikibase\Repo\Api\ApiErrorReporter;
use Wikibase\Repo\Api\MergeItems;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Repo\Interactors\ItemMergeInteractor;
use Wikibase\Repo\Interactors\RedirectCreationInteractor;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Test\EntityModificationTestHelper;
use Wikibase\Test\MockRepository;
use Wikibase\Lib\Store\EntityRedirect;


/**
 * @covers Wikibase\Repo\Api\MergeItems
 *
 * @group API
 * @group Wikibase
 * @group WikibaseAPI
 * @group WikibaseRepo
 * @group MergeItemsTest
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Adam Shorland
 * @author Lucie-Aimée Kaffee
 */
class MergeItemsTest extends \MediaWikiTestCase {

	/**
	 * @var MockRepository|null
	 */
	private $mockRepository = null;

	/**
	 * @var EntityModificationTestHelper|null
	 */
	private $entityModificationTestHelper = null;

	/**
	 * @var ApiModuleTestHelper|null
	 */
	private $apiModuleTestHelper = null;

	protected function setUp() {
		parent::setUp();

		$this->setUpSites();

		$this->entityModificationTestHelper = new EntityModificationTestHelper();
		$this->apiModuleTestHelper = new ApiModuleTestHelper();

		$this->mockRepository = $this->entityModificationTestHelper->getMockRepository();

		$this->entityModificationTestHelper->putEntities( array(
			'Q1' => array(),
			'Q2' => array(),
			'P1' => array( 'datatype' => 'string' ),
			'P2' => array( 'datatype' => 'string' ),
		) );

		$this->entityModificationTestHelper->putRedirects( array(
			'Q11' => 'Q1',
			'Q12' => 'Q2',
		) );
	}

	private function setUpSites() {
		static $isSetup = false;

		if ( !$isSetup ) {
			$sitesTable = WikibaseRepo::getDefaultInstance()->getSiteStore();
			$sitesTable->clear();
			$sitesTable->saveSites( TestSites::getSites() );

			$isSetup = true;
		}
	}

	private function getPermissionCheckers() {
		$permissionChecker = $this->getMock( 'Wikibase\Repo\Store\EntityPermissionChecker' );

		$permissionChecker->expects( $this->any() )
			->method( 'getPermissionStatusForEntityId' )
			->will( $this->returnCallback( function( User $user, $permission ) {
				if ( $user->getName() === 'UserWithoutPermission' && $permission === 'edit' ) {
					return Status::newFatal( 'permissiondenied' );
				} else {
					return Status::newGood();
				}
			} ) );

		return $permissionChecker;
	}

	/**
	 * @param EntityRedirect|null $redirect
	 *
	 * @return RedirectCreationInteractor
	 */
	public function getMockRedirectCreationInteractor( EntityRedirect $redirect = null ) {
		$mock = $this->getMockBuilder( 'Wikibase\Repo\Interactors\RedirectCreationInteractor' )
			->disableOriginalConstructor()
			->getMock();

		if ( $redirect ) {
			$mock->expects( $this->once() )
				->method( 'createRedirect' )
				->with( $redirect->getEntityId(), $redirect->getTargetId() )
				->will( $this->returnCallback( function() use ( $redirect ) {
					return $redirect;
				} ) );
		} else {
			$mock->expects( $this->never() )
				->method( 'createRedirect' );
		}

		return $mock;
	}

	/**
	 * @param MergeItems $module
	 */
	private function overrideServices( MergeItems $module, EntityRedirect $expectedRedirect = null ) {
		$idParser = new BasicEntityIdParser();

		$errorReporter = new ApiErrorReporter(
			$module,
			WikibaseRepo::getDefaultInstance()->getExceptionLocalizer(),
			Language::factory( 'en' )
		);

		$mockContext = $this->getMock( 'RequestContext' );
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$apiHelperFactory = $wikibaseRepo->getApiHelperFactory( $mockContext );

		$resultBuilder = $apiHelperFactory->getResultBuilder( $module );
		$summaryFormatter = $wikibaseRepo->getSummaryFormatter();

		$changeOpsFactory = $wikibaseRepo->getChangeOpFactoryProvider()->getMergeChangeOpFactory();

		$module->setServices(
			$idParser,
			$errorReporter,
			$resultBuilder,
			new ItemMergeInteractor(
				$changeOpsFactory,
				$this->mockRepository,
				$this->mockRepository,
				$this->getPermissionCheckers(),
				$summaryFormatter,
				$module->getUser(),
				$this->getMockRedirectCreationInteractor( $expectedRedirect )
			)
		);
	}

	private function callApiModule( $params, EntityRedirect $expectedRedirect = null ) {
		$module = $this->apiModuleTestHelper->newApiModule( 'Wikibase\Repo\Api\MergeItems', 'wbmergeitems', $params );
		$this->overrideServices( $module, $expectedRedirect );

		$module->execute();

		$data = $module->getResult()->getResultData( null, array(
			'BC' => array(),
			'Types' => array(),
			'Strip' => 'all',
		) );
		return $data;
	}

	public function provideData() {
		$testCases = array();
		$testCases['labelMerge'] = array(
			array( 'labels' => array( 'en' => array( 'language' => 'en', 'value' => 'foo' ) ) ),
			array(),
			array(),
			array( 'labels' => array( 'en' => array( 'language' => 'en', 'value' => 'foo' ) ) ),
			true,
		);
		$testCases['IgnoreConflictSitelinksMerge'] = array(
			array( 'sitelinks' => array(
				'dewiki' => array( 'site' => 'dewiki', 'title' => 'RemainFrom' ),
				'enwiki' => array( 'site' => 'enwiki', 'title' => 'PlFrom' ),
			) ),
			array( 'sitelinks' => array( 'dewiki' => array( 'site' => 'dewiki', 'title' => 'RemainTo' ) ) ),
			array( 'sitelinks' => array( 'dewiki' => array( 'site' => 'dewiki', 'title' => 'RemainFrom' ) ) ),
			array( 'sitelinks' => array(
				'dewiki' => array( 'site' => 'dewiki', 'title' => 'RemainTo' ),
				'enwiki' => array( 'site' => 'enwiki', 'title' => 'PlFrom' ),
			) ),
			false,
			'sitelink',
		);
		$testCases['claimMerge'] = array(
			array( 'claims' => array( 'P1' => array( array( 'mainsnak' => array(
				'snaktype' => 'value', 'property' => 'P1', 'datavalue' => array( 'value' => 'imastring', 'type' => 'string' ) ),
				'type' => 'statement', 'rank' => 'normal', 'id' => 'deadbeefdeadbeefdeadbeefdeadbeef' ) ) ) ),
			array(),
			array(),
			array( 'claims' => array( 'P1' => array( array( 'mainsnak' => array(
				'snaktype' => 'value', 'property' => 'P1', 'datavalue' => array( 'value' => 'imastring', 'type' => 'string' ) ),
				'type' => 'statement', 'rank' => 'normal' ) ) ) ),
			true,
		);

		return $testCases;
	}

	/**
	 * @dataProvider provideData
	 */
	public function testMergeRequest( $pre1, $pre2, $expectedFrom, $expectedTo, $expectRedirect, $ignoreConflicts = null ) {
		// -- set up params ---------------------------------
		$params = array(
			'action' => 'wbmergeitems',
			'fromid' => 'Q1',
			'toid' => 'Q2',
			'summary' => 'CustomSummary!',
		);
		if ( $ignoreConflicts !== null ) {
			$params['ignoreconflicts'] = $ignoreConflicts;
		}

		// -- prefill the entities --------------------------------------------
		$this->entityModificationTestHelper->putEntity( $pre1, 'Q1' );
		$this->entityModificationTestHelper->putEntity( $pre2, 'Q2' );

		// -- do the request --------------------------------------------
		$redirect = $expectRedirect ? new EntityRedirect( new ItemId( 'Q1' ), new ItemId( 'Q2' ) ): null;
		$result = $this->callApiModule( $params, $redirect );

		// -- check the result --------------------------------------------
		$this->assertResultCorrect( $result );

		// -- check the items --------------------------------------------
		$this->assertItemsCorrect( $result, $expectedFrom, $expectedTo );

		// -- check redirect --------------------------------------------
		$this->assertRedirectCorrect( $result, $redirect );

		// -- check the edit summaries --------------------------------------------
		$this->assertEditSummariesCorrect( $result );
	}

	private function assertResultCorrect( array $result ) {
		$this->apiModuleTestHelper->assertResultSuccess( $result );

		$this->apiModuleTestHelper->assertResultHasKeyInPath( array( 'from', 'id' ), $result );
		$this->apiModuleTestHelper->assertResultHasKeyInPath( array( 'to', 'id' ), $result );
		$this->assertEquals( 'Q1', $result['from']['id'] );
		$this->assertEquals( 'Q2', $result['to']['id'] );

		$this->apiModuleTestHelper->assertResultHasKeyInPath( array( 'from', 'lastrevid' ), $result );
		$this->apiModuleTestHelper->assertResultHasKeyInPath( array( 'to', 'lastrevid' ), $result );
		$this->assertGreaterThan( 0, $result['from']['lastrevid'] );
		$this->assertGreaterThan( 0, $result['to']['lastrevid'] );
	}

	private function assertItemsCorrect( array $result, array $expectedFrom, array $expectedTo ) {
		$actualFrom = $this->entityModificationTestHelper->getEntity( $result['from']['id'], true ); //resolve redirects
		$this->entityModificationTestHelper->assertEntityEquals( $expectedFrom, $actualFrom );

		$actualTo = $this->entityModificationTestHelper->getEntity( $result['to']['id'], true );
		$this->entityModificationTestHelper->assertEntityEquals( $expectedTo, $actualTo );
	}

	private function assertRedirectCorrect( array $result, EntityRedirect $redirect = null ) {
		$this->assertArrayHasKey( 'redirected', $result );

		if ( $redirect ) {
			$this->assertEquals( 1, $result['redirected'] );
		} else {
			$this->assertEquals( 0, $result['redirected'] );
		}
	}

	private function assertEditSummariesCorrect( array $result ) {
		$this->entityModificationTestHelper->assertRevisionSummary( array( 'wbmergeitems' ), $result['from']['lastrevid'] );
		$this->entityModificationTestHelper->assertRevisionSummary( '/CustomSummary/', $result['from']['lastrevid'] );
		$this->entityModificationTestHelper->assertRevisionSummary( array( 'wbmergeitems' ), $result['to']['lastrevid'] );
		$this->entityModificationTestHelper->assertRevisionSummary( '/CustomSummary/', $result['to']['lastrevid'] );
	}

	public function provideExceptionParamsData() {
		return array(
			array( //0 no ids given
				'p' => array(),
				'e' => array( 'exception' => array( 'type' => 'UsageException', 'code' => 'param-missing' ) ) ),
			array( //1 only from id
				'p' => array( 'fromid' => 'Q1' ),
				'e' => array( 'exception' => array( 'type' => 'UsageException', 'code' => 'param-missing' ) ) ),
			array( //2 only to id
				'p' => array( 'toid' => 'Q1' ),
				'e' => array( 'exception' => array( 'type' => 'UsageException', 'code' => 'param-missing' ) ) ),
			array( //3 toid bad
				'p' => array( 'fromid' => 'Q1', 'toid' => 'ABCDE' ),
				'e' => array( 'exception' => array( 'type' => 'UsageException', 'code' => 'invalid-entity-id' ) ) ),
			array( //4 fromid bad
				'p' => array( 'fromid' => 'ABCDE', 'toid' => 'Q1' ),
				'e' => array( 'exception' => array( 'type' => 'UsageException', 'code' => 'invalid-entity-id' ) ) ),
			array( //5 both same id
				'p' => array( 'fromid' => 'Q1', 'toid' => 'Q1' ),
				'e' => array( 'exception' => array( 'type' => 'UsageException', 'code' => 'invalid-entity-id', 'message' => 'You must provide unique ids' ) ) ),
			array( //6 from id is property
				'p' => array( 'fromid' => 'P1', 'toid' => 'Q1' ),
				'e' => array( 'exception' => array( 'type' => 'UsageException', 'code' => 'not-item' ) ) ),
			array( //7 to id is property
				'p' => array( 'fromid' => 'Q1', 'toid' => 'P1' ),
				'e' => array( 'exception' => array( 'type' => 'UsageException', 'code' => 'not-item' ) ) ),
			array( //8 bad ignoreconficts
				'p' => array( 'fromid' => 'Q2', 'toid' => 'Q2', 'ignoreconflicts' => 'foo' ),
				'e' => array( 'exception' => array( 'type' => 'UsageException', 'code' => 'invalid-entity-id' ) ) ),
			array( //9 bad ignoreconficts
				'p' => array( 'fromid' => 'Q2', 'toid' => 'Q2', 'ignoreconflicts' => 'label|foo' ),
				'e' => array( 'exception' => array( 'type' => 'UsageException', 'code' => 'invalid-entity-id' ) ) ),
		);
	}

	/**
	 * @dataProvider provideExceptionParamsData
	 */
	public function testMergeItemsParamsExceptions( $params, $expected ) {
		// -- set any defaults ------------------------------------
		$params['action'] = 'wbmergeitems';

		try {
			$this->callApiModule( $params );
			$this->fail( 'Expected UsageException!' );
		} catch ( \UsageException $ex ) {
			$this->apiModuleTestHelper->assertUsageException( $expected, $ex );
		}
	}

	public function provideExceptionConflictsData() {
		return array(
			array(
				array( 'descriptions' => array( 'en' => array( 'language' => 'en', 'value' => 'foo' ) ) ),
				array( 'descriptions' => array( 'en' => array( 'language' => 'en', 'value' => 'foo2' ) ) ),
			),
			array(
				array( 'sitelinks' => array( 'dewiki' => array( 'site' => 'dewiki', 'title' => 'Foo' ) ) ),
				array( 'sitelinks' => array( 'dewiki' => array( 'site' => 'dewiki', 'title' => 'Foo2' ) ) ),
			),
		);
	}

	/**
	 * @dataProvider provideExceptionConflictsData
	 */
	public function testMergeItemsConflictsExceptions( $pre1, $pre2 ) {
		$expected = array( 'exception' => array( 'type' => 'UsageException', 'code' => 'failed-save' ) );

		// -- prefill the entities --------------------------------------------
		$this->entityModificationTestHelper->putEntity( $pre1, 'Q1' );
		$this->entityModificationTestHelper->putEntity( $pre2, 'Q2' );

		$params = array(
			'action' => 'wbmergeitems',
			'fromid' => 'Q1',
			'toid' => 'Q2',
		);

		// -- do the request --------------------------------------------
		try {
			$this->callApiModule( $params );
			$this->fail( 'Expected UsageException!' );
		} catch ( \UsageException $ex ) {
			$this->apiModuleTestHelper->assertUsageException( $expected, $ex );
		}
	}

	public function testMergeNonExistingItem() {
		$params = array(
			'action' => 'wbmergeitems',
			'fromid' => 'Q60457977',
			'toid' => 'Q60457978'
		);

		try {
			$this->callApiModule( $params );
			$this->fail( 'Expected UsageException!' );
		} catch ( \UsageException $ex ) {
			$this->apiModuleTestHelper->assertUsageException( 'no-such-entity', $ex );
		}
	}

}
