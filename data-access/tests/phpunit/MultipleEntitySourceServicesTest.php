<?php

namespace Wikibase\DataAccess\Tests;

use Wikibase\DataAccess\EntitySource;
use Wikibase\DataAccess\EntitySourceDefinitions;
use Wikibase\DataAccess\GenericServices;
use Wikibase\DataAccess\MultipleEntitySourceServices;
use Wikibase\DataAccess\SingleEntitySourceServices;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\EntityTypeDefinitions;
use Wikibase\Lib\Interactors\TermSearchInteractor;
use Wikibase\Lib\Interactors\TermSearchInteractorFactory;
use Wikibase\Lib\Interactors\TermSearchResult;
use Wikibase\Lib\Store\EntityInfo;
use Wikibase\Lib\Store\EntityInfoBuilder;
use Wikibase\Lib\Store\EntityRevision;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\TermIndexEntry;

/**
 * @covers \Wikibase\DataAccess\MultipleEntitySourceServices
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class MultipleEntitySourceServicesTest extends \PHPUnit_Framework_TestCase {

	use \PHPUnit4And6Compat;

	public function testGetEntityRevisionLookupReturnsLookupThatReturnsExpectedRevisionData() {
		$itemRevisionLookup = $this->createMock( EntityRevisionLookup::class );
		$itemRevisionLookup->method( 'getEntityRevision' )
			->willReturn( 'item revision data' );

		$propertyRevisionLookup = $this->createMock( EntityRevisionLookup::class );
		$propertyRevisionLookup->method( $this->anything() )
			->willThrowException( new \LogicException( 'This service should not be used' ) );

		$itemServices = $this->createMock( SingleEntitySourceServices::class );
		$itemServices->method( 'getEntityRevisionLookup' )
			->willReturn( $itemRevisionLookup );

		$propertyServices = $this->createMock( SingleEntitySourceServices::class );
		$propertyServices->method( 'getEntityRevisionLookup' )
			->willReturn( $propertyRevisionLookup );

		$services = $this->newMultipleEntitySourceServices( [ 'items' => $itemServices, 'props' => $propertyServices ] );

		$lookup = $services->getEntityRevisionLookup();

		$this->assertEquals( 'item revision data', $lookup->getEntityRevision( new ItemId( 'Q123' ) ) );
	}

	public function testGetEntityInfoBuilderReturnsBuilderHandlingAllSourceEntities() {
		$itemId = 'Q200';
		$propertyId = 'P500';

		$itemInfoBuilder = $this->createMock( EntityInfoBuilder::class );
		$itemInfoBuilder->method( 'collectEntityInfo' )
			->willReturn( new EntityInfo( [ $itemId => 'item info' ] ) );

		$itemServices = $this->createMock( SingleEntitySourceServices::class );
		$itemServices->method( 'getEntityInfoBuilder' )
			->willReturn( $itemInfoBuilder );

		$propertyInfoBuilder = $this->createMock( EntityInfoBuilder::class );
		$propertyInfoBuilder->method( 'collectEntityInfo' )
			->willReturn( new EntityInfo( [ $propertyId => 'property info' ] ) );

		$propertyServices = $this->createMock( SingleEntitySourceServices::class );
		$propertyServices->method( 'getEntityInfoBuilder' )
			->willReturn( $propertyInfoBuilder );

		$services = $this->newMultipleEntitySourceServices( [ 'items' => $itemServices, 'props' => $propertyServices ] );

		$infoBuilder = $services->getEntityInfoBuilder();
		$info = $infoBuilder->collectEntityInfo( [ new PropertyId( $propertyId ), new ItemId( $itemId ) ], [ 'en' ] );

		$this->assertEquals( [ $itemId => 'item info', $propertyId => 'property info' ], $info->asArray() );
	}

	public function testGetTermSearchInteractorFactoryGeneratesInteractorsReturningResultsForConfiguredSources() {
		$itemResult = new TermSearchResult( new Term( 'en', 'test' ), TermIndexEntry::TYPE_LABEL, new ItemId( 'Q123' ) );

		$itemInteractor = $this->createMock( TermSearchInteractor::class );
		$itemInteractor->method( 'searchForEntities' )
			->willReturn( $itemResult );

		$itemInteractorFactory = $this->createMock( TermSearchInteractorFactory::class );
		$itemInteractorFactory->method( 'newInteractor' )
			->willReturn( $itemInteractor );

		$itemServices = $this->createMock( SingleEntitySourceServices::class );
		$itemServices->method( 'getTermSearchInteractorFactory' )
			->willReturn( $itemInteractorFactory );

		$dummyInteractorFactory = $this->createMock( TermSearchInteractorFactory::class );
		$dummyInteractorFactory->method( 'newInteractor' )
			->willReturn( $this->createMock( TermSearchInteractor::class ) );

		$propertyServices = $this->createMock( SingleEntitySourceServices::class );
		$propertyServices->method( 'getTermSearchInteractorFactory' )
			->willReturn( $dummyInteractorFactory );

		$services = $this->newMultipleEntitySourceServices( [ 'items' => $itemServices, 'props' => $propertyServices ] );

		$interactor = $services->getTermSearchInteractorFactory()->newInteractor( 'en' );

		$searchResult = $interactor->searchForEntities( 'test', 'en', 'item', [ TermIndexEntry::TYPE_LABEL ] );

		$this->assertEquals( $itemResult, $searchResult );
	}

	public function testEntityFromKnownSourceUpdated_entityUpdatedPassedToRelevantServiceContainer() {
		$itemRevision = new EntityRevision( new Item( new ItemId( 'Q1' ) ) );

		$itemServices = $this->createMock( SingleEntitySourceServices::class );
		$itemServices->expects( $this->atLeastOnce() )
			->method( 'entityUpdated' )
			->with( $itemRevision );
		$propertyServices = $this->createMock( SingleEntitySourceServices::class );
		$propertyServices->expects( $this->never() )
			->method( 'entityUpdated' );

		$services = $this->newMultipleEntitySourceServices( [ 'items' => $itemServices, 'props' => $propertyServices ] );

		$services->entityUpdated( $itemRevision );
	}

	public function testEntityFromKnownSourceDeleted_entityDeletedPassedToRelevantServiceContainer() {
		$itemId = new ItemId( 'Q1' );

		$itemServices = $this->createMock( SingleEntitySourceServices::class );
		$itemServices->expects( $this->atLeastOnce() )
			->method( 'entityDeleted' )
			->with( $itemId );
		$propertyServices = $this->createMock( SingleEntitySourceServices::class );
		$propertyServices->expects( $this->never() )
			->method( 'entityDeleted' );

		$services = $this->newMultipleEntitySourceServices( [ 'items' => $itemServices, 'props' => $propertyServices ] );

		$services->entityDeleted( $itemId );
	}

	public function testRedirectOfEntityFromKnownSourceDeleted_redirectUpdatedPassedToRelevantServiceContainer() {
		$itemRedirect = new EntityRedirect( new ItemId( 'Q1' ), new ItemId( 'Q300' ) );
		$revisionId = 333;

		$itemServices = $this->createMock( SingleEntitySourceServices::class );
		$itemServices->expects( $this->atLeastOnce() )
			->method( 'redirectUpdated' )
			->with( $itemRedirect, $revisionId );
		$propertyServices = $this->createMock( SingleEntitySourceServices::class );
		$propertyServices->expects( $this->never() )
			->method( 'redirectUpdated' );

		$services = $this->newMultipleEntitySourceServices( [ 'items' => $itemServices, 'props' => $propertyServices ] );

		$services->redirectUpdated( $itemRedirect, $revisionId );
	}

	/**
	 * @param SingleEntitySourceServices[] $perSourceServices
	 * @return MultipleEntitySourceServices
	 */
	private function newMultipleEntitySourceServices( array $perSourceServices ) {
		return new MultipleEntitySourceServices(
			new EntitySourceDefinitions( [
				new EntitySource( 'items', 'itemdb', [ 'item' => [ 'namespaceId' => 100, 'slot' => 'main' ] ] ),
				new EntitySource( 'props', 'propb', [ 'property' => [ 'namespaceId' => 200, 'slot' => 'main' ] ] ),
			] ),
			new GenericServices( new EntityTypeDefinitions( [] ), [], [] ),
			$perSourceServices
		);
	}

}
