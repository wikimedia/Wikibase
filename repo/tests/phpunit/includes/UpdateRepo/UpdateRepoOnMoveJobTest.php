<?php

namespace Wikibase\Repo\Tests\UpdateRepo;

use HashSiteStore;
use NullStatsdDataFactory;
use Psr\Log\NullLogger;
use Site;
use SiteLookup;
use Status;
use Title;
use User;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Diff\EntityDiffer;
use Wikibase\DataModel\Services\Diff\EntityPatcher;
use Wikibase\Repo\EditEntity\MediawikiEditEntityFactory;
use Wikibase\Repo\Store\EntityTitleStoreLookup;
use Wikibase\Repo\EditEntity\EditFilterHookRunner;
use Wikibase\Repo\Store\EntityPermissionChecker;
use Wikibase\Repo\UpdateRepo\UpdateRepoOnMoveJob;
use Wikibase\SummaryFormatter;
use Wikibase\Lib\Tests\MockRepository;

/**
 * @covers \Wikibase\Repo\UpdateRepo\UpdateRepoOnMoveJob
 * @covers \Wikibase\Repo\UpdateRepo\UpdateRepoJob
 *
 * @group Wikibase
 * @group WikibaseIntegration
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch < hoo@online.de >
 */
class UpdateRepoOnMoveJobTest extends \MediaWikiTestCase {

	public function testGetSummary() {
		$job = new UpdateRepoOnMoveJob(
			Title::newMainPage(),
			[
				'siteId' => 'SiteID',
				'oldTitle' => 'Test',
				'newTitle' => 'MoarTest',
			]
		);

		$summary = $job->getSummary();

		$this->assertEquals( 'clientsitelink-update', $summary->getMessageKey() );
		$this->assertEquals( 'SiteID', $summary->getLanguageCode() );
		$this->assertEquals(
			[ 'SiteID:Test', 'SiteID:MoarTest' ],
			$summary->getCommentArgs()
		);
	}

	/**
	 * @param string $normalizedPageName
	 *
	 * @return SiteLookup
	 */
	private function getSiteLookup( $normalizedPageName ) {
		$enwiki = $this->getMock( Site::class );
		$enwiki->expects( $this->any() )
			->method( 'getGlobalId' )
			->will( $this->returnValue( 'enwiki' ) );
		$enwiki->expects( $this->any() )
			->method( 'normalizePageName' )
			->will( $this->returnValue( $normalizedPageName ) );

		return new HashSiteStore( [ $enwiki ] );
	}

	/**
	 * @param ItemId $itemId
	 *
	 * @return EntityTitleStoreLookup
	 */
	private function getEntityTitleLookup( ItemId $itemId ) {
		$entityTitleLookup = $this->getMock( EntityTitleStoreLookup::class );
		$entityTitleLookup->expects( $this->any() )
			->method( 'getTitleForId' )
			->with( $itemId )
			->will( $this->returnValue( Title::newFromText( $itemId->getSerialization() ) ) );

		return $entityTitleLookup;
	}

	/**
	 * @return EntityPermissionChecker
	 */
	private function getEntityPermissionChecker() {
		$entityPermissionChecker = $this->getMock( EntityPermissionChecker::class );
		$entityPermissionChecker->expects( $this->any() )
			->method( 'getPermissionStatusForEntity' )
			->will( $this->returnValue( Status::newGood() ) );

		return $entityPermissionChecker;
	}

	/**
	 * @return SummaryFormatter
	 */
	private function getSummaryFormatter() {
		$summaryFormatter = $this->getMockBuilder( SummaryFormatter::class )
				->disableOriginalConstructor()->getMock();

		return $summaryFormatter;
	}

	/**
	 * @return EditFilterHookRunner
	 */
	private function getMockEditFitlerHookRunner() {
		$runner = $this->getMockBuilder( EditFilterHookRunner::class )
			->setMethods( [ 'run' ] )
			->disableOriginalConstructor()
			->getMock();
		$runner->expects( $this->any() )
			->method( 'run' )
			->will( $this->returnValue( Status::newGood() ) );
		return $runner;
	}

	public function runProvider() {
		return [
			[ 'New page name', 'New page name', 'Old page name' ],
			// Client normalization gets applied
			[ 'Even newer page name', 'Even newer page name', 'Old page name' ],
			// The title in the repo item is not the one we expect, so don't change it
			[ 'Old page name', 'New page name', 'Something else' ],
		];
	}

	/**
	 * @dataProvider runProvider
	 * @param string $expected
	 * @param string $normalizedPageName
	 * @param string $oldTitle
	 */
	public function testRun( $expected, $normalizedPageName, $oldTitle ) {
		$user = User::newFromName( 'UpdateRepo' );

		// Needed as UpdateRepoOnMoveJob instantiates a User object
		$user->addToDatabase();

		$item = new Item();
		$item->getSiteLinkList()->addNewSiteLink( 'enwiki', 'Old page name', [ new ItemId( 'Q42' ) ] );

		$mockRepository = new MockRepository();

		$mockRepository->saveEntity( $item, 'UpdateRepoOnDeleteJobTest', $user, EDIT_NEW );

		$params = [
			'siteId' => 'enwiki',
			'entityId' => $item->getId()->getSerialization(),
			'oldTitle' => $oldTitle,
			'newTitle' => 'New page name',
			'user' => $user->getName()
		];

		$job = new UpdateRepoOnMoveJob( Title::newMainPage(), $params );
		$job->initServices(
			$mockRepository,
			$mockRepository,
			$this->getSummaryFormatter(),
			new NullLogger(),
			$this->getSiteLookup( $normalizedPageName ),
			new MediawikiEditEntityFactory(
				$this->getEntityTitleLookup( $item->getId() ),
				$mockRepository,
				$mockRepository,
				$this->getEntityPermissionChecker(),
				new EntityDiffer(),
				new EntityPatcher(),
				$this->getMockEditFitlerHookRunner(),
				new NullStatsdDataFactory(),
				PHP_INT_MAX
			)
		);

		$job->run();

		/** @var Item $item */
		$item = $mockRepository->getEntity( $item->getId() );

		$this->assertSame(
			$expected,
			$item->getSiteLinkList()->getBySiteId( 'enwiki' )->getPageName(),
			'Title linked on enwiki after the job ran'
		);

		$this->assertEquals(
			$item->getSiteLinkList()->getBySiteId( 'enwiki' )->getBadges(),
			[ new ItemId( 'Q42' ) ]
		);
	}

}
