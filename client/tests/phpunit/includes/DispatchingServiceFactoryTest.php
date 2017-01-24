<?php

namespace Wikibase\Client\Tests;

use DataValues\Deserializers\DataValueDeserializer;
use Wikibase\Client\DispatchingServiceFactory;
use Wikibase\Client\Store\RepositoryServiceContainer;
use Wikibase\Client\Store\RepositoryServiceContainerFactory;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\EntityRevision;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\Sql\PrefetchingWikiPageEntityMetaDataAccessor;

/**
 * @covers Wikibase\Client\DispatchingServiceFactory
 *
 * @group Wikibase
 * @group WikibaseClient
 *
 * @license GPL-2.0+
 */
class DispatchingServiceFactoryTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @return RepositoryServiceContainerFactory
	 */
	private function getRepositoryServiceContainerFactory() {
		$entityRevisionLookup = $this->getMock( EntityRevisionLookup::class );

		$container = $this->getMockBuilder( RepositoryServiceContainer::class )
			->disableOriginalConstructor()
			->getMock();
		$container->expects( $this->any() )
			->method( 'getService' )
			->will(
				$this->returnCallback( function ( $service ) use ( $entityRevisionLookup ) {
					return $service === 'EntityRevisionLookup' ? $entityRevisionLookup : null;
				} )
			);

		$containerFactory = $this->getMockBuilder( RepositoryServiceContainerFactory::class )
			->disableOriginalConstructor()
			->getMock();
		$containerFactory->expects( $this->any() )
			->method( 'getContainer' )
			->will( $this->returnValue( $container ) );

		return $containerFactory;
	}

	/**
	 * @return DispatchingServiceFactory
	 */
	private function getDispatchingServiceFactory( RepositoryServiceContainerFactory $containerFactory ) {
		$client = WikibaseClient::getDefaultInstance();
		$settings = $client->getSettings();
		$settings->setSetting( 'foreignRepositories', [ 'foo' => [ 'repoDatabase' => 'foowiki' ] ] );

		$factory = new DispatchingServiceFactory( $containerFactory, [ '', 'foo' ] );

		$factory->defineService( 'EntityRevisionLookup', function() {
			return $this->getMock( EntityRevisionLookup::class );
		} );

		return $factory;
	}

	public function testGetServiceNames() {
		$factory = $this->getDispatchingServiceFactory( $this->getRepositoryServiceContainerFactory() );

		$this->assertEquals(
			[ 'EntityRevisionLookup' ],
			$factory->getServiceNames()
		);
	}

	public function testGetServiceMap() {
		$factory = $this->getDispatchingServiceFactory( $this->getRepositoryServiceContainerFactory() );

		$serviceMap = $factory->getServiceMap( 'EntityRevisionLookup' );

		$this->assertEquals(
			[ '', 'foo' ],
			array_keys( $serviceMap )
		);
		$this->assertContainsOnlyInstancesOf( EntityRevisionLookup::class, $serviceMap );
	}

	public function testGetService() {
		$factory = $this->getDispatchingServiceFactory( $this->getRepositoryServiceContainerFactory() );

		$serviceOne = $factory->getService( 'EntityRevisionLookup' );
		$serviceTwo = $factory->getService( 'EntityRevisionLookup' );

		$this->assertInstanceOf( EntityRevisionLookup::class, $serviceOne );
		$this->assertInstanceOf( EntityRevisionLookup::class, $serviceTwo );
		$this->assertSame( $serviceOne, $serviceTwo );
	}

	/**
	 * @param string|false $dbName
	 * @param string $repositoryName
	 *
	 * @return RepositoryServiceContainer
	 */
	private function getRepositoryServiceContainer( $dbName, $repositoryName ) {
		return new RepositoryServiceContainer(
			$dbName,
			$repositoryName,
			new BasicEntityIdParser(),
			new DataValueDeserializer( [] ),
			WikibaseClient::getDefaultInstance()
		);
	}

	/**
	 * @param string $event
	 *
	 * @return RepositoryServiceContainerFactory
	 */
	private function getRepositoryServiceContainerFactoryForEventTest( $event ) {
		$localMetaDataAccessor = $this->getMockBuilder( PrefetchingWikiPageEntityMetaDataAccessor::class )
			->disableOriginalConstructor()
			->getMock();
		$localMetaDataAccessor->expects( $this->never() )->method( $event );

		$fooMetaDataAccessor = $this->getMockBuilder( PrefetchingWikiPageEntityMetaDataAccessor::class )
			->disableOriginalConstructor()
			->getMock();
		$fooMetaDataAccessor->expects( $this->atLeastOnce() )->method( $event );

		$localServiceContainer = $this->getRepositoryServiceContainer( false, '' );
		$localServiceContainer->defineService( 'PrefetchingEntityMetaDataAccessor', function () use ( $localMetaDataAccessor ) {
			return $localMetaDataAccessor;
		} );

		$fooServiceContainer = $this->getRepositoryServiceContainer( 'foo', 'foowiki' );
		$fooServiceContainer->defineService( 'PrefetchingEntityMetaDataAccessor', function () use ( $fooMetaDataAccessor ) {
			return $fooMetaDataAccessor;
		} );

		$containerFactory = $this->getMockBuilder( RepositoryServiceContainerFactory::class )
			->disableOriginalConstructor()
			->getMock();

		$containerFactory->expects( $this->any() )
			->method( 'getContainer' )
			->will(
				$this->returnCallback( function ( $container ) use ( $localServiceContainer, $fooServiceContainer ) {
					return $container === '' ? $localServiceContainer : $fooServiceContainer;
				} )
			);

		return $containerFactory;
	}

	public function testEntityUpdatedDelegatesEventToRepositorySpecificWatcher() {
		$factory = $this->getDispatchingServiceFactory(
			$this->getRepositoryServiceContainerFactoryForEventTest( 'entityUpdated' )
		);

		$factory->entityUpdated( new EntityRevision( new Item( new ItemId( 'foo:Q123' ) ) ) );
	}

	public function testEntityDeletedDelegatesEventToRepositorySpecificWatcher() {
		$factory = $this->getDispatchingServiceFactory(
			$this->getRepositoryServiceContainerFactoryForEventTest( 'entityDeleted' )
		);

		$factory->entityDeleted( new ItemId( 'foo:Q123' ) );
	}

	public function testRedirectUpdatedDelegatesEventToRepositorySpecificWatcher() {
		$factory = $this->getDispatchingServiceFactory(
			$this->getRepositoryServiceContainerFactoryForEventTest( 'redirectUpdated' )
		);

		$factory->redirectUpdated( new EntityRedirect( new ItemId( 'foo:Q123' ), new ItemId( 'foo:Q321' ) ), 100 );
	}

}
