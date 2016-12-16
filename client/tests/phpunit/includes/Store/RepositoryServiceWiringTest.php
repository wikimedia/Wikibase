<?php

namespace Wikibase\Client\Tests\Store;

use DataValues\Deserializers\DataValueDeserializer;
use Wikibase\Client\Store\RepositoryServiceContainer;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Services\EntityId\PrefixMappingEntityIdParser;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\PropertyInfoStore;
use Wikibase\TermIndex;

/**
 * @covers client/includes/Store/RepositoryServiceWiring.php
 *
 * @group Wikibase
 * @group WikibaseClient
 *
 * @license GPL-2.0+
 */
class RepositoryServiceWiringTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @return RepositoryServiceContainer
	 */
	private function getRepositoryServiceContainer() {
		$container = new RepositoryServiceContainer(
			false,
			'',
			new PrefixMappingEntityIdParser( [ '' => '' ], $this->getMock( EntityIdParser::class ) ),
			new DataValueDeserializer( [] ),
			WikibaseClient::getDefaultInstance()
		);

		$container->loadWiringFiles( [ __DIR__ . '/../../../../includes/Store/RepositoryServiceWiring.php' ] );

		return $container;
	}

	public function provideServices() {
		return [
			[ 'EntityRevisionLookup', EntityRevisionLookup::class ],
			[ 'PropertyInfoStore', PropertyInfoStore::class ],
			[ 'TermIndex', TermIndex::class ],
		];
	}

	/**
	 * @dataProvider provideServices
	 */
	public function testGetService( $serviceName, $expectedClass ) {
		$container = $this->getRepositoryServiceContainer();

		$service = $container->getService( $serviceName );

		$this->assertInstanceOf( $expectedClass, $service );
	}

	public function testGetServiceNames() {
		$container = $this->getRepositoryServiceContainer();

		$this->assertEquals(
			[ 'EntityRevisionLookup', 'PropertyInfoStore', 'TermIndex' ],
			$container->getServiceNames()
		);
	}

}
