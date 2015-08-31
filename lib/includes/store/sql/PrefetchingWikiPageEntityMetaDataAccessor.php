<?php

namespace Wikibase\Lib\Store\Sql;

use MapCacheLRU;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\DataModel\Services\Entity\EntityPrefetcher;
use Wikibase\EntityRevision;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\EntityStoreWatcher;

/**
 * A WikiPageEntityMetaDataAccessor decorator that implements prefetching and caching.
 *
 * @since 0.5
 *
 * @license GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 */
class PrefetchingWikiPageEntityMetaDataAccessor implements EntityPrefetcher, EntityStoreWatcher,
		WikiPageEntityMetaDataAccessor {

	/**
	 * @var WikiPageEntityMetaDataAccessor
	 */
	private $lookup;

	/**
	 * @var int
	 */
	private $maxCacheKeys;

	/**
	 * @var MapCacheLRU
	 */
	private $cache;

	/**
	 * @var EntityId[]
	 */
	private $toFetch = array();

	/**
	 * @param WikiPageEntityMetaDataAccessor $lookup
	 * @param int $maxCacheKeys Maximum number of entries to cache (defaults to 1000)
	 */
	public function __construct( WikiPageEntityMetaDataAccessor $lookup, $maxCacheKeys = 1000 ) {
		$this->lookup = $lookup;
		$this->maxCacheKeys = $maxCacheKeys;
		$this->cache = new MapCacheLRU( $maxCacheKeys );
	}

	/**
	 * Marks the given entity ids for prefetching.
	 *
	 * @param EntityId[] $entityIds
	 */
	public function prefetch( array $entityIds ) {
		$entityIdCount = count( $entityIds );

		if ( $entityIdCount > $this->maxCacheKeys ) {
			// Ouch... fetching everything wouldn't fit into the cache, thus
			// other functions might not find what they're looking for.
			// Create a new, large enough MapCacheLRU to mitigate this.
			$this->cache = new MapCacheLRU( $entityIdCount + 1 );
			$this->maxCacheKeys = $entityIdCount + 1;
			wfDebugLog(
				'PrefetchingWikiPageEntityMetaDataAccessor',
				"Needed to create a new MapCacheLRU instance for $entityIdCount entities."
			);
		}
		if ( ( $entityIdCount + count( $this->toFetch ) ) > $this->maxCacheKeys ) {
			// Fetching everything would exceed the capacity of the cache,
			// thus discard all older entity ids as we can safely ignore these.
			$this->toFetch = array();
		}

		foreach ( $entityIds as $entityId ) {
			$idSerialization = $entityId->getSerialization();
			if ( $this->cache->has( $idSerialization ) ) {
				// Make sure the entities we already know about are not going
				// to be purged, by requesting them.
				$this->cache->get( $idSerialization );
			} else {
				$this->toFetch[$idSerialization] = $entityId;
			}
		}
	}

	/**
	 * @see EntityPrefetcher::purge
	 *
	 * @param EntityId $entityId
	 */
	public function purge( EntityId $entityId ) {
		$this->cache->clear( $entityId->getSerialization() );
	}

	/**
	 * @see EntityPrefetcher::purgeAll
	 */
	public function purgeAll() {
		$this->cache->clear();
	}

	/**
	 * @see EntityStoreWatcher::entityDeleted
	 *
	 * @param EntityId $entityId
	 */
	public function entityDeleted( EntityId $entityId ) {
		$this->purge( $entityId );
	}

	/**
	 * @see EntityStoreWatcher::entityDeleted
	 *
	 * @param EntityRevision $entityRevision
	 */
	public function entityUpdated( EntityRevision $entityRevision ) {
		$this->purge( $entityRevision->getEntity()->getId() );
	}

	/**
	 * @see EntityStoreWatcher::redirectUpdated
	 *
	 * @param EntityRedirect $entityRedirect
	 * @param int $revisionId
	 */
	public function redirectUpdated( EntityRedirect $entityRedirect, $revisionId ) {
		$this->purge( $entityRedirect->getEntityId() );
	}

	/**
	 * @see WikiPageEntityMetaDataAccessor::loadRevisionInformation
	 *
	 * @param EntityId[] $entityIds
	 * @param string $mode (EntityRevisionLookup::LATEST_FROM_SLAVE or EntityRevisionLookup::LATEST_FROM_MASTER)
	 *
	 * @return array of entity id serialization => object or false if no such entity exists.
	 */
	public function loadRevisionInformation( array $entityIds, $mode ) {
		if ( $mode === EntityRevisionLookup::LATEST_FROM_MASTER ) {
			// Don't attempt to use the cache in case we are asked to fetch
			// from master. Also don't put load on the master by just fetching
			// everything in $this->toFetch.
			$data = $this->lookup->loadRevisionInformation( $entityIds, $mode );
			// Cache the data, just in case it will be needed again.
			$this->store( $data );
			// Make sure we wont fetch these next time.
			foreach ( $entityIds as $entityId ) {
				$key = $entityId->getSerialization();
				unset( $this->toFetch[$key] );
			}

			return $data;
		}

		$this->prefetch( $entityIds );
		$this->doFetch();

		$data = array();
		foreach ( $entityIds as $entityId ) {
			$data[$entityId->getSerialization()] = $this->cache->get( $entityId->getSerialization() );
		}

		return $data;
	}

	/**
	 * @see WikiPageEntityMetaDataAccessor::loadRevisionInformationByRevisionId
	 *
	 * @param EntityId $entityId
	 * @param int $revisionId
	 *
	 * @return object|bool false if no such entity exists
	 */
	public function loadRevisionInformationByRevisionId( EntityId $entityId, $revisionId ) {
		// Caching this would have little or no benefit, but would be rather complex.
		return $this->lookup->loadRevisionInformationByRevisionId( $entityId, $revisionId );
	}

	private function doFetch() {
		if ( empty( $this->toFetch ) ) {
			return;
		}

		$data = $this->lookup->loadRevisionInformation(
			$this->toFetch,
			EntityRevisionLookup::LATEST_FROM_SLAVE
		);

		// Store the data, including cache misses
		$this->store( $data );

		// Prune $this->toFetch
		$this->toFetch = array();
	}

	private function store( array $data ) {
		foreach ( $data as $key => $value ) {
			$this->cache->set( $key, $value );
		}
	}

}
