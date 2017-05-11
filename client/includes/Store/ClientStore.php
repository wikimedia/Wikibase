<?php

namespace Wikibase;

use Wikibase\Client\RecentChanges\RecentChangesDuplicateDetector;
use Wikibase\Client\Store\UsageUpdater;
use Wikibase\Client\Usage\SubscriptionManager;
use Wikibase\Client\Usage\UsageLookup;
use Wikibase\Client\Usage\UsageTracker;
use Wikibase\DataModel\Services\Entity\EntityPrefetcher;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Term\PropertyLabelResolver;
use Wikibase\Lib\Store\EntityChangeLookup;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\DataAccess\Store\PropertyInfoLookup;
use Wikibase\Lib\Store\SiteLinkLookup;
use Wikibase\Store\EntityIdLookup;

/**
 * Client store interface.
 *
 * @license GPL-2.0+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 */
interface ClientStore {

	/**
	 * @return RecentChangesDuplicateDetector|null
	 */
	public function getRecentChangesDuplicateDetector();

	/**
	 * @return SiteLinkLookup
	 */
	public function getSiteLinkLookup();

	/**
	 * @return UsageLookup
	 */
	public function getUsageLookup();

	/**
	 * @return UsageTracker
	 */
	public function getUsageTracker();

	/**
	 * @return SubscriptionManager
	 */
	public function getSubscriptionManager();

	/**
	 * @return EntityLookup
	 */
	public function getEntityLookup();

	/**
	 * @return EntityRevisionLookup
	 */
	public function getEntityRevisionLookup();

	/**
	 * @return PropertyLabelResolver
	 */
	public function getPropertyLabelResolver();

	/**
	 * @return TermIndex
	 */
	public function getTermIndex();

	/**
	 * @return EntityIdLookup
	 */
	public function getEntityIdLookup();

	/**
	 * @return PropertyInfoLookup
	 */
	public function getPropertyInfoLookup();

	/**
	 * Removes all data from the store.
	 */
	public function clear();

	/**
	 * Rebuilds all data in the store.
	 */
	public function rebuild();

	/**
	 * Returns an EntityPrefetcher which can be used to prefetch a list of entity
	 * ids in case we need to for example load a batch of entity ids.
	 *
	 * @return EntityPrefetcher
	 */
	public function getEntityPrefetcher();

	/**
	 * @return UsageUpdater
	 */
	public function getUsageUpdater();

	/**
	 * @return EntityChangeLookup
	 */
	public function getEntityChangeLookup();

}
