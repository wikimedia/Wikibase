<?php

namespace Wikibase\DataAccess\Tests;

use DataValues\Deserializers\DataValueDeserializer;
use LogicException;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataAccess\GenericServices;
use Wikibase\DataAccess\RepositoryServiceContainer;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Services\Entity\EntityPrefetcher;
use Wikibase\DataModel\Services\EntityId\PrefixMappingEntityIdParser;
use Wikibase\DataModel\Services\Term\TermBuffer;
use Wikibase\Lib\EntityIdComposer;
use Wikibase\Lib\Interactors\TermSearchInteractorFactory;
use Wikibase\Lib\Store\EntityInfoBuilderFactory;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\PrefetchingTermLookup;
use Wikibase\Lib\Store\PropertyInfoLookup;
use Wikibase\Lib\Store\Sql\WikiPageEntityMetaDataAccessor;
use Wikibase\TermIndex;

/**
 * @group Wikibase
 *
 * @license GPL-2.0+
 */
class PerRepositoryServiceWiringTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @return RepositoryServiceContainer
	 */
	private function getRepositoryServiceContainer() {
		$client = WikibaseClient::getDefaultInstance();
		$container = new RepositoryServiceContainer(
			false,
			'',
			new PrefixMappingEntityIdParser( [ '' => '' ], $this->getMock( EntityIdParser::class ) ),
			new EntityIdComposer( [] ),
			new DataValueDeserializer( [] ),
			new GenericServices( $client->getEntityNamespaceLookup() ),
			$client
		);

		$container->loadWiringFiles( [ __DIR__ . '/../../src/PerRepositoryServiceWiring.php' ] );

		return $container;
	}

	public function provideServices() {
		return [
			[ 'EntityInfoBuilderFactory', EntityInfoBuilderFactory::class ],
			[ 'EntityPrefetcher', EntityPrefetcher::class ],
			[ 'EntityRevisionLookup', EntityRevisionLookup::class ],
			[ 'PrefetchingTermLookup', PrefetchingTermLookup::class ],
			[ 'PropertyInfoLookup', PropertyInfoLookup::class ],
			[ 'TermBuffer', TermBuffer::class ],
			[ 'TermIndex', TermIndex::class ],
			[ 'TermSearchInteractorFactory', TermSearchInteractorFactory::class ],
			[ 'WikiPageEntityMetaDataAccessor', WikiPageEntityMetaDataAccessor::class ],
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
			[
				'EntityInfoBuilderFactory',
				'EntityPrefetcher',
				'EntityRevisionLookup',
				'PrefetchingTermLookup',
				'PropertyInfoLookup',
				'TermBuffer',
				'TermIndex',
				'TermSearchInteractorFactory',
				'WikiPageEntityMetaDataAccessor'
			],
			$container->getServiceNames()
		);
	}

	public function testGetEntityPrefetcherThrowsAnExceptionIfNoPrefetcherService() {
		$container = $this->getRepositoryServiceContainer();

		// Make 'WikiPageEntityMetaDataAccessor' service not an implementation
		// of EntityPrefetcher interface
		$container->redefineService( 'WikiPageEntityMetaDataAccessor', function () {
			return $this->getMock( WikiPageEntityMetaDataAccessor::class );
		} );

		$this->setExpectedException( LogicException::class );

		$container->getService( 'EntityPrefetcher' );
	}

}
