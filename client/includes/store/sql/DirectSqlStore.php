<?php

namespace Wikibase;

use HashBagOStuff;
use LoadBalancer;
use ObjectCache;
use Site;
use Wikibase\Client\Store\EntityIdLookup;
use Wikibase\Client\Store\Sql\ConnectionManager;
use Wikibase\Client\Store\Sql\PagePropsEntityIdLookup;
use Wikibase\Client\Store\TitleFactory;
use Wikibase\Client\Usage\NullUsageTracker;
use Wikibase\Client\Usage\SiteLinkUsageLookup;
use Wikibase\Client\Usage\Sql\SqlUsageTracker;
use Wikibase\Client\Usage\SubscriptionManager;
use Wikibase\Client\Usage\UsageLookup;
use Wikibase\Client\Usage\UsageTracker;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\Lib\Store\CachingEntityRevisionLookup;
use Wikibase\Lib\Store\EntityContentDataCodec;
use Wikibase\Lib\Store\EntityLookup;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\RedirectResolvingEntityLookup;
use Wikibase\Lib\Store\RevisionBasedEntityLookup;
use Wikibase\Lib\Store\SiteLinkLookup;
use Wikibase\Lib\Store\SiteLinkTable;
use Wikibase\Lib\Store\WikiPageEntityRevisionLookup;

/**
 * Implementation of the client store interface using direct access to the repository's
 * database via MediaWiki's foreign wiki mechanism as implemented by LBFactoryMulti.
 *
 * @since 0.3
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 */
class DirectSqlStore implements ClientStore {

	/**
	 * @var EntityLookup|null
	 */
	private $entityRevisionLookup = null;

	/**
	 * @var PropertyLabelResolver|null
	 */
	private $propertyLabelResolver = null;

	/**
	 * @var TermIndex|null
	 */
	private $termIndex = null;

	/**
	 * @var EntityIdLookup|null
	 */
	private $entityIdLookup = null;

	/**
	 * @var PropertyInfoTable|null
	 */
	private $propertyInfoTable = null;

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 * @var string|bool The database name of the repo wiki or false for the local wiki
	 */
	private $repoWiki;

	/**
	 * @var string
	 */
	private $languageCode;

	/**
	 * @var SiteLinkTable|null
	 */
	private $siteLinkTable = null;

	/**
	 * @var UsageTracker|null
	 */
	private $usageTracker = null;

	/**
	 * @var UsageLookup|null
	 */
	private $usageLookup = null;

	/**
	 * @var Site|null
	 */
	private $site = null;

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
	 * @var EntityContentDataCodec
	 */
	private $contentCodec;

	/**
	 * @var bool
	 */
	private $useLegacyUsageIndex;

	/**
	 * @param EntityContentDataCodec $contentCodec
	 * @param EntityIdParser $entityIdParser
	 * @param string|bool $repoWiki the symbolic database name of the repo wiki
	 * @param string $languageCode
	 */
	public function __construct(
		EntityContentDataCodec $contentCodec,
		EntityIdParser $entityIdParser,
		$repoWiki = false,
		$languageCode
	) {
		$this->contentCodec = $contentCodec;
		$this->entityIdParser = $entityIdParser;
		$this->repoWiki = $repoWiki;
		$this->languageCode = $languageCode;

		$settings = WikibaseClient::getDefaultInstance()->getSettings();

		$this->cacheKeyPrefix = $settings->getSetting( 'sharedCacheKeyPrefix' );
		$this->cacheType = $settings->getSetting( 'sharedCacheType' );
		$this->cacheDuration = $settings->getSetting( 'sharedCacheDuration' );
		$this->useLegacyUsageIndex = $settings->getSetting( 'useLegacyUsageIndex' );
	}

	/**
	 * @see ClientStore::getSubscriptionManager
	 *
	 * @return SubscriptionManager
	 */
	public function getSubscriptionManager() {
		return new SubscriptionManager();
	}

	/**
	 * Returns a LoadBalancer that acts as a factory for connections to the local (client) wiki's
	 * database.
	 *
	 * @return LoadBalancer
	 */
	private function getLocalLoadBalancer() {
		return wfGetLB();
	}

	/**
	 * @see ClientStore::getUsageLookup
	 *
	 * @note: If the useLegacyUsageIndex option is set, this returns a SiteLinkUsageLookup.
	 *
	 * @return UsageLookup
	 */
	public function getUsageLookup() {
		if ( $this->usageLookup === null ) {
			if ( $this->useLegacyUsageIndex ) {
				$this->usageLookup = new SiteLinkUsageLookup(
					$this->getSite()->getGlobalId(),
					$this->getSiteLinkLookup(),
					new TitleFactory()
				);
			} else {
				$this->usageLookup = $this->getUsageTracker();
			}
		}

		return $this->usageLookup;
	}

	/**
	 * @see ClientStore::getUsageTracker
	 *
	 * @note: If the useLegacyUsageIndex option is set, this returns a NullUsageTracker!
	 *
	 * @return UsageTracker
	 */
	public function getUsageTracker() {
		if ( $this->usageTracker === null ) {
			if ( $this->useLegacyUsageIndex ) {
				$this->usageTracker = new NullUsageTracker();
			} else {
				$connectionManager = new ConnectionManager( $this->getLocalLoadBalancer() );
				$this->usageTracker = new SqlUsageTracker( $this->entityIdParser, $connectionManager );
			}
		}

		return $this->usageTracker;
	}

	/**
	 * Sets the site object representing the local wiki.
	 * For testing only!
	 *
	 * @todo: remove this once the Site can be injected via the constructor!
	 *
	 * @param Site $site
	 */
	public function setSite( Site $site ) {
		$this->site = $site;
	}

	/**
	 * Returns the site object representing the local wiki.
	 *
	 * @return Site
	 */
	private function getSite() {
		// @FIXME: inject the site
		if ( $this->site === null ) {
			$this->site = WikibaseClient::getDefaultInstance()->getSite();
		}

		return $this->site;
	}

	/**
	 * @see ClientStore::getSiteLinkLookup
	 *
	 * @return SiteLinkLookup
	 */
	public function getSiteLinkLookup() {
		if ( $this->siteLinkTable === null ) {
			$this->siteLinkTable = $this->newSiteLinkTable();
		}

		return $this->siteLinkTable;
	}

	/**
	 * @return SiteLinkLookup
	 */
	private function newSiteLinkTable() {
		return new SiteLinkTable( 'wb_items_per_site', true, $this->repoWiki );
	}

	/**
	 * @see ClientStore::getEntityLookup
	 *
	 * The EntityLookup returned by this method will resolve redirects.
	 *
	 * @return EntityLookup
	 */
	public function getEntityLookup() {
		$revisionLookup = $this->getEntityRevisionLookup();
		$lookup = new RevisionBasedEntityLookup( $revisionLookup );
		$lookup = new RedirectResolvingEntityLookup( $lookup );
		return $lookup;
	}

	/**
	 * @see ClientStore::getEntityRevisionLookup
	 *
	 * @return EntityRevisionLookup
	 */
	public function getEntityRevisionLookup() {
		if ( $this->entityRevisionLookup === null ) {
			$this->entityRevisionLookup = $this->newEntityRevisionLookup();
		}

		return $this->entityRevisionLookup;
	}

	/**
	 * @return EntityRevisionLookup
	 */
	private function newEntityRevisionLookup() {
		// NOTE: Keep cache key in sync with SqlStore::newEntityRevisionLookup on the Repo
		$cacheKeyPrefix = $this->cacheKeyPrefix . ':WikiPageEntityRevisionLookup';

		$lookup = new WikiPageEntityRevisionLookup(
			$this->contentCodec,
			$this->entityIdParser,
			$this->repoWiki
		);

		// Lower caching layer using persistent cache (e.g. memcached).
		// We need to verify the revision ID against the database to avoid stale data.
		$lookup = new CachingEntityRevisionLookup(
			$lookup,
			wfGetCache( $this->cacheType ),
			$this->cacheDuration,
			$cacheKeyPrefix
		);
		$lookup->setVerifyRevision( true );

		// Top caching layer using an in-process hash.
		// No need to verify the revision ID, we'll ignore updates that happen during the request.
		$lookup = new CachingEntityRevisionLookup( $lookup, new HashBagOStuff() );
		$lookup->setVerifyRevision( false );

		return $lookup;
	}

	/**
	 * @see ClientStore::getTermIndex
	 *
	 * @return TermIndex
	 */
	public function getTermIndex() {
		if ( $this->termIndex === null ) {
			$this->termIndex = $this->newTermIndex();
		}

		return $this->termIndex;
	}

	/**
	 * @see ClientStore::getEntityIdLookup
	 *
	 * @return EntityIdLookup
	 */
	public function getEntityIdLookup() {
		if ( $this->entityIdLookup === null ) {
			$this->entityIdLookup = new PagePropsEntityIdLookup(
				wfGetLB(),
				$this->entityIdParser
			);
		}

		return $this->entityIdLookup;
	}

	/**
	 * @return TermIndex
	 */
	private function newTermIndex() {
		//TODO: Get $stringNormalizer from WikibaseClient?
		//      Can't really pass this via the constructor...
		$stringNormalizer = new StringNormalizer();
		return new TermSqlIndex( $stringNormalizer, $this->repoWiki );
	}

	/**
	 * @see ClientStore::getPropertyLabelResolver
	 *
	 * @return PropertyLabelResolver
	 */
	public function getPropertyLabelResolver() {
		if ( $this->propertyLabelResolver === null ) {
			$this->propertyLabelResolver = $this->newPropertyLabelResolver();
		}

		return $this->propertyLabelResolver;
	}

	/**
	 * @return PropertyLabelResolver
	 */
	private function newPropertyLabelResolver() {
		// cache key needs to be language specific
		$cacheKey = $this->cacheKeyPrefix . ':TermPropertyLabelResolver' . '/' . $this->languageCode;

		return new TermPropertyLabelResolver(
			$this->languageCode,
			$this->getTermIndex(),
			ObjectCache::getInstance( $this->cacheType ),
			$this->cacheDuration,
			$cacheKey
		);
	}

	/**
	 * @see ClientStore::newChangesTable
	 *
	 * @return ChangesTable
	 */
	public function newChangesTable() {
		return new ChangesTable( $this->repoWiki );
	}

	/**
	 * @see ClientStore::clear
	 *
	 * Does nothing.
	 */
	public function clear() {
		// noop
	}

	/**
	 * @see ClientStore::rebuild
	 *
	 * Does nothing.
	 */
	public function rebuild() {
		$this->clear();
	}

	/**
	 * @see ClientStore::getPropertyInfoStore
	 *
	 * @return PropertyInfoStore
	 */
	public function getPropertyInfoStore() {
		if ( $this->propertyInfoTable === null ) {
			$this->propertyInfoTable = $this->newPropertyInfoTable();
		}

		return $this->propertyInfoTable;
	}

	/**
	 * @return PropertyInfoTable
	 */
	private function newPropertyInfoTable() {
		$usePropertyInfoTable = WikibaseClient::getDefaultInstance()
			->getSettings()->getSetting( 'usePropertyInfoTable' );

		if ( $usePropertyInfoTable ) {
			$table = new PropertyInfoTable( true, $this->repoWiki );
			$cacheKey = $this->cacheKeyPrefix . ':CachingPropertyInfoStore';
			return new CachingPropertyInfoStore(
				$table,
				ObjectCache::getInstance( $this->cacheType ),
				$this->cacheDuration,
				$cacheKey
			);
		} else {
			// dummy info store
			return new DummyPropertyInfoStore();
		}
	}

}
