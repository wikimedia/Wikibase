<?php

namespace Wikibase\Client;

use DataTypes\DataTypeFactory;
use DataValues\Deserializers\DataValueDeserializer;
use Deserializers\Deserializer;
use Exception;
use Hooks;
use Language;
use LogicException;
use MediaWikiSite;
use MWException;
use Site;
use SiteSQLStore;
use SiteStore;
use StubObject;
use Wikibase\Client\Changes\AffectedPagesFinder;
use Wikibase\Client\Changes\ChangeHandler;
use Wikibase\Client\Changes\ChangeRunCoalescer;
use Wikibase\Client\Changes\WikiPageUpdater;
use Wikibase\Client\DataAccess\PropertyIdResolver;
use Wikibase\Client\DataAccess\PropertyParserFunction\PropertyClaimsRendererFactory;
use Wikibase\Client\DataAccess\PropertyParserFunction\Runner;
use Wikibase\Client\DataAccess\RestrictedEntityLookup;
use Wikibase\Client\DataAccess\SnaksFinder;
use Wikibase\Client\Hooks\LanguageLinkBadgeDisplay;
use Wikibase\Client\Hooks\OtherProjectsSidebarGeneratorFactory;
use Wikibase\Client\Hooks\ParserFunctionRegistrant;
use Wikibase\Client\Store\TitleFactory;
use Wikibase\ClientStore;
use Wikibase\DataModel\DeserializerFactory;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Services\Diff\EntityDiffer;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\DispatchingEntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Services\EntityId\SuffixEntityIdParser;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\EntityRetrievingDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\TermLookup;
use Wikibase\DataModel\Services\Term\TermBuffer;
use Wikibase\DirectSqlStore;
use Wikibase\EntityFactory;
use Wikibase\InternalSerialization\DeserializerFactory as InternalDeserializerFactory;
use Wikibase\LangLinkHandler;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\Changes\EntityChangeFactory;
use Wikibase\Lib\DataTypeDefinitions;
use Wikibase\Lib\FormatterLabelDescriptionLookupFactory;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\Lib\OutputFormatSnakFormatterFactory;
use Wikibase\Lib\OutputFormatValueFormatterFactory;
use Wikibase\Lib\PropertyInfoDataTypeLookup;
use Wikibase\Lib\Store\EntityContentDataCodec;
use Wikibase\Lib\WikibaseContentLanguages;
use Wikibase\Lib\WikibaseSnakFormatterBuilders;
use Wikibase\Lib\WikibaseValueFormatterBuilders;
use Wikibase\NamespaceChecker;
use Wikibase\SettingsArray;
use Wikibase\Store\BufferingTermLookup;
use Wikibase\StringNormalizer;

/**
 * Top level factory for the WikibaseClient extension.
 *
 * @since 0.4
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 */
final class WikibaseClient {

	/**
	 * @var SettingsArray
	 */
	private $settings;

	/**
	 * @var Language
	 */
	private $contentLanguage;

	/**
	 * @var SiteStore|null
	 */
	private $siteStore;

	/**
	 * @var PropertyDataTypeLookup|null
	 */
	private $propertyDataTypeLookup = null;

	/**
	 * @var DataTypeFactory|null
	 */
	private $dataTypeFactory = null;

	/**
	 * @var EntityIdParser|null
	 */
	private $entityIdParser = null;

	/**
	 * @var LanguageFallbackChainFactory|null
	 */
	private $languageFallbackChainFactory = null;

	/**
	 * @var ClientStore|null
	 */
	private $store = null;

	/**
	 * @var StringNormalizer|null
	 */
	private $stringNormalizer = null;

	/**
	 * @var Site|null
	 */
	private $site = null;

	/**
	 * @var string|null
	 */
	private $siteGroup = null;

	/**
	 * @var OutputFormatSnakFormatterFactory|null
	 */
	private $snakFormatterFactory = null;

	/**
	 * @var OutputFormatValueFormatterFactory|null
	 */
	private $valueFormatterFactory = null;

	/**
	 * @var LangLinkHandler|null
	 */
	private $langLinkHandler = null;

	/**
	 * @var ParserOutputDataUpdater|null
	 */
	private $parserOutputDataUpdater = null;

	/**
	 * @var NamespaceChecker|null
	 */
	private $namespaceChecker = null;

	/**
	 * @var RestrictedEntityLookup|null
	 */
	private $restrictedEntityLookup = null;

	/**
	 * @var DataTypeDefinitions
	 */
	private $dataTypeDefinitions;

	/**
	 * @var TermLookup|null
	 */
	private $termLookup = null;

	/**
	 * @since 0.4
	 *
	 * @param SettingsArray $settings
	 * @param Language $contentLanguage
	 * @param DataTypeDefinitions $dataTypeDefinitions
	 * @param SiteStore|null $siteStore
	 */
	public function __construct(
		SettingsArray $settings,
		Language $contentLanguage,
		DataTypeDefinitions $dataTypeDefinitions,
		SiteStore $siteStore = null
	) {
		$this->settings = $settings;
		$this->contentLanguage = $contentLanguage;
		$this->siteStore = $siteStore;
		$this->dataTypeDefinitions = $dataTypeDefinitions;
	}

	/**
	 * @since 0.4
	 *
	 * @return DataTypeFactory
	 */
	public function getDataTypeFactory() {
		if ( $this->dataTypeFactory === null ) {
			$this->dataTypeFactory = new DataTypeFactory( $this->dataTypeDefinitions->getValueTypes() );
		}

		return $this->dataTypeFactory;
	}

	/**
	 * @since 0.4
	 *
	 * @return EntityIdParser
	 */
	public function getEntityIdParser() {
		if ( $this->entityIdParser === null ) {
			//TODO: make the ID builders configurable
			$this->entityIdParser = new DispatchingEntityIdParser( BasicEntityIdParser::getBuilders() );
		}

		return $this->entityIdParser;
	}

	/**
	 * @return EntityLookup
	 */
	private function getEntityLookup() {
		return $this->getStore()->getEntityLookup();
	}

	/**
	 * @return TermBuffer
	 */
	public function getTermBuffer() {
		return $this->getBufferingTermLookup();
	}

	/**
	 * @return TermLookup
	 */
	public function getTermLookup() {
		return $this->getBufferingTermLookup();
	}

	/**
	 * @return BufferingTermLookup
	 */
	public function getBufferingTermLookup() {
		if ( !$this->termLookup ) {
			$this->termLookup = new BufferingTermLookup(
				$this->getStore()->getTermIndex(),
				1000 // @todo: configure buffer size
			);
		}

		return $this->termLookup;
	}

	/**
	 * @since 0.4
	 *
	 * @return PropertyDataTypeLookup
	 */
	public function getPropertyDataTypeLookup() {
		if ( $this->propertyDataTypeLookup === null ) {
			$infoStore = $this->getStore()->getPropertyInfoStore();
			$retrievingLookup = new EntityRetrievingDataTypeLookup( $this->getEntityLookup() );
			$this->propertyDataTypeLookup = new PropertyInfoDataTypeLookup( $infoStore, $retrievingLookup );
		}

		return $this->propertyDataTypeLookup;
	}

	/**
	 * @since 0.4
	 *
	 * @return StringNormalizer
	 */
	public function getStringNormalizer() {
		if ( $this->stringNormalizer === null ) {
			$this->stringNormalizer = new StringNormalizer();
		}

		return $this->stringNormalizer;
	}

	/**
	 * @since 0.4
	 *
	 * @return RepoLinker
	 */
	public function newRepoLinker() {
		return new RepoLinker(
			$this->settings->getSetting( 'repoUrl' ),
			$this->settings->getSetting( 'repoArticlePath' ),
			$this->settings->getSetting( 'repoScriptPath' ),
			$this->settings->getSetting( 'repoNamespaces' )
		);
	}

	/**
	 * @since 0.4
	 *
	 * @return LanguageFallbackChainFactory
	 */
	public function getLanguageFallbackChainFactory() {
		if ( $this->languageFallbackChainFactory === null ) {
			$this->languageFallbackChainFactory = new LanguageFallbackChainFactory(
				defined( 'WB_EXPERIMENTAL_FEATURES' ) && WB_EXPERIMENTAL_FEATURES
			);
		}

		return $this->languageFallbackChainFactory;
	}

	/**
	 * Returns an instance of the default store.
	 *
	 * @since 0.1
	 *
	 * @throws Exception
	 * @return ClientStore
	 */
	public function getStore() {
		if ( $this->store === null ) {
			// NOTE: $repoDatabase is null per default, meaning no direct access to the repo's
			// database. If $repoDatabase is false, the local wiki IS the repository. Otherwise,
			// $repoDatabase needs to be a logical database name that LBFactory understands.
			$repoDatabase = $this->settings->getSetting( 'repoDatabase' );
			$this->store = new DirectSqlStore(
				$this->getEntityContentDataCodec(),
				$this->getEntityIdParser(),
				$repoDatabase,
				$this->contentLanguage->getCode()
			);
		}

		return $this->store;
	}

	/**
	 * Overrides the default store to be used in the client app context.
	 * This is intended for use by test cases.
	 *
	 * @param ClientStore|null $store
	 *
	 * @throws LogicException If MW_PHPUNIT_TEST is not defined, to avoid this
	 * method being abused in production code.
	 */
	public function overrideStore( ClientStore $store = null ) {
		if ( !defined( 'MW_PHPUNIT_TEST' ) ) {
			throw new LogicException( 'Overriding the store instance is only supported in test mode' );
		}

		$this->store = $store;
	}

	/**
	 * @since 0.4
	 *
	 * @return Language
	 */
	public function getContentLanguage() {
		return $this->contentLanguage;
	}

	/**
	 * @since 0.4
	 *
	 * @return SettingsArray
	 */
	public function getSettings() {
		return $this->settings;
	}

	/**
	 * Returns a new instance constructed from global settings.
	 * IMPORTANT: Use only when it is not feasible to inject an instance properly.
	 *
	 * @return WikibaseClient
	 */
	private static function newInstance() {
		global $wgContLang, $wgWBClientSettings, $wgWBClientDataTypes;

		$dataTypeDefinitions = $wgWBClientDataTypes;
		Hooks::run( 'WikibaseClientDataTypes', array( &$dataTypeDefinitions ) );

		return new self(
			new SettingsArray( $wgWBClientSettings ),
			$wgContLang,
			new DataTypeDefinitions( $dataTypeDefinitions )
		);
	}

	/**
	 * Returns the default instance constructed using newInstance().
	 * IMPORTANT: Use only when it is not feasible to inject an instance properly.
	 *
	 * @since 0.4
	 *
	 * @param string $reset Flag: Pass "reset" to reset the default instance
	 *
	 * @return WikibaseClient
	 */
	public static function getDefaultInstance( $reset = 'noreset' ) {
		static $instance = null;

		if ( $instance === null || $reset === 'reset' ) {
			$instance = self::newInstance();
		}

		return $instance;
	}

	/**
	 * Returns the this client wiki's site object.
	 *
	 * This is taken from the siteGlobalID setting, which defaults
	 * to the wiki's database name.
	 *
	 * If the configured site ID is not found in the sites table, a
	 * new Site object is constructed from the configured ID.
	 *
	 * @throws MWException
	 * @return Site
	 */
	public function getSite() {
		if ( $this->site === null ) {
			$globalId = $this->settings->getSetting( 'siteGlobalID' );
			$localId = $this->settings->getSetting( 'siteLocalID' );

			$this->site = $this->getSiteStore()->getSite( $globalId );

			if ( !$this->site ) {
				wfDebugLog( __CLASS__, __FUNCTION__ . ": Unable to resolve site ID '{$globalId}'!" );

				$this->site = new MediaWikiSite();
				$this->site->setGlobalId( $globalId );
				$this->site->addLocalId( Site::ID_INTERWIKI, $localId );
				$this->site->addLocalId( Site::ID_EQUIVALENT, $localId );
			}

			if ( !in_array( $localId, $this->site->getLocalIds() ) ) {
				wfDebugLog( __CLASS__, __FUNCTION__
					. ": The configured local id $localId does not match any local ID of site $globalId: "
					. var_export( $this->site->getLocalIds(), true ) );
			}
		}

		return $this->site;
	}

	/**
	 * Returns the site group ID for the group to be used for language links.
	 * This is typically the group the client wiki itself belongs to, but
	 * can be configured to be otherwise using the languageLinkSiteGroup setting.
	 *
	 * @return string
	 */
	public function getLangLinkSiteGroup() {
		$group = $this->settings->getSetting( 'languageLinkSiteGroup' );

		if ( $group === null ) {
			$group = $this->getSiteGroup();
		}

		return $group;
	}

	/**
	 * Gets the site group ID from setting, which if not set then does
	 * lookup in site store.
	 *
	 * @return string
	 */
	private function newSiteGroup() {
		$siteGroup = $this->settings->getSetting( 'siteGroup' );

		if ( !$siteGroup ) {
			$siteId = $this->settings->getSetting( 'siteGlobalID' );

			$site = $this->getSiteStore()->getSite( $siteId );

			if ( !$site ) {
				wfWarn( 'Cannot find site ' . $siteId . ' in sites table' );

				return true;
			}

			$siteGroup = $site->getGroup();
		}

		return $siteGroup;
	}

	/**
	 * Get site group ID
	 *
	 * @return string
	 */
	public function getSiteGroup() {
		if ( $this->siteGroup === null ) {
			$this->siteGroup = $this->newSiteGroup();
		}

		return $this->siteGroup;
	}

	/**
	 * Returns a OutputFormatSnakFormatterFactory the provides SnakFormatters
	 * for different output formats.
	 *
	 * @return OutputFormatSnakFormatterFactory
	 */
	public function getSnakFormatterFactory() {
		if ( $this->snakFormatterFactory === null ) {
			$this->snakFormatterFactory = $this->newSnakFormatterFactory();
		}

		return $this->snakFormatterFactory;
	}

	/**
	 * @return OutputFormatSnakFormatterFactory
	 */
	private function newSnakFormatterFactory() {
		$valueFormatterBuilders = $this->newWikibaseValueFormatterBuilders();

		$builders = new WikibaseSnakFormatterBuilders(
			$valueFormatterBuilders,
			$this->getPropertyDataTypeLookup(),
			$this->getDataTypeFactory()
		);

		return new OutputFormatSnakFormatterFactory( $builders->getSnakFormatterBuildersForFormats() );
	}

	/**
	 * Returns a OutputFormatValueFormatterFactory the provides ValueFormatters
	 * for different output formats.
	 *
	 * @return OutputFormatValueFormatterFactory
	 */
	public function getValueFormatterFactory() {
		if ( $this->valueFormatterFactory === null ) {
			$this->valueFormatterFactory = $this->newValueFormatterFactory();
		}

		return $this->valueFormatterFactory;
	}

	/**
	 * @return OutputFormatValueFormatterFactory
	 */
	private function newValueFormatterFactory() {
		$builders = $this->newWikibaseValueFormatterBuilders();
		return new OutputFormatValueFormatterFactory( $builders->getValueFormatterBuildersForFormats() );
	}

	private function newWikibaseValueFormatterBuilders() {
		return new WikibaseValueFormatterBuilders(
			$this->contentLanguage,
			new FormatterLabelDescriptionLookupFactory( $this->getTermLookup() ),
			new LanguageNameLookup(),
			$this->getRepoEntityUriParser()
		);
	}

	/**
	 * @return SuffixEntityIdParser
	 */
	private function getRepoEntityUriParser() {
		return new SuffixEntityIdParser(
			$this->getSettings()->getSetting( 'repoConceptBaseUri' ),
			$this->getEntityIdParser()
		);
	}

	/**
	 * @return NamespaceChecker
	 */
	public function getNamespaceChecker() {
		if ( $this->namespaceChecker === null ) {
			$this->namespaceChecker = new NamespaceChecker(
				$this->settings->getSetting( 'excludeNamespaces' ),
				$this->settings->getSetting( 'namespaces' )
			);
		}

		return $this->namespaceChecker;
	}

	/**
	 * @return LangLinkHandler
	 */
	public function getLangLinkHandler() {
		if ( $this->langLinkHandler === null ) {
			$this->langLinkHandler = new LangLinkHandler(
				$this->getLanguageLinkBadgeDisplay(),
				$this->getNamespaceChecker(),
				$this->getStore()->getSiteLinkLookup(),
				$this->getStore()->getEntityLookup(),
				$this->getSiteStore(),
				$this->settings->getSetting( 'siteGlobalID' ),
				$this->getLangLinkSiteGroup()
			);
		}

		return $this->langLinkHandler;
	}

	/**
	 * @return ParserOutputDataUpdater
	 */
	public function getParserOutputDataUpdater() {
		if ( $this->parserOutputDataUpdater === null ) {
			$this->parserOutputDataUpdater = new ParserOutputDataUpdater(
				$this->getOtherProjectsSidebarGeneratorFactory(),
				$this->getStore()->getSiteLinkLookup(),
				$this->getStore()->getEntityLookup(),
				$this->settings->getSetting( 'siteGlobalID' )
			);
		}

		return $this->parserOutputDataUpdater;
	}

	/**
	 * @return LanguageLinkBadgeDisplay
	 */
	public function getLanguageLinkBadgeDisplay() {
		global $wgLang;
		StubObject::unstub( $wgLang );

		$badgeClassNames = $this->settings->getSetting( 'badgeClassNames' );

		return new LanguageLinkBadgeDisplay(
			$this->getEntityLookup(),
			is_array( $badgeClassNames ) ? $badgeClassNames : array(),
			$wgLang
		);
	}

	/**
	 * @since 0.5
	 *
	 * @return SiteStore
	 */
	public function getSiteStore() {
		if ( $this->siteStore === null ) {
			$this->siteStore = SiteSQLStore::newInstance();
		}

		return $this->siteStore;
	}

	/**
	 * @return EntityFactory
	 */
	public function getEntityFactory() {
		$entityClasses = array(
			Item::ENTITY_TYPE => 'Wikibase\DataModel\Entity\Item',
			Property::ENTITY_TYPE => 'Wikibase\DataModel\Entity\Property',
		);

		//TODO: provide a hook or registry for adding more.

		return new EntityFactory( $entityClasses );
	}

	/**
	 * @return EntityContentDataCodec
	 */
	public function getEntityContentDataCodec() {
		// Serialization is not supported on the client, since the client never stores entities.
		$forbiddenSerializer = new ForbiddenSerializer( 'Entity serialization is not supported on the client!' );

		return new EntityContentDataCodec(
			$this->getEntityIdParser(),
			$forbiddenSerializer,
			$this->getInternalEntityDeserializer()
		);
	}

	/**
	 * @return Deserializer
	 */
	public function getInternalEntityDeserializer() {
		return $this->getInternalDeserializerFactory()->newEntityDeserializer();
	}

	/**
	 * @return Deserializer
	 */
	public function getInternalStatementDeserializer() {
		return $this->getInternalDeserializerFactory()->newStatementDeserializer();
	}

	/**
	 * @return InternalDeserializerFactory
	 */
	private function getInternalDeserializerFactory() {
		return new InternalDeserializerFactory(
			$this->getDataValueDeserializer(),
			$this->getEntityIdParser()
		);
	}

	/**
	 * @return DataValueDeserializer
	 */
	private function getDataValueDeserializer() {
		return new DataValueDeserializer( array(
			'boolean' => 'DataValues\BooleanValue',
			'number' => 'DataValues\NumberValue',
			'string' => 'DataValues\StringValue',
			'unknown' => 'DataValues\UnknownValue',
			'globecoordinate' => 'DataValues\Geo\Values\GlobeCoordinateValue',
			'monolingualtext' => 'DataValues\MonolingualTextValue',
			'multilingualtext' => 'DataValues\MultilingualTextValue',
			'quantity' => 'DataValues\QuantityValue',
			'time' => 'DataValues\TimeValue',
			'wikibase-entityid' => 'Wikibase\DataModel\Entity\EntityIdValue',
		) );
	}

	/**
	 * @since 0.5
	 *
	 * @return OtherProjectsSidebarGeneratorFactory
	 */
	public function getOtherProjectsSidebarGeneratorFactory() {
		return new OtherProjectsSidebarGeneratorFactory(
			$this->settings,
			$this->getStore()->getSiteLinkLookup(),
			$this->getSiteStore()
		);
	}

	/**
	 * @since 0.5
	 *
	 * @return EntityChangeFactory
	 */
	public function getEntityChangeFactory() {
		//TODO: take this from a setting or registry.
		$changeClasses = array(
			Item::ENTITY_TYPE => 'Wikibase\ItemChange',
			// Other types of entities will use EntityChange
		);

		return new EntityChangeFactory(
			$this->getStore()->newChangesTable(),
			$this->getEntityFactory(),
			new EntityDiffer(),
			$changeClasses
		);
	}

	/**
	 * @return ParserFunctionRegistrant
	 */
	public function getParserFunctionRegistrant() {
		return new ParserFunctionRegistrant(
			$this->settings->getSetting( 'allowDataTransclusion' )
		);
	}

	/**
	 * @return PropertyClaimsRendererFactory
	 */
	private function getPropertyClaimsRendererFactory() {
		$entityLookup = $this->getRestrictedEntityLookup();

		$propertyIdResolver = new PropertyIdResolver(
			$entityLookup,
			$this->getStore()->getPropertyLabelResolver()
		);

		return new PropertyClaimsRendererFactory(
			$propertyIdResolver,
			new SnaksFinder(),
			$this->getLanguageFallbackChainFactory(),
			$this->getSnakFormatterFactory(),
			$entityLookup
		);
	}

	/**
	 * @return Runner
	 */
	public function getPropertyParserFunctionRunner() {
		return new Runner(
			$this->getPropertyClaimsRendererFactory(),
			$this->getStore()->getSiteLinkLookup(),
			$this->getEntityIdParser(),
			$this->getRestrictedEntityLookup(),
			$this->settings->getSetting( 'siteGlobalID' ),
			$this->settings->getSetting( 'allowArbitraryDataAccess' )
		);
	}

	/**
	 * @return OtherProjectsSitesProvider
	 */
	public function getOtherProjectsSitesProvider() {
		$otherProjectsSitesProvider = new OtherProjectsSitesGenerator(
			$this->getSiteStore(),
			$this->settings->getSetting( 'siteGlobalID' ),
			$this->settings->getSetting( 'specialSiteLinkGroups' )
		);

		return new CachingOtherProjectsSitesProvider(
			$otherProjectsSitesProvider,
			wfGetMainCache(),
			60 * 60
		);
	}

	/**
	 * @return AffectedPagesFinder
	 */
	public function getAffectedPagesFinder() {
		return new AffectedPagesFinder(
			$this->getStore()->getUsageLookup(),
			$this->getNamespaceChecker(),
			new TitleFactory(),
			$this->settings->getSetting( 'siteGlobalID' ),
			$this->getContentLanguage()->getCode()
		);
	}

	/**
	 * @return ChangeHandler
	 */
	public function getChangeHandler() {
		$siteId = $this->getSite()->getGlobalId();

		return new ChangeHandler(
			$this->getAffectedPagesFinder(),
			new TitleFactory(),
			new WikiPageUpdater(),
			new ChangeRunCoalescer(
				$this->getStore()->getEntityRevisionLookup(),
				$this->getEntityChangeFactory(),
				$siteId
			),
			$siteId,
			$this->settings->getSetting( 'injectRecentChanges' )
		);
	}

	/**
	 * Get a ContentLanguages object holding the languages available for labels, descriptions and aliases.
	 *
	 * @return WikibaseContentLanguages
	 */
	public function getTermsLanguages() {
		return new WikibaseContentLanguages();
	}

	/**
	 * @return DeserializerFactory
	 */
	public function getDeserializerFactory() {
		return new DeserializerFactory(
			$this->getDataValueDeserializer(),
			$this->getEntityIdParser()
		);
	}

	/**
	 * @return RestrictedEntityLookup
	 */
	public function getRestrictedEntityLookup() {
		if ( $this->restrictedEntityLookup === null ) {
			$this->restrictedEntityLookup = new RestrictedEntityLookup(
				$this->getEntityLookup(),
				$this->settings->getSetting( 'entityAccessLimit' )
			);
		}

		return $this->restrictedEntityLookup;
	}

}
