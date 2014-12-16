<?php

namespace Wikibase;

use Language;
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
	 * @var EntityLookup
	 */
	private $entityRevisionLookup = null;

	/**
	 * @var PropertyLabelResolver
	 */
	private $propertyLabelResolver = null;

	/**
	 * @var TermIndex
	 */
	private $termIndex = null;

	/**
	 * @var EntityIdLookup
	 */
	private $entityIdLookup = null;

	/**
	 * @var PropertyInfoTable
	 */
	private $propertyInfoTable = null;

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 * @var String|bool $repoWiki
	 */
	protected $repoWiki;

	/**
	 * @var Language
	 */
	protected $language;

	/**
	 * @var SiteLinkTable
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
	private $cachePrefix;

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
	 * @var string
	 */
	private $siteId;

	/**
	 * @param EntityContentDataCodec $contentCodec
	 * @param Language $wikiLanguage
	 * @param EntityIdParser $entityIdParser
	 * @param string $repoWiki the symbolic database name of the repo wiki
	 */
	public function __construct(
		EntityContentDataCodec $contentCodec,
		Language $wikiLanguage,
		EntityIdParser $entityIdParser,
		$repoWiki
	) {
		$this->repoWiki = $repoWiki;
		$this->language = $wikiLanguage;
		$this->contentCodec = $contentCodec;

		// @TODO: Inject
		$settings = WikibaseClient::getDefaultInstance()->getSettings();
		$cachePrefix = $settings->getSetting( 'sharedCacheKeyPrefix' );
		$cacheDuration = $settings->getSetting( 'sharedCacheDuration' );
		$cacheType = $settings->getSetting( 'sharedCacheType' );
		$siteId = $settings->getSetting( 'siteGlobalID' );

		$this->changesDatabase = $settings->getSetting( 'changesDatabase' );
		$this->useLegacyUsageIndex = $settings->getSetting( 'useLegacyUsageIndex' );

		$this->cachePrefix = $cachePrefix;
		$this->cacheDuration = $cacheDuration;
		$this->cacheType = $cacheType;
		$this->entityIdParser = $entityIdParser;
		$this->siteId = $siteId;
	}

	/**
	 * @see Store::getSubscriptionManager
	 *
	 * @since 0.5
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
	 * @see Store::getUsageLookup
	 *
	 * @note: If the useLegacyUsageIndex option is set, this returns a SiteLinkUsageLookup.
	 *
	 * @since 0.5
	 *
	 * @return UsageLookup
	 */
	public function getUsageLookup() {
		if ( !$this->usageLookup ) {
			if ( $this->useLegacyUsageIndex ) {
				$this->usageLookup = new SiteLinkUsageLookup(
					$this->siteId,
					$this->getSiteLinkTable(),
					new TitleFactory()
				);
			} else {
				$this->usageLookup = $this->getUsageTracker();
			}
		}

		return $this->usageLookup;
	}

	/**
	 * @see Store::getUsageTracker
	 *
	 * @note: If the useLegacyUsageIndex option is set, this returns a NullUsageTracker!
	 *
	 * @since 0.5
	 *
	 * @return UsageTracker
	 */
	public function getUsageTracker() {
		if ( !$this->usageTracker ) {
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
	 * @see Store::getSiteLinkTable
	 *
	 * @since 0.4
	 *
	 * @return SiteLinkLookup
	 */
	public function getSiteLinkTable() {
		if ( !$this->siteLinkTable ) {
			$this->siteLinkTable = $this->newSiteLinkTable();
		}

		return $this->siteLinkTable;
	}

	/**
	 * @since 0.3
	 *
	 * @return SiteLinkLookup
	 */
	protected function newSiteLinkTable() {
		return new SiteLinkTable( 'wb_items_per_site', true, $this->repoWiki );
	}


	/**
	 * @see ClientStore::getEntityLookup
	 *
	 * The EntityLookup returned by this method will resolve redirects.
	 *
	 * @since 0.4
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
	 * @since 0.5
	 *
	 * @return EntityRevisionLookup
	 */
	public function getEntityRevisionLookup() {
		if ( !$this->entityRevisionLookup ) {
			$this->entityRevisionLookup = $this->newEntityRevisionLookup();
		}

		return $this->entityRevisionLookup;
	}

	/**
	 * Create a new EntityLookup
	 *
	 * @return CachingEntityRevisionLookup
	 */
	protected function newEntityRevisionLookup() {
		//NOTE: Keep in sync with SqlStore::newEntityLookup on the repo
		$key = $this->cachePrefix . ':WikiPageEntityRevisionLookup';

		$lookup = new WikiPageEntityRevisionLookup(
			$this->contentCodec,
			$this->entityIdParser,
			$this->repoWiki
		);

		// Lower caching layer using persistent cache (e.g. memcached).
		// We need to verify the revision ID against the database to avoid stale data.
		$lookup = new CachingEntityRevisionLookup( $lookup, wfGetCache( $this->cacheType ), $this->cacheDuration, $key );
		$lookup->setVerifyRevision( true );

		// Top caching layer using an in-process hash.
		// No need to verify the revision ID, we'll ignore updates that happen during the request.
		$lookup = new CachingEntityRevisionLookup( $lookup, new \HashBagOStuff() );
		$lookup->setVerifyRevision( false );

		return $lookup;
	}

	/**
	 * Get a TermIndex object
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
	 * Get a EntityIdLookup object
	 *
	 * @return EntityIdLookup
	 */
	public function getEntityIdLookup() {
		if ( !$this->entityIdLookup ) {
			$this->entityIdLookup = new PagePropsEntityIdLookup(
				wfGetLB(),
				$this->entityIdParser
			);
		}

		return $this->entityIdLookup;
	}

	/**
	 * Create a new TermIndex instance
	 *
	 * @return TermIndex
	 */
	protected function newTermIndex() {
		//TODO: Get $stringNormalizer from WikibaseClient?
		//      Can't really pass this via the constructor...
		$stringNormalizer = new StringNormalizer();
		return new TermSqlIndex( $stringNormalizer , $this->repoWiki );
	}

	/**
	 * Get a PropertyLabelResolver object
	 *
	 * @return PropertyLabelResolver
	 */
	public function getPropertyLabelResolver() {
		if ( !$this->propertyLabelResolver ) {
			$this->propertyLabelResolver = $this->newPropertyLabelResolver();
		}

		return $this->propertyLabelResolver;
	}


	/**
	 * Create a new PropertyLabelResolver instance
	 *
	 * @return PropertyLabelResolver
	 */
	protected function newPropertyLabelResolver() {
		$langCode = $this->language->getCode();

		// cache key needs to be language specific
		$key = $this->cachePrefix . ':TermPropertyLabelResolver' . '/' . $langCode;

		return new TermPropertyLabelResolver(
			$langCode,
			$this->getTermIndex(),
			ObjectCache::getInstance( $this->cacheType ),
			$this->cacheDuration,
			$key
		);
	}

	/**
	 * @see Store::newChangesTable
	 *
	 * @since 0.4
	 *
	 * @return ChangesTable
	 */
	public function newChangesTable() {
		return new ChangesTable( $this->repoWiki );
	}

	/**
	 * Does nothing.
	 *
	 * @since 0.3
	 */
	public function clear() {
		// noop
	}

	/**
	 * Does nothing.
	 *
	 * @since 0.3
	 */
	public function rebuild() {
		$this->clear();
	}


	/**
	 * @see Store::getPropertyInfoStore
	 *
	 * @since 0.4
	 *
	 * @return PropertyInfoStore
	 */
	public function getPropertyInfoStore() {
		if ( !$this->propertyInfoTable ) {
			$this->propertyInfoTable = $this->newPropertyInfoTable();
		}

		return $this->propertyInfoTable;
	}

	/**
	 * Creates a new PropertyInfoTable
	 *
	 * @return PropertyInfoTable
	 */
	protected function newPropertyInfoTable() {
		$usePropertyInfoTable = WikibaseClient::getDefaultInstance()
			->getSettings()->getSetting( 'usePropertyInfoTable' );

		if ( $usePropertyInfoTable ) {
			$table = new PropertyInfoTable( true, $this->repoWiki );
			$key = $this->cachePrefix . ':CachingPropertyInfoStore';
			return new CachingPropertyInfoStore( $table, ObjectCache::getInstance( $this->cacheType ),
				$this->cacheDuration, $key );
		} else {
			// dummy info store
			return new DummyPropertyInfoStore();
		}
	}
}
