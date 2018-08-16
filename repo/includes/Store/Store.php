<?php

namespace Wikibase;

use Wikibase\DataModel\Services\Entity\EntityPrefetcher;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\EntityRedirectLookup;
use Wikibase\Lib\Store\EntityByLinkedTitleLookup;
use Wikibase\Lib\Store\EntityInfoBuilder;
use Wikibase\Lib\Store\Sql\EntityChangeLookup;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Lib\Store\EntityStoreWatcher;
use Wikibase\Lib\Store\LabelConflictFinder;
use Wikibase\Lib\Store\PropertyInfoLookup;
use Wikibase\Lib\Store\PropertyInfoStore;
use Wikibase\Lib\Store\SiteLinkStore;
use Wikibase\Repo\Store\ChangeStore;
use Wikibase\Repo\Store\EntitiesWithoutTermFinder;
use Wikibase\Repo\Store\ItemsWithoutSitelinksFinder;
use Wikibase\Repo\Store\SiteLinkConflictLookup;

/**
 * Store interface. All interaction with store Wikibase does on top
 * of storing pages and associated core MediaWiki indexing is done
 * through this interface.
 *
 * @todo: provide getXXX() methods for getting local pseudo-singletons (shared service objects).
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
interface Store {

	const LOOKUP_CACHING_ENABLED = '';
	const LOOKUP_CACHING_DISABLED = 'uncached';
	const LOOKUP_CACHING_RETRIEVE_ONLY = 'retrieve-only';

	/**
	 * @return SiteLinkStore
	 */
	public function newSiteLinkStore();

	/**
	 * @return EntityByLinkedTitleLookup
	 */
	public function getEntityByLinkedTitleLookup();

	/**
	 * Removes all data from the store.
	 */
	public function clear();

	/**
	 * Clear the in-memory cache used when self::LOOKUP_CACHING_ENABLED
	 */
	public function clearInMemoryCache();

	/**
	 * Rebuilds the store from the original data source.
	 */
	public function rebuild();

	/**
	 * @return TermIndex
	 */
	public function getTermIndex();

	/**
	 * @return LabelConflictFinder
	 */
	public function getLabelConflictFinder();

	/**
	 * @return IdGenerator
	 */
	public function newIdGenerator();

	/**
	 * @return EntitiesWithoutTermFinder
	 */
	public function newEntitiesWithoutTermFinder();

	/**
	 * @return ItemsWithoutSitelinksFinder
	 */
	public function newItemsWithoutSitelinksFinder();

	/**
	 * @return EntityRedirectLookup
	 */
	public function getEntityRedirectLookup();

	/**
	 * @param string $cache One of self::LOOKUP_CACHING_*
	 *        self::LOOKUP_CACHING_DISABLED to get an uncached direct lookup
	 *        self::LOOKUP_CACHING_RETRIEVE_ONLY to get a lookup which reads from the cache, but doesn't store retrieved entities
	 *        self::LOOKUP_CACHING_ENABLED to get a caching lookup (default)
	 *
	 * @return EntityLookup
	 */
	public function getEntityLookup( $cache = self::LOOKUP_CACHING_ENABLED );

	/**
	 * @param string $cache One of self::LOOKUP_CACHING_*
	 *        self::LOOKUP_CACHING_DISABLED to get an uncached direct lookup
	 *        self::LOOKUP_CACHING_RETRIEVE_ONLY to get a lookup which reads from the cache, but doesn't store retrieved entities
	 *        self::LOOKUP_CACHING_ENABLED to get a caching lookup (default)
	 *
	 * @return EntityRevisionLookup
	 */
	public function getEntityRevisionLookup( $cache = self::LOOKUP_CACHING_ENABLED );

	/**
	 * @return EntityStore
	 */
	public function getEntityStore();

	/**
	 * Returns an EntityStoreWatcher that should be notified of changes to
	 * entities, in order to keep any caches updated.
	 *
	 * @return EntityStoreWatcher
	 */
	public function getEntityStoreWatcher();

	/**
	 * @return EntityInfoBuilder
	 */
	public function getEntityInfoBuilder();

	/**
	 * @return PropertyInfoLookup
	 */
	public function getPropertyInfoLookup();

	/**
	 * @return PropertyInfoStore
	 */
	public function getPropertyInfoStore();

	/**
	 * @return SiteLinkConflictLookup
	 */
	public function getSiteLinkConflictLookup();

	/**
	 * Returns an EntityPrefetcher which can be used to prefetch a list of entity
	 * ids in case we need to for example load a batch of entity ids.
	 *
	 * @return EntityPrefetcher
	 */
	public function getEntityPrefetcher();

	/**
	 * @return EntityChangeLookup
	 */
	public function getEntityChangeLookup();

	/**
	 * @return ChangeStore
	 */
	public function getChangeStore();

}
