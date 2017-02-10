<?php

namespace Wikibase\Client\Tests;

use Prophecy\Argument;
use Wikibase\Client\DispatchingServiceFactory;
use Wikibase\Client\Store\RepositoryServiceContainer;
use Wikibase\Client\Store\RepositoryServiceContainerFactory;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\EntityRevision;

/**
 * @covers Wikibase\Client\DispatchingServiceFactory
 *
 * @group Wikibase
 * @group WikibaseClient
 *
 * @license GPL-2.0+
 */
class DispatchingServiceFactoryTest extends \PHPUnit_Framework_TestCase {

	public function testGetServiceNames_ReturnsNameOfDefinedService() {
		$factory = new DispatchingServiceFactory(
			$this->dummy( RepositoryServiceContainerFactory::class ),
			[],
			[]
		);

		$factory->defineService(
			'SomeService',
			function () {
				return $this->someService( 'does not matter' );
			}
		);

		$this->assertContains( 'SomeService', $factory->getServiceNames() );
	}

	public function testGetServiceMap_ReturnsArrayMappingNameOfRepositoryToServiceForThatRepository(
	) {
		$someServiceName = 'some-service';
		$localService = $this->someService( 'local' );
		$fooService = $this->someService( 'foo' );

		$localContainer = $this->prophesize( RepositoryServiceContainer::class );
		$localContainer->getService( $someServiceName )->willReturn( $localService );

		$fooContainer = $this->prophesize( RepositoryServiceContainer::class );
		$fooContainer->getService( $someServiceName )->willReturn( $fooService );

		$rscFactory = $this->prophesize( RepositoryServiceContainerFactory::class );
		$rscFactory->newContainer( '' )->willReturn( $localContainer );
		$rscFactory->newContainer( 'foo' )->willReturn( $fooContainer );
		$dispatchingFactory = new DispatchingServiceFactory(
			$rscFactory->reveal(),
			[ '', 'foo' ],
			[]
		);

		$serviceMap = $dispatchingFactory->getServiceMap( $someServiceName );

		$expectedServiceMap = [
			'' => $localService,
			'foo' => $fooService,
		];
		$this->assertEquals( $expectedServiceMap, $serviceMap );
	}

	public function testGetService_AlwaysReturnsTheSameService() {
		$factory = new DispatchingServiceFactory(
			$this->dummy( RepositoryServiceContainerFactory::class ),
			[],
			[]
		);

		$someService = $this->someService( 'some service instance' );
		$factory->defineService(
			'some-service',
			function () use ( $someService ) {
				return $someService;
			}
		);

		$serviceOne = $factory->getService( 'some-service' );
		$serviceTwo = $factory->getService( 'some-service' );

		$this->assertSame( $someService, $serviceOne );
		$this->assertSame( $someService, $serviceTwo );
	}

	public function testEntityUpdatedDelegatesEventToContainerOfRelevantRepository() {
		$localContainer = $this->prophesize( RepositoryServiceContainer::class );
		$fooContainer = $this->prophesize( RepositoryServiceContainer::class );
		$factory = new DispatchingServiceFactory(
			$this->createRepositoryServiceContainerFactory(
				[ '' => $localContainer->reveal(), 'foo' => $fooContainer->reveal() ]
			),
			[ '', 'foo' ],
			[ Item::ENTITY_TYPE => 'foo' ]
		);

		$factory->entityUpdated( new EntityRevision( new Item( new ItemId( 'foo:Q123' ) ) ) );

		$fooContainer->entityUpdated(
			new EntityRevision( new Item( new ItemId( 'foo:Q123' ) ) )
		)->shouldHaveBeenCalled();
		$localContainer->entityUpdated( Argument::any() )->shouldNotHaveBeenCalled();
	}

	public function testEntityDeletedDelegatesEventToContainerOfRelevantRepository() {
		$localContainer = $this->prophesize( RepositoryServiceContainer::class );
		$fooContainer = $this->prophesize( RepositoryServiceContainer::class );
		$factory = new DispatchingServiceFactory(
			$this->createRepositoryServiceContainerFactory(
				[ '' => $localContainer->reveal(), 'foo' => $fooContainer->reveal() ]
			),
			[ '', 'foo' ],
			[ Item::ENTITY_TYPE => 'foo' ]
		);

		$factory->entityDeleted( new ItemId( 'foo:Q123' ) );

		$fooContainer->entityDeleted( new ItemId( 'foo:Q123' ) )->shouldHaveBeenCalled();
		$localContainer->entityDeleted( Argument::any() )->shouldNotHaveBeenCalled();
	}

	public function testRedirectUpdatedDelegatesEventToContainerOfRelevantRepository() {
		$localContainer = $this->prophesize( RepositoryServiceContainer::class );
		$fooContainer = $this->prophesize( RepositoryServiceContainer::class );
		$factory = new DispatchingServiceFactory(
			$this->createRepositoryServiceContainerFactory(
				[ '' => $localContainer->reveal(), 'foo' => $fooContainer->reveal() ]
			),
			[ '', 'foo' ],
			[ Item::ENTITY_TYPE => 'foo' ]
		);

		$factory->redirectUpdated(
			new EntityRedirect( new ItemId( 'foo:Q1' ), new ItemId( 'foo:Q2' ) ),
			100
		);

		$fooContainer->redirectUpdated(
			new EntityRedirect( new ItemId( 'foo:Q1' ), new ItemId( 'foo:Q2' ) ),
			100
		)
			->shouldHaveBeenCalled();
		$localContainer->redirectUpdated(
			Argument::any(),
			Argument::any()
		)->shouldNotHaveBeenCalled();
	}

	public function testGetEntityTypeToRepoMapping() {
		$factory = new DispatchingServiceFactory(
			$this->dummy( RepositoryServiceContainerFactory::class ),
			[ '', 'foo' ],
			[ Item::ENTITY_TYPE => '', Property::ENTITY_TYPE => 'foo' ]
		);

		$this->assertEquals(
			[
				Item::ENTITY_TYPE => '',
				Property::ENTITY_TYPE => 'foo',
			],
			$factory->getEntityTypeToRepoMapping()
		);
	}

	/**
	 * @param array $containers Assoc array [ '<repo name>' => RepositoryServiceContainer, ... ]
	 *
	 * @return RepositoryServiceContainerFactory
	 */
	private function createRepositoryServiceContainerFactory( array $containers ) {
		$containerFactory = $this->getMockBuilder( RepositoryServiceContainerFactory::class )
			->disableOriginalConstructor()
			->getMock();
		$containerFactory->method( 'newContainer' )
			->will(
				$this->returnCallback(
					function ( $container ) use ( $containers ) {
						return $containers[ $container ];
					}
				)
			);

		return $containerFactory;
	}

	/**
	 * Creates test dummy
	 *
	 * @param string $class
	 *
	 * @return object
	 */
	private function dummy( $class ) {
		return $this->prophesize( $class )->reveal();
	}

	/**
	 * Creates dummy object (in context of this text to represent some service)
	 *
	 * @param $description
	 *
	 * @return object
	 */
	private function someService( $description ) {
		$result = new \stdClass();
		$result->description = $description;

		return $result;
	}

}
