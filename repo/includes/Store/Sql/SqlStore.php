<?php

namespace Wikibase;

use HashBagOStuff;
use ObjectCache;
use Revision;
use Wikibase\DataAccess\WikibaseServices;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Services\Entity\EntityPrefetcher;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\EntityRedirectLookup;
use Wikibase\DataModel\Services\Lookup\RedirectResolvingEntityLookup;
use Wikibase\Lib\Changes\EntityChangeFactory;
use Wikibase\Lib\EntityIdComposer;
use Wikibase\Lib\Store\CachingEntityRevisionLookup;
use Wikibase\Lib\Store\CacheAwarePropertyInfoStore;
use Wikibase\Lib\Store\CachingPropertyInfoLookup;
use Wikibase\Lib\Store\Sql\EntityChangeLookup;
use Wikibase\Lib\Store\EntityInfoBuilderFactory;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Lib\Store\EntityStoreWatcher;
use Wikibase\Lib\Store\PropertyInfoStore;
use Wikibase\Lib\Store\Sql\PropertyInfoTable;
use Wikibase\Lib\Store\Sql\TermSqlIndex;
use Wikibase\Repo\Store\EntityTitleStoreLookup;
use Wikibase\Lib\Store\LabelConflictFinder;
use Wikibase\Lib\Store\PropertyInfoLookup;
use Wikibase\Lib\Store\RevisionBasedEntityLookup;
use Wikibase\Lib\Store\SiteLinkStore;
use Wikibase\Lib\Store\Sql\SiteLinkTable;
use Wikibase\Lib\Store\Sql\PrefetchingWikiPageEntityMetaDataAccessor;
use Wikibase\Repo\Store\DispatchingEntityStoreWatcher;
use Wikibase\Repo\Store\EntitiesWithoutTermFinder;
use Wikibase\Repo\Store\ItemsWithoutSitelinksFinder;
use Wikibase\Repo\Store\SiteLinkConflictLookup;
use Wikibase\Repo\Store\Sql\SqlEntitiesWithoutTermFinder;
use Wikibase\Repo\Store\Sql\SqlChangeStore;
use Wikibase\Repo\Store\Sql\SqlItemsWithoutSitelinksFinder;
use Wikibase\Repo\Store\Sql\SqlSiteLinkConflictLookup;
use Wikibase\Repo\Store\Sql\WikiPageEntityRedirectLookup;
use Wikibase\Repo\Store\WikiPageEntityStore;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Store\EntityIdLookup;
use Wikimedia\Rdbms\DBQueryError;
use WikiPage;

/**
 * Implementation of the store interface using an SQL backend via MediaWiki's
 * storage abstraction layer.
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class SqlStore implements Store {

	/**
	 * @var EntityChangeFactory
	 */
	private $entityChangeFactory;

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 * @var EntityIdComposer
	 */
	private $entityIdComposer;

	/**
	 * @var EntityRevisionLookup|null
	 */
	private $entityRevisionLookup = null;

	/**
	 * @var EntityRevisionLookup|null
	 */
	private $rawEntityRevisionLookup = null;

	/**
	 * @var CachingEntityRevisionLookup|null
	 */
	private $retrieveOnlyEntityRevisionLookup = null;

	/**
	 * @var EntityStore|null
	 */
	private $entityStore = null;

	/**
	 * @var DispatchingEntityStoreWatcher|null
	 */
	private $entityStoreWatcher = null;

	/**
	 * @var PropertyInfoLookup|null
	 */
	private $propertyInfoLookup = null;

	/**
	 * @var PropertyInfoStore|null
	 */
	private $propertyInfoStore = null;

	/**
	 * @var PropertyInfoTable|null
	 */
	private $propertyInfoTable = null;

	/**
	 * @var string|bool false for local, or a database id that wfGetLB understands.
	 */
	private $changesDatabase;

	/**
	 * @var TermIndex|null
	 */
	private $termIndex = null;

	/**
	 * @var PrefetchingWikiPageEntityMetaDataAccessor|null
	 */
	private $entityPrefetcher = null;

	/**
	 * @var EntityIdLookup
	 */
	private $entityIdLookup;

	/**
	 * @var EntityTitleStoreLookup
	 */
	private $entityTitleLookup;

	/**
	 * @var EntityNamespaceLookup
	 */
	private $entityNamespaceLookup;

	/**
	 * @var WikibaseServices
	 */
	private $wikibaseServices;

	/**
	 * @var string
	 */
	private $cacheKeyPrefix;

	/**
	 * @var int
	 */
	private $cacheType;

	/**
	 * @var int
	 */
	private $cacheDuration;

	/**
	 * @var int[]
	 */
	private $idBlacklist;

	/**
	 * @var bool
	 */
	private $writeFullEntityIdColumn;

	/**
	 * @var bool
	 */
	private $readFullEntityIdColumn;

	/**
	 * @param EntityChangeFactory $entityChangeFactory
	 * @param EntityIdParser $entityIdParser
	 * @param EntityIdComposer $entityIdComposer
	 * @param EntityIdLookup $entityIdLookup
	 * @param EntityTitleStoreLookup $entityTitleLookup
	 * @param EntityNamespaceLookup $entityNamespaceLookup
	 * @param WikibaseServices $wikibaseServices Service container providing data access services
	 */
	public function __construct(
		EntityChangeFactory $entityChangeFactory,
		EntityIdParser $entityIdParser,
		EntityIdComposer $entityIdComposer,
		EntityIdLookup $entityIdLookup,
		EntityTitleStoreLookup $entityTitleLookup,
		EntityNamespaceLookup $entityNamespaceLookup,
		WikibaseServices $wikibaseServices
	) {
		$this->entityChangeFactory = $entityChangeFactory;
		$this->entityIdParser = $entityIdParser;
		$this->entityIdComposer = $entityIdComposer;
		$this->entityIdLookup = $entityIdLookup;
		$this->entityTitleLookup = $entityTitleLookup;
		$this->entityNamespaceLookup = $entityNamespaceLookup;
		$this->wikibaseServices = $wikibaseServices;

		//TODO: inject settings
		$settings = WikibaseRepo::getDefaultInstance()->getSettings();
		$this->changesDatabase = $settings->getSetting( 'changesDatabase' );
		$this->cacheKeyPrefix = $settings->getSetting( 'sharedCacheKeyPrefix' );
		$this->cacheType = $settings->getSetting( 'sharedCacheType' );
		$this->cacheDuration = $settings->getSetting( 'sharedCacheDuration' );
		$this->idBlacklist = $settings->getSetting( 'idBlacklist' );
		$this->writeFullEntityIdColumn = $settings->getSetting( 'writeFullEntityIdColumn' );
		$this->readFullEntityIdColumn = $settings->getSetting( 'readFullEntityIdColumn' );
	}

	/**
	 * @see Store::getTermIndex
	 *
	 * @return TermIndex
	 */
	public function getTermIndex() {
		if ( !$this->termIndex ) {
			$this->termIndex = $this->newTermIndex();
		}

		return $this->termIndex;
	}

	/**
	 * @see Store::getLabelConflictFinder
	 *
	 * @return LabelConflictFinder
	 */
	public function getLabelConflictFinder() {
		return $this->getTermIndex();
	}

	/**
	 * @return TermIndex
	 */
	private function newTermIndex() {
		//TODO: Get $stringNormalizer from WikibaseRepo?
		//      Can't really pass this via the constructor...
		$stringNormalizer = new StringNormalizer();
		$termSqlIndex = new TermSqlIndex(
			$stringNormalizer,
			$this->entityIdComposer,
			$this->entityIdParser,
			false,
			'',
			$this->writeFullEntityIdColumn
		);

		$termSqlIndex->setReadFullEntityIdColumn( $this->readFullEntityIdColumn );

		return $termSqlIndex;
	}

	/**
	 * @see Store::clear
	 */
	public function clear() {
		$this->newSiteLinkStore()->clear();
		$this->getTermIndex()->clear();
	}

	/**
	 * @see Store::rebuild
	 */
	public function rebuild() {
		$dbw = wfGetDB( DB_MASTER );

		// TODO: refactor selection code out (relevant for other stores)

		$pages = $dbw->select(
			[ 'page' ],
			[ 'page_id', 'page_latest' ],
			[ 'page_content_model' => WikibaseRepo::getDefaultInstance()->getEntityContentFactory()->getEntityContentModels() ],
			__METHOD__,
			[ 'LIMIT' => 1000 ] // TODO: continuation
		);

		foreach ( $pages as $pageRow ) {
			$page = WikiPage::newFromID( $pageRow->page_id );
			$revision = Revision::newFromId( $pageRow->page_latest );
			try {
				$page->doEditUpdates( $revision, $GLOBALS['wgUser'] );
			} catch ( DBQueryError $e ) {
				wfLogWarning(
					'editUpdateFailed for ' . $page->getId() . ' on revision ' .
					$revision->getId() . ': ' . $e->getMessage()
				);
			}
		}
	}

	/**
	 * @see Store::newIdGenerator
	 *
	 * @return IdGenerator
	 */
	public function newIdGenerator() {
		return new SqlIdGenerator( wfGetLB(), $this->idBlacklist );
	}

	/**
	 * @see Store::newSiteLinkStore
	 *
	 * @return SiteLinkStore
	 */
	public function newSiteLinkStore() {
		return new SiteLinkTable( 'wb_items_per_site', false );
	}

	/**
	 * @see Store::newEntitiesWithoutTermFinder
	 *
	 * @return EntitiesWithoutTermFinder
	 */
	public function newEntitiesWithoutTermFinder() {
		$sqlEntitiesWithoutTermFinder = new SqlEntitiesWithoutTermFinder(
			$this->entityIdParser,
			$this->entityNamespaceLookup,
			[ // TODO: Make this configurable!
				Item::ENTITY_TYPE => 'Q',
				Property::ENTITY_TYPE => 'P'
			]
		);

		$sqlEntitiesWithoutTermFinder->setCanReadFullEntityIdColumn(
			$this->readFullEntityIdColumn
		);

		return $sqlEntitiesWithoutTermFinder;
	}

	/**
	 * @see Store::newItemsWithoutSitelinksFinder
	 *
	 * @return ItemsWithoutSitelinksFinder
	 */
	public function newItemsWithoutSitelinksFinder() {
		return new SqlItemsWithoutSitelinksFinder(
			$this->entityNamespaceLookup
		);
	}

	/**
	 * @return EntityRedirectLookup
	 */
	public function getEntityRedirectLookup() {
		return new WikiPageEntityRedirectLookup(
			$this->entityTitleLookup,
			$this->entityIdLookup,
			wfGetLB()
		);
	}

	/**
	 * @see Store::getEntityLookup
	 * @see SqlStore::getEntityRevisionLookup
	 *
	 * The EntityLookup returned by this method will resolve redirects.
	 *
	 * @param string $cache Flag string, set to 'uncached' to get an uncached direct lookup service. Set to 'retrieve-only'
	 *        to get a lookup which reads from the cache, but doesn't store retrieved entities there.
	 *
	 * @return EntityLookup
	 */
	public function getEntityLookup( $cache = '' ) {
		$revisionLookup = $this->getEntityRevisionLookup( $cache );
		$revisionBasedLookup = new RevisionBasedEntityLookup( $revisionLookup );
		$resolvingLookup = new RedirectResolvingEntityLookup( $revisionBasedLookup );
		return $resolvingLookup;
	}

	/**
	 * @see Store::getEntityStoreWatcher
	 *
	 * @return EntityStoreWatcher
	 */
	public function getEntityStoreWatcher() {
		if ( !$this->entityStoreWatcher ) {
			$this->entityStoreWatcher = new DispatchingEntityStoreWatcher();
		}

		return $this->entityStoreWatcher;
	}

	/**
	 * @see Store::getEntityStore
	 *
	 * @return EntityStore
	 */
	public function getEntityStore() {
		if ( !$this->entityStore ) {
			$this->entityStore = $this->newEntityStore();
		}

		return $this->entityStore;
	}

	/**
	 * @return WikiPageEntityStore
	 */
	private function newEntityStore() {
		$contentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();
		$idGenerator = $this->newIdGenerator();

		$store = new WikiPageEntityStore( $contentFactory, $idGenerator, $this->entityIdComposer );
		$store->registerWatcher( $this->getEntityStoreWatcher() );
		return $store;
	}

	/**
	 * @see Store::getEntityRevisionLookup
	 *
	 * @param string $cache Flag string, set to 'uncached' to get an uncached direct lookup service. Set to 'retrieve-only'
	 *        to get a lookup which reads from the cache, but doesn't store retrieved entities there.
	 *
	 * @return EntityRevisionLookup
	 */
	public function getEntityRevisionLookup( $cache = '' ) {
		if ( !$this->entityRevisionLookup ) {
			list( $this->rawEntityRevisionLookup, $this->entityRevisionLookup ) = $this->newEntityRevisionLookup();
		}

		if ( $cache === 'uncached' ) {
			return $this->rawEntityRevisionLookup;
		} elseif ( $cache === 'retrieve-only' ) {
			return $this->getRetrieveOnlyCachingEntityRevisionLookup();
		} else {
			return $this->entityRevisionLookup;
		}
	}

	/**
	 * @return string
	 */
	private function getEntityRevisionLookupCacheKey() {
		// NOTE: Keep cache key in sync with DirectSqlStore::newEntityRevisionLookup in WikibaseClient
		return $this->cacheKeyPrefix . ':WikiPageEntityRevisionLookup';
	}

	/**
	 * Creates a strongly connected pair of EntityRevisionLookup services, the first being the
	 * non-caching lookup, the second being the caching lookup.
	 *
	 * @return EntityRevisionLookup[] A two-element array with a "raw", non-caching and a caching
	 *  EntityRevisionLookup.
	 */
	private function newEntityRevisionLookup() {
		$cacheKeyPrefix = $this->getEntityRevisionLookupCacheKey();

		// Maintain a list of watchers to be notified of changes to any entities,
		// in order to update caches.
		/** @var WikiPageEntityStore $dispatcher */
		$dispatcher = $this->getEntityStoreWatcher();

		$dispatcher->registerWatcher( $this->wikibaseServices->getEntityStoreWatcher() );
		$nonCachingLookup = $this->wikibaseServices->getEntityRevisionLookup();

		// Lower caching layer using persistent cache (e.g. memcached).
		$persistentCachingLookup = new CachingEntityRevisionLookup(
			$nonCachingLookup,
			wfGetCache( $this->cacheType ),
			$this->cacheDuration,
			$cacheKeyPrefix
		);
		// We need to verify the revision ID against the database to avoid stale data.
		$persistentCachingLookup->setVerifyRevision( true );
		$dispatcher->registerWatcher( $persistentCachingLookup );

		// Top caching layer using an in-process hash.
		$hashCachingLookup = new CachingEntityRevisionLookup(
			$persistentCachingLookup,
			new HashBagOStuff( [ 'maxKeys' => 1000 ] )
		);
		// No need to verify the revision ID, we'll ignore updates that happen during the request.
		$hashCachingLookup->setVerifyRevision( false );
		$dispatcher->registerWatcher( $hashCachingLookup );

		return [ $nonCachingLookup, $hashCachingLookup ];
	}

	/**
	 * @return CachingEntityRevisionLookup
	 */
	private function getRetrieveOnlyCachingEntityRevisionLookup() {
		if ( !$this->retrieveOnlyEntityRevisionLookup ) {
			$retrieveOnlyEntityRevisionLookup = new CachingEntityRevisionLookup(
				$this->getEntityRevisionLookup( 'uncached' ),
				wfGetCache( $this->cacheType ),
				$this->cacheDuration,
				$this->getEntityRevisionLookupCacheKey(),
				'retrieve-only'
			);

			$retrieveOnlyEntityRevisionLookup->setVerifyRevision( true );

			/** @var WikiPageEntityStore $dispatcher */
			$dispatcher = $this->getEntityStoreWatcher();
			$dispatcher->registerWatcher( $retrieveOnlyEntityRevisionLookup );

			$this->retrieveOnlyEntityRevisionLookup = $retrieveOnlyEntityRevisionLookup;
		}

		return $this->retrieveOnlyEntityRevisionLookup;
	}

	/**
	 * @see Store::getEntityInfoBuilderFactory
	 *
	 * @return EntityInfoBuilderFactory
	 */
	public function getEntityInfoBuilderFactory() {
		return $this->wikibaseServices->getEntityInfoBuilderFactory();
	}

	/**
	 * @see Store::getPropertyInfoLookup
	 *
	 * @return PropertyInfoLookup
	 */
	public function getPropertyInfoLookup() {
		if ( !$this->propertyInfoLookup ) {
			$this->propertyInfoLookup = $this->newPropertyInfoLookup();
		}

		return $this->propertyInfoLookup;
	}

	/**
	 * Creates a new PropertyInfoLookup instance
	 * Note: cache key used by the lookup should be the same as the cache key used
	 * by CachedPropertyInfoStore.
	 *
	 * @return PropertyInfoLookup
	 */
	private function newPropertyInfoLookup() {
		$nonCachingLookup = $this->wikibaseServices->getPropertyInfoLookup();

		$cacheKey = $this->cacheKeyPrefix . ':CacheAwarePropertyInfoStore';

		return new CachingPropertyInfoLookup(
			$nonCachingLookup,
			ObjectCache::getInstance( $this->cacheType ),
			$this->cacheDuration,
			$cacheKey
		);
	}

	/**
	 * @see Store::getPropertyInfoStore
	 *
	 * @return PropertyInfoStore
	 */
	public function getPropertyInfoStore() {
		if ( !$this->propertyInfoStore ) {
			$this->propertyInfoStore = $this->newPropertyInfoStore();
		}

		return $this->propertyInfoStore;
	}

	/**
	 * Creates a new PropertyInfoStore
	 * Note: cache key used by the lookup should be the same as the cache key used
	 * by CachedPropertyInfoLookup.
	 *
	 * @return PropertyInfoStore
	 */
	private function newPropertyInfoStore() {
		// TODO: this should be changed so it uses the same PropertyInfoTable instance which is used by
		// the lookup configured for local repo in DispatchingPropertyInfoLookup (if using dispatching services
		// from client). As we don't want to introduce DispatchingPropertyInfoStore service, this should probably
		// be accessing RepositorySpecificServices of local repo (which is currently not exposed
		// to/by WikibaseClient).
		// For non-dispatching-service use case it is already using the same PropertyInfoTable instance
		// for both store and lookup - no change needed here.

		$table = $this->getPropertyInfoTable();
		$cacheKey = $this->cacheKeyPrefix . ':CacheAwarePropertyInfoStore';

		// TODO: we might want to register the CacheAwarePropertyInfoLookup instance created by
		// newPropertyInfoLookup as a watcher to this CacheAwarePropertyInfoStore instance.
		return new CacheAwarePropertyInfoStore(
			$table,
			ObjectCache::getInstance( $this->cacheType ),
			$this->cacheDuration,
			$cacheKey
		);
	}

	/**
	 * @return PropertyInfoTable
	 */
	private function getPropertyInfoTable() {
		if ( $this->propertyInfoTable === null ) {
			$this->propertyInfoTable = new PropertyInfoTable( $this->entityIdComposer );
		}
		return $this->propertyInfoTable;
	}

	/**
	 * @return SiteLinkConflictLookup
	 */
	public function getSiteLinkConflictLookup() {
		return new SqlSiteLinkConflictLookup( $this->entityIdComposer );
	}

	/**
	 * @return PrefetchingWikiPageEntityMetaDataAccessor
	 */
	public function getEntityPrefetcher() {
		if ( $this->entityPrefetcher === null ) {
			$this->entityPrefetcher = $this->newEntityPrefetcher();
		}

		return $this->entityPrefetcher;
	}

	/**
	 * @return EntityPrefetcher
	 */
	private function newEntityPrefetcher() {
		return $this->wikibaseServices->getEntityPrefetcher();
	}

	/**
	 * @return EntityChangeLookup
	 */
	public function getEntityChangeLookup() {
		return new EntityChangeLookup( $this->entityChangeFactory, $this->entityIdParser );
	}

	/**
	 * @return SqlChangeStore
	 */
	public function getChangeStore() {
		return new SqlChangeStore( wfGetLB() );
	}

}
