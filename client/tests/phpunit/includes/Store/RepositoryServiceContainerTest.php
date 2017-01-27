<?php

namespace Wikibase\Client\Tests\Store;

use DataValues\Deserializers\DataValueDeserializer;
use HashSiteStore;
use Language;
use stdClass;
use Wikibase\Client\Store\RepositoryServiceContainer;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\EntityId\PrefixMappingEntityIdParser;
use Wikibase\EntityRevision;
use Wikibase\Lib\DataTypeDefinitions;
use Wikibase\Lib\EntityTypeDefinitions;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\EntityStoreWatcher;
use Wikibase\SettingsArray;

/**
 * @covers Wikibase\Client\Store\RepositoryServiceContainer
 *
 * @group Wikibase
 * @group WikibaseClient
 *
 * @license GPL-2.0+
 */
class RepositoryServiceContainerTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @return WikibaseClient
	 */
	private function getWikibaseClient() {
		$settings = WikibaseClient::getDefaultInstance()->getSettings()->getArrayCopy();
		$settings['foreignRepositories'] = [
			'foo' => [ 'repoDatabase' => 'foowiki', 'prefixMapping' => [ 'bar' => 'xyz' ] ]
		];

		return new WikibaseClient(
			new SettingsArray( $settings ),
			new DataTypeDefinitions( [] ),
			new EntityTypeDefinitions( [] ),
			new HashSiteStore()
		);
	}

	/**
	 * @return RepositoryServiceContainer
	 */
	private function newRepositoryServiceContainer() {
		/** @var EntityIdParser $idParser */
		$idParser = $this->getMock( EntityIdParser::class );

		return new RepositoryServiceContainer(
			'foowiki',
			'foo',
			new PrefixMappingEntityIdParser( [ '' => 'foo' ], $idParser ),
			new DataValueDeserializer( [] ),
			$this->getWikibaseClient()
		);
	}

	/**
	 * @return RepositoryServiceContainer
	 */
	private function getRepositoryServiceContainer() {
		$container = $this->newRepositoryServiceContainer();

		$container->defineService( 'EntityRevisionLookup', function() {
			return $this->getMock( EntityRevisionLookup::class );
		} );

		return $container;
	}

	public function testGetService() {
		$repositoryServiceContainer = $this->getRepositoryServiceContainer();

		$serviceOne = $repositoryServiceContainer->getService( 'EntityRevisionLookup' );
		$serviceTwo = $repositoryServiceContainer->getService( 'EntityRevisionLookup' );

		$this->assertInstanceOf( EntityRevisionLookup::class, $serviceOne );
		$this->assertInstanceOf( EntityRevisionLookup::class, $serviceTwo );

		$this->assertSame( $serviceOne, $serviceTwo );
	}

	public function testGetServiceNames() {
		$repositoryServiceContainer = $this->getRepositoryServiceContainer();

		$this->assertEquals(
			[ 'EntityRevisionLookup' ],
			$repositoryServiceContainer->getServiceNames()
		);
	}

	public function testGetRepositoryName() {
		$repositoryServiceContainer = $this->getRepositoryServiceContainer();

		$this->assertEquals( 'foo', $repositoryServiceContainer->getRepositoryName() );
	}

	public function testGetDatabaseName() {
		$repositoryServiceContainer = $this->getRepositoryServiceContainer();

		$this->assertEquals( 'foowiki', $repositoryServiceContainer->getDatabaseName() );
	}

	/**
	 * @param $event
	 *
	 * Returns a RepositoryServiceContainer with the following services defined:
	 *  - 'watcherService' - dummy service implementing EntityStoreWatcher interface,
	 *  - 'anotherWatcherService' - dummy service implementing EntityStoreWatcher interface,
	 *  - 'unusedWatcherService' - dummy service implementing EntityStoreWatcher interface,
	 *  - 'nonWatcherService' - dummy service not implementing EntityStoreWatcher interface,
	 * All services but 'unusedWatcherService' are initialized by default.
	 * This methods provides a set up for testing that RepositoryServiceContainer propagates entity change
	 * event to all of its watcher services but not to those that have not been used yet (in which case
	 * it makes no sense to pass the event to them).
	 *
	 * @return RepositoryServiceContainer
	 */
	private function getRepositoryServiceContainerForEventTest( $event ) {
		$watcherService = $this->getMock( EntityStoreWatcher::class );
		$watcherService->expects( $this->atLeastOnce() )->method( $event );

		$unusedWatcherService = $this->getMock( EntityStoreWatcher::class );
		$unusedWatcherService->expects( $this->never() )->method( $event );

		$nonWatcherService = $this->getMock( stdClass::class );
		$nonWatcherService->expects( $this->never() )->method( $event );

		$container = $this->newRepositoryServiceContainer();
		$container->defineService( 'watcherService', function () use ( $watcherService ) {
			return $watcherService;
		} );
		$container->defineService( 'anotherWatcherService', function () use ( $watcherService ) {
			return $watcherService;
		} );
		$container->defineService( 'unusedWatcherService', function () use ( $unusedWatcherService ) {
			return $unusedWatcherService;
		} );
		$container->defineService( 'nonWatcherService', function () use ( $nonWatcherService ) {
			return $nonWatcherService;
		} );

		// Instantiate services relevant for the check
		$container->getService( 'watcherService' );
		$container->getService( 'anotherWatcherService' );
		$container->getService( 'nonWatcherService' );

		return $container;
	}

	public function testEntityUpdatedDelegatesEventToAllWatchersThatHaveAlreadyBeenUsed() {
		$container = $this->getRepositoryServiceContainerForEventTest( 'entityUpdated' );

		$container->entityUpdated( new EntityRevision( new Item( new ItemId( 'foo:Q123' ) ) ) );
	}

	public function testEntityDeletedDelegatesEventToAllWatchersThatHaveAlreadyBeenUsed() {
		$container = $this->getRepositoryServiceContainerForEventTest( 'entityDeleted' );

		$container->entityDeleted( new ItemId( 'foo:Q123' ) );
	}

	public function testRedirectUpdatedDelegatesEventToAllWatchersThatHaveAlreadyBeenUsed() {
		$container = $this->getRepositoryServiceContainerForEventTest( 'redirectUpdated' );

		$container->redirectUpdated( new EntityRedirect( new ItemId( 'foo:Q123' ), new ItemId( 'foo:Q321' ) ), 100 );
	}

}
