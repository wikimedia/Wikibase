<?php

namespace Wikibase\Client;

use MediaWiki\Services\ServiceContainer;
use Wikibase\Client\Store\RepositoryServiceContainerFactory;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\DataModel\Services\Entity\EntityPrefetcher;
use Wikibase\DataModel\Services\Term\TermBuffer;
use Wikibase\EntityRevision;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\EntityStoreWatcher;
use Wikibase\Lib\Store\PropertyInfoLookup;
use Wikibase\Lib\Store\Sql\PrefetchingWikiPageEntityMetaDataAccessor;

/**
 * A factory/locator of services dispatching the action to services configured for the
 * particular input, based on the repository the particular input entity belongs to.
 * Dispatching services provide a way of using entities from multiple repositories.
 *
 * Services are defined by loading wiring array(s), or by using defineService method.
 *
 * @license GPL-2.0+
 */
class DispatchingServiceFactory extends ServiceContainer implements EntityDataRetrievalServiceFactory, EntityStoreWatcher {

	/**
	 * @var string[]
	 */
	private $repositoryNames;

	/**
	 * @var RepositoryServiceContainerFactory
	 */
	private $repositoryServiceContainerFactory;

	/**
	 * @param RepositoryServiceContainerFactory $repositoryServiceContainerFactory
	 * @param string[] $repositoryNames
	 */
	public function __construct(
		RepositoryServiceContainerFactory $repositoryServiceContainerFactory,
		array $repositoryNames
	) {
		parent::__construct();

		$this->repositoryServiceContainerFactory = $repositoryServiceContainerFactory;
		$this->repositoryNames = $repositoryNames;
	}

	/**
	 * @param string $service
	 * @return array An associative array mapping repository names to service instances configured for the repository
	 */
	public function getServiceMap( $service ) {
		$serviceMap = [];
		foreach ( $this->repositoryNames as $repositoryName ) {
			$serviceMap[$repositoryName] = $this->repositoryServiceContainerFactory
				->getContainer( $repositoryName )->getService( $service );
		}
		return $serviceMap;
	}

	/**
	 * @param string $repositoryName
	 * @return PrefetchingWikiPageEntityMetaDataAccessor|null
	 */
	private function getPrefetchingMetaDataAccessorForRepository( $repositoryName ) {
		/** @var PrefetchingWikiPageEntityMetaDataAccessor[] $entityPrefetchers */
		$metaDataAccessors = $this->getServiceMap( 'PrefetchingEntityMetaDataAccessor' );

		return isset( $metaDataAccessors[$repositoryName] ) ? $metaDataAccessors[$repositoryName] : null;
	}

	/**
	 * @see EntityStoreWatcher::entityUpdated
	 *
	 * @param EntityRevision $entityRevision
	 */
	public function entityUpdated( EntityRevision $entityRevision ) {
		$metaDataAccessor = $this->getPrefetchingMetaDataAccessorForRepository(
			$entityRevision->getEntity()->getId()->getRepositoryName()
		);

		if ( $metaDataAccessor !== null ) {
			$metaDataAccessor->entityUpdated( $entityRevision );
		}
	}

	/**
	 * @see EntityStoreWatcher::entityDeleted
	 *
	 * @param EntityId $entityId
	 */
	public function entityDeleted( EntityId $entityId ) {
		$metaDataAccessor = $this->getPrefetchingMetaDataAccessorForRepository( $entityId->getRepositoryName() );

		if ( $metaDataAccessor !== null ) {
			$metaDataAccessor->entityDeleted( $entityId );
		}
	}

	/**
	 * @see EntityStoreWatcher::redirectUpdated
	 *
	 * @param EntityRedirect $entityRedirect
	 * @param int $revisionId
	 */
	public function redirectUpdated( EntityRedirect $entityRedirect, $revisionId ) {
		$metaDataAccessor = $this->getPrefetchingMetaDataAccessorForRepository(
			$entityRedirect->getEntityId()->getRepositoryName()
		);

		if ( $metaDataAccessor !== null ) {
			$metaDataAccessor->redirectUpdated( $entityRedirect, $revisionId );
		}
	}

	/**
	 * @return EntityRevisionLookup
	 */
	public function getEntityRevisionLookup() {
		return $this->getService( 'EntityRevisionLookup' );
	}

	/**
	 * @return PropertyInfoLookup
	 */
	public function getPropertyInfoLookup() {
		return $this->getService( 'PropertyInfoLookup' );
	}

	/**
	 * @return TermBuffer
	 */
	public function getTermBuffer() {
		return $this->getService( 'TermBuffer' );
	}

}
