<?php

namespace Wikibase\Client;

use CentralIdLookup;
use DataValues\Deserializers\DataValueDeserializer;
use DataValues\Geo\Values\GlobeCoordinateValue;
use DataValues\MonolingualTextValue;
use DataValues\QuantityValue;
use DataValues\StringValue;
use DataValues\TimeValue;
use DataValues\UnknownValue;
use Deserializers\Deserializer;
use Deserializers\DispatchableDeserializer;
use Deserializers\DispatchingDeserializer;
use ExtensionRegistry;
use ExternalUserNames;
use Hooks;
use Http;
use JobQueueGroup;
use Language;
use LogicException;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWikiSite;
use MWException;
use ObjectCache;
use Psr\Log\LoggerInterface;
use Serializers\Serializer;
use Site;
use SiteLookup;
use StubObject;
use Title;
use TitleFactory;
use Wikibase\Client\Changes\AffectedPagesFinder;
use Wikibase\Client\Changes\ChangeHandler;
use Wikibase\Client\Changes\ChangeRunCoalescer;
use Wikibase\Client\Changes\WikiPageUpdater;
use Wikibase\Client\DataAccess\ClientSiteLinkTitleLookup;
use Wikibase\Client\DataAccess\DataAccessSnakFormatterFactory;
use Wikibase\Client\DataAccess\ParserFunctions\Runner;
use Wikibase\Client\DataAccess\ParserFunctions\StatementGroupRendererFactory;
use Wikibase\Client\DataAccess\ReferenceFormatterFactory;
use Wikibase\Client\DataAccess\SnaksFinder;
use Wikibase\Client\Hooks\LangLinkHandlerFactory;
use Wikibase\Client\Hooks\LanguageLinkBadgeDisplay;
use Wikibase\Client\Hooks\OtherProjectsSidebarGeneratorFactory;
use Wikibase\Client\Hooks\SidebarLinkBadgeDisplay;
use Wikibase\Client\ParserOutput\ClientParserOutputDataUpdater;
use Wikibase\Client\RecentChanges\RecentChangeFactory;
use Wikibase\Client\RecentChanges\SiteLinkCommentCreator;
use Wikibase\Client\Store\ClientStore;
use Wikibase\Client\Store\DescriptionLookup;
use Wikibase\Client\Store\Sql\DirectSqlStore;
use Wikibase\Client\Store\Sql\PagePropsEntityIdLookup;
use Wikibase\Client\Usage\EntityUsageFactory;
use Wikibase\DataAccess\AliasTermBuffer;
use Wikibase\DataAccess\ByTypeDispatchingEntityIdLookup;
use Wikibase\DataAccess\DataAccessSettings;
use Wikibase\DataAccess\EntitySource;
use Wikibase\DataAccess\EntitySourceDefinitions;
use Wikibase\DataAccess\EntitySourceDefinitionsConfigParser;
use Wikibase\DataAccess\GenericServices;
use Wikibase\DataAccess\MultipleEntitySourceServices;
use Wikibase\DataAccess\PrefetchingTermLookup;
use Wikibase\DataAccess\SingleEntitySourceServices;
use Wikibase\DataAccess\WikibaseServices;
use Wikibase\DataModel\DeserializerFactory;
use Wikibase\DataModel\Entity\DispatchingEntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\SerializerFactory;
use Wikibase\DataModel\Services\Diff\EntityDiffer;
use Wikibase\DataModel\Services\EntityId\EntityIdComposer;
use Wikibase\DataModel\Services\EntityId\SuffixEntityIdParser;
use Wikibase\DataModel\Services\Lookup\DisabledEntityTypesEntityLookup;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\EntityRetrievingDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\RestrictedEntityLookup;
use Wikibase\DataModel\Services\Lookup\TermLookup;
use Wikibase\DataModel\Services\Term\PropertyLabelResolver;
use Wikibase\DataModel\Services\Term\TermBuffer;
use Wikibase\InternalSerialization\DeserializerFactory as InternalDeserializerFactory;
use Wikibase\Lib\Changes\EntityChange;
use Wikibase\Lib\Changes\EntityChangeFactory;
use Wikibase\Lib\Changes\ItemChange;
use Wikibase\Lib\ContentLanguages;
use Wikibase\Lib\DataTypeDefinitions;
use Wikibase\Lib\DataTypeFactory;
use Wikibase\Lib\EntityTypeDefinitions;
use Wikibase\Lib\Formatters\CachingKartographerEmbeddingHandler;
use Wikibase\Lib\Formatters\FormatterLabelDescriptionLookupFactory;
use Wikibase\Lib\Formatters\OutputFormatSnakFormatterFactory;
use Wikibase\Lib\Formatters\OutputFormatValueFormatterFactory;
use Wikibase\Lib\Formatters\Reference\WellKnownReferenceProperties;
use Wikibase\Lib\Formatters\WikibaseSnakFormatterBuilders;
use Wikibase\Lib\Formatters\WikibaseValueFormatterBuilders;
use Wikibase\Lib\Interactors\TermSearchInteractor;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\Lib\PropertyInfoDataTypeLookup;
use Wikibase\Lib\SettingsArray;
use Wikibase\Lib\Store\CachingPropertyOrderProvider;
use Wikibase\Lib\Store\EntityIdLookup;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Lib\Store\FallbackPropertyOrderProvider;
use Wikibase\Lib\Store\HttpUrlPropertyOrderProvider;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookupFactory;
use Wikibase\Lib\Store\PropertyOrderProvider;
use Wikibase\Lib\Store\Sql\Terms\CachedDatabasePropertyLabelResolver;
use Wikibase\Lib\Store\Sql\Terms\DatabaseTermInLangIdsResolver;
use Wikibase\Lib\Store\Sql\Terms\DatabaseTypeIdsStore;
use Wikibase\Lib\Store\TitleLookupBasedEntityExistenceChecker;
use Wikibase\Lib\Store\TitleLookupBasedEntityRedirectChecker;
use Wikibase\Lib\Store\TitleLookupBasedEntityTitleTextLookup;
use Wikibase\Lib\Store\TitleLookupBasedEntityUrlLookup;
use Wikibase\Lib\Store\WikiPagePropertyOrderProvider;
use Wikibase\Lib\StringNormalizer;
use Wikibase\Lib\TermFallbackCache\TermFallbackCacheFacade;
use Wikibase\Lib\TermFallbackCache\TermFallbackCacheServiceFactory;
use Wikibase\Lib\TermFallbackCacheFactory;
use Wikibase\Lib\TermLanguageFallbackChain;
use Wikibase\Lib\WikibaseContentLanguages;
use Wikibase\Lib\WikibaseSettings;

/**
 * Top level factory for the WikibaseClient extension.
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 */
final class WikibaseClient {

	/**
	 * @warning only for use in getDefaultInstance()!
	 * @var WikibaseClient
	 */
	private static $defaultInstance = null;

	/**
	 * @warning only for use in getDefaultSnakFormatterBuilders()!
	 * @var WikibaseSnakFormatterBuilders
	 */
	private static $defaultSnakFormatterBuilders = null;

	/**
	 * @var SettingsArray
	 */
	private $settings;

	/**
	 * @var SiteLookup
	 */
	private $siteLookup;

	/**
	 * @var WikibaseServices
	 */
	private $wikibaseServices;

	/**
	 * @var PropertyDataTypeLookup|null
	 */
	private $propertyDataTypeLookup = null;

	/**
	 * @var DataTypeFactory|null
	 */
	private $dataTypeFactory = null;

	/**
	 * @var Deserializer|null
	 */
	private $entityDeserializer = null;

	/**
	 * @var EntityIdParser|null
	 */
	private $entityIdParser = null;

	/**
	 * @var EntityIdComposer|null
	 */
	private $entityIdComposer = null;

	/**
	 * @var EntityIdLookup|null
	 */
	private $entityIdLookup = null;

	/**
	 * @var ClientStore|null
	 */
	private $store = null;

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
	 * @var ClientParserOutputDataUpdater|null
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
	 * @var EntityTypeDefinitions
	 */
	private $entityTypeDefinitions;

	/**
	 * @var TermLookup|null
	 */
	private $termLookup = null;

	/**
	 * @var TermBuffer|null
	 */
	private $termBuffer = null;

	/**
	 * @var PrefetchingTermLookup|null
	 */
	private $prefetchingTermLookup = null;

	/**
	 * @var PropertyOrderProvider|null
	 */
	private $propertyOrderProvider = null;

	/**
	 * @var SidebarLinkBadgeDisplay|null
	 */
	private $sidebarLinkBadgeDisplay = null;

	/**
	 * @var WikibaseValueFormatterBuilders|null
	 */
	private $valueFormatterBuilders = null;

	/**
	 * @var WikibaseContentLanguages|null
	 */
	private $wikibaseContentLanguages = null;

	/**
	 * @var EntitySourceDefinitions
	 */
	private $entitySourceDefinitions;

	/** @var DescriptionLookup|null */
	private $descriptionLookup = null;

	/** @var PropertyLabelResolver|null */
	private $propertyLabelResolver = null;

	/** @var ReferenceFormatterFactory|null */
	private $referenceFormatterFactory = null;

	/** @var TermFallbackCacheFactory|null */
	private $termFallbackCacheFactory = null;

	/**
	 * @warning This is for use with bootstrap code in WikibaseClient.datatypes.php only!
	 * Program logic should use WikibaseClient::getSnakFormatterFactory() instead!
	 *
	 * @return WikibaseValueFormatterBuilders
	 */
	public static function getDefaultValueFormatterBuilders() {
		global $wgThumbLimits;
		return self::getDefaultInstance()->newWikibaseValueFormatterBuilders( $wgThumbLimits );
	}

	/**
	 * Returns a low level factory object for creating formatters for well known data types.
	 *
	 * @warning This is for use with getDefaultValueFormatterBuilders() during bootstrap only!
	 * Program logic should use WikibaseClient::getSnakFormatterFactory() instead!
	 *
	 * @param array $thumbLimits
	 *
	 * @return WikibaseValueFormatterBuilders
	 */
	private function newWikibaseValueFormatterBuilders( array $thumbLimits ) {
		if ( $this->valueFormatterBuilders === null ) {
			$entityTitleLookup = new ClientSiteLinkTitleLookup(
				$this->getStore()->getSiteLinkLookup(),
				$this->settings->getSetting( 'siteGlobalID' )
			);

			$services = MediaWikiServices::getInstance();

			$kartographerEmbeddingHandler = null;
			if ( $this->useKartographerGlobeCoordinateFormatter() ) {
				$kartographerEmbeddingHandler = new CachingKartographerEmbeddingHandler(
					$services->getParserFactory()->create()
				);
			}

			$this->valueFormatterBuilders = new WikibaseValueFormatterBuilders(
				new FormatterLabelDescriptionLookupFactory( $this->getTermLookup() ),
				new LanguageNameLookup( $this->getUserLanguage()->getCode() ),
				$this->getRepoItemUriParser(),
				$this->settings->getSetting( 'geoShapeStorageBaseUrl' ),
				$this->settings->getSetting( 'tabularDataStorageBaseUrl' ),
				$this->getTermFallbackCache(),
				$this->settings->getSetting( 'sharedCacheDuration' ),
				$this->getEntityLookup(),
				$this->getStore()->getEntityRevisionLookup(),
				$this->settings->getSetting( 'entitySchemaNamespace' ),
				new TitleLookupBasedEntityExistenceChecker(
					$entityTitleLookup,
					$services->getLinkBatchFactory()
				),
				new TitleLookupBasedEntityTitleTextLookup( $entityTitleLookup ),
				new TitleLookupBasedEntityUrlLookup( $entityTitleLookup ),
				new TitleLookupBasedEntityRedirectChecker( $entityTitleLookup ),
				$entityTitleLookup,
				$kartographerEmbeddingHandler,
				$this->settings->getSetting( 'useKartographerMaplinkInWikitext' ),
				$thumbLimits
			);
		}

		return $this->valueFormatterBuilders;
	}

	/**
	 * @return bool
	 */
	private function useKartographerGlobeCoordinateFormatter() {
		// FIXME: remove the global out of here
		global $wgKartographerEnableMapFrame;

		return $this->settings->getSetting( 'useKartographerGlobeCoordinateFormatter' ) &&
			ExtensionRegistry::getInstance()->isLoaded( 'Kartographer' ) &&
			isset( $wgKartographerEnableMapFrame ) &&
			$wgKartographerEnableMapFrame;
	}

	/**
	 * @warning This is for use with bootstrap code in WikibaseClient.datatypes.php only!
	 * Program logic should use WikibaseClient::getSnakFormatterFactory() instead!
	 *
	 * @return WikibaseSnakFormatterBuilders
	 */
	public static function getDefaultSnakFormatterBuilders() {
		if ( self::$defaultSnakFormatterBuilders === null ) {
			self::$defaultSnakFormatterBuilders = self::getDefaultInstance()->newWikibaseSnakFormatterBuilders(
				self::getDefaultValueFormatterBuilders()
			);
		}

		return self::$defaultSnakFormatterBuilders;
	}

	/**
	 * Returns a low level factory object for creating formatters for well known data types.
	 *
	 * @warning This is for use with getDefaultValueFormatterBuilders() during bootstrap only!
	 * Program logic should use WikibaseClient::getSnakFormatterFactory() instead!
	 *
	 * @param WikibaseValueFormatterBuilders $valueFormatterBuilders
	 *
	 * @return WikibaseSnakFormatterBuilders
	 */
	private function newWikibaseSnakFormatterBuilders( WikibaseValueFormatterBuilders $valueFormatterBuilders ) {
		return new WikibaseSnakFormatterBuilders(
			$valueFormatterBuilders,
			$this->getStore()->getPropertyInfoLookup(),
			$this->getPropertyDataTypeLookup(),
			$this->getDataTypeFactory()
		);
	}

	public function __construct(
		SettingsArray $settings,
		DataTypeDefinitions $dataTypeDefinitions,
		EntityTypeDefinitions $entityTypeDefinitions,
		SiteLookup $siteLookup,
		EntitySourceDefinitions $entitySourceDefinitions
	) {
		$this->settings = $settings;
		$this->dataTypeDefinitions = $dataTypeDefinitions;
		$this->entityTypeDefinitions = $entityTypeDefinitions;
		$this->siteLookup = $siteLookup;
		$this->entitySourceDefinitions = $entitySourceDefinitions;
	}

	public function getDataTypeFactory(): DataTypeFactory {
		if ( $this->dataTypeFactory === null ) {
			$this->dataTypeFactory = new DataTypeFactory( $this->dataTypeDefinitions->getValueTypes() );
		}

		return $this->dataTypeFactory;
	}

	public function getEntityIdParser(): EntityIdParser {
		if ( $this->entityIdParser === null ) {
			$this->entityIdParser = new DispatchingEntityIdParser(
				$this->entityTypeDefinitions->getEntityIdBuilders()
			);
		}

		return $this->entityIdParser;
	}

	public function getEntityIdComposer(): EntityIdComposer {
		if ( $this->entityIdComposer === null ) {
			$this->entityIdComposer = new EntityIdComposer(
				$this->entityTypeDefinitions->get( EntityTypeDefinitions::ENTITY_ID_COMPOSER_CALLBACK )
			);
		}

		return $this->entityIdComposer;
	}

	public function getWikibaseServices(): WikibaseServices {
		if ( $this->wikibaseServices === null ) {
			$this->wikibaseServices = $this->newEntitySourceWikibaseServices();
		}

		return $this->wikibaseServices;
	}

	private function newEntitySourceWikibaseServices() {
		$nameTableStoreFactory = MediaWikiServices::getInstance()->getNameTableStoreFactory();
		$genericServices = new GenericServices( $this->entityTypeDefinitions );

		$singleSourceServices = [];
		foreach ( $this->entitySourceDefinitions->getSources() as $source ) {
			// TODO: extract
			$singleSourceServices[$source->getSourceName()] = new SingleEntitySourceServices(
				$genericServices,
				$this->getEntityIdParser(),
				$this->getEntityIdComposer(),
				$this->getDataValueDeserializer(),
				$nameTableStoreFactory->getSlotRoles( $source->getDatabaseName() ),
				$this->getDataAccessSettings(),
				$source,
				$this->entityTypeDefinitions->get( EntityTypeDefinitions::DESERIALIZER_FACTORY_CALLBACK ),
				$this->entityTypeDefinitions->get( EntityTypeDefinitions::ENTITY_METADATA_ACCESSOR_CALLBACK ),
				$this->entityTypeDefinitions->get( EntityTypeDefinitions::PREFETCHING_TERM_LOOKUP_CALLBACK ),
				$this->entityTypeDefinitions->get( EntityTypeDefinitions::ENTITY_REVISION_LOOKUP_FACTORY_CALLBACK )
			);
		}

		return new MultipleEntitySourceServices( $this->entitySourceDefinitions, $genericServices, $singleSourceServices );
	}

	private function getDataAccessSettings() {
		return new DataAccessSettings(
			$this->settings->getSetting( 'maxSerializedEntitySize' )
		);
	}

	/**
	 * @return EntityLookup
	 */
	private function getEntityLookup() {
		return $this->getStore()->getEntityLookup();
	}

	private static function getDefaultDataTypes() {
		$baseDataTypes = require __DIR__ . '/../../lib/WikibaseLib.datatypes.php';
		$clientDataTypes = require __DIR__ . '/../WikibaseClient.datatypes.php';

		return array_merge_recursive( $baseDataTypes, $clientDataTypes );
	}

	/**
	 * @return array[]
	 */
	private static function getDefaultEntityTypes() {
		return require __DIR__ . '/../../lib/WikibaseLib.entitytypes.php';
	}

	/**
	 * @return TermBuffer|AliasTermBuffer
	 */
	public function getTermBuffer() {
		if ( !$this->termBuffer ) {
			$this->termBuffer = $this->getPrefetchingTermLookup();
		}

		return $this->termBuffer;
	}

	public function getTermLookup(): TermLookup {
		if ( !$this->termLookup ) {
			$this->termLookup = $this->getPrefetchingTermLookup();
		}

		return $this->termLookup;
	}

	private function getPrefetchingTermLookup(): PrefetchingTermLookup {
		if ( !$this->prefetchingTermLookup ) {
			$this->prefetchingTermLookup = $this->getWikibaseServices()->getPrefetchingTermLookup();
		}

		return $this->prefetchingTermLookup;
	}

	/**
	 * XXX: This is not used by client itself, but is used by ArticlePlaceholder!
	 */
	public function newTermSearchInteractor( string $displayLanguageCode ): TermSearchInteractor {
		return $this->getWikibaseServices()->getTermSearchInteractorFactory()
			->newInteractor( $displayLanguageCode );
	}

	public function getPropertyDataTypeLookup(): PropertyDataTypeLookup {
		if ( $this->propertyDataTypeLookup === null ) {
			$infoLookup = $this->getStore()->getPropertyInfoLookup();
			$retrievingLookup = new EntityRetrievingDataTypeLookup( $this->getEntityLookup() );
			$this->propertyDataTypeLookup = new PropertyInfoDataTypeLookup(
				$infoLookup,
				$this->getLogger(),
				$retrievingLookup
			);
		}

		return $this->propertyDataTypeLookup;
	}

	public function getStringNormalizer(): StringNormalizer {
		return $this->getWikibaseServices()->getStringNormalizer();
	}

	public function newRepoLinker(): RepoLinker {
		return new RepoLinker(
			$this->entitySourceDefinitions,
			$this->settings->getSetting( 'repoUrl' ),
			$this->settings->getSetting( 'repoArticlePath' ),
			$this->settings->getSetting( 'repoScriptPath' )
		);
	}

	public function getLanguageFallbackChainFactory(): LanguageFallbackChainFactory {
		return $this->getWikibaseServices()->getLanguageFallbackChainFactory();
	}

	public function getLanguageFallbackLabelDescriptionLookupFactory(): LanguageFallbackLabelDescriptionLookupFactory {
		return new LanguageFallbackLabelDescriptionLookupFactory(
			$this->getLanguageFallbackChainFactory(),
			$this->getTermLookup(),
			$this->getTermBuffer()
		);
	}

	/**
	 * Returns an instance of the default store.
	 */
	public function getStore(): ClientStore {
		if ( $this->store === null ) {
			$this->store = new DirectSqlStore(
				$this->getEntityChangeFactory(),
				$this->getEntityIdParser(),
				$this->getEntityIdComposer(),
				$this->getEntityIdLookup(),
				$this->getEntityNamespaceLookup(),
				$this->getWikibaseServices(),
				$this->getSettings(),
				$this->getDatabaseDomainNameOfLocalRepo(),
				$this->getContentLanguage()->getCode()
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
	 * Overrides the TermLookup to be used.
	 * This is intended for use by test cases.
	 *
	 * @param TermLookup|null $lookup
	 *
	 * @throws LogicException If MW_PHPUNIT_TEST is not defined, to avoid this
	 * method being abused in production code.
	 */
	public function overrideTermLookup( TermLookup $lookup = null ) {
		if ( !defined( 'MW_PHPUNIT_TEST' ) ) {
			throw new LogicException( 'Overriding TermLookup is only supported in test mode' );
		}

		$this->termLookup = $lookup;
	}

	/**
	 * @throws MWException when called to early
	 */
	public function getContentLanguage(): Language {
		/**
		 * Before this constant is defined, custom config may not have been taken into account.
		 * So try not to allow code to use a language before that point.
		 * This code was explicitly mentioning the SetupAfterCache hook.
		 * With services, that hook won't be a problem anymore.
		 * So this check may well be unnecessary (but better safe than sorry).
		 */
		if ( !defined( 'MW_SERVICE_BOOTSTRAP_COMPLETE' ) ) {
			throw new MWException( 'Premature access to MediaWiki ContentLanguage!' );
		}

		return MediaWikiServices::getInstance()->getContentLanguage();
	}

	/**
	 * @throws MWException when called to early
	 */
	private function getUserLanguage(): Language {
		global $wgLang;

		// TODO: define a LanguageProvider service instead of using a global directly.
		// NOTE: we cannot inject $wgLang in the constructor, because it may still be null
		// when WikibaseClient is initialized. In particular, the language object may not yet
		// be there when the SetupAfterCache hook is run during bootstrapping.

		if ( !$wgLang ) {
			throw new MWException( 'Premature access: $wgLang is not yet initialized!' );
		}

		StubObject::unstub( $wgLang );
		return $wgLang;
	}

	public function getSettings(): SettingsArray {
		return $this->settings;
	}

	/**
	 * Returns a new instance constructed from global settings.
	 * IMPORTANT: Use only when it is not feasible to inject an instance properly.
	 *
	 * @throws MWException
	 * @return self
	 */
	private static function newInstance() {
		$dataTypeDefinitionsArray = self::getDefaultDataTypes();
		Hooks::run( 'WikibaseClientDataTypes', [ &$dataTypeDefinitionsArray ] );

		$entityTypeDefinitionsArray = self::getDefaultEntityTypes();
		Hooks::run( 'WikibaseClientEntityTypes', [ &$entityTypeDefinitionsArray ] );

		$settings = WikibaseSettings::getClientSettings();

		$dataTypeDefinitions = new DataTypeDefinitions(
			$dataTypeDefinitionsArray,
			$settings->getSetting( 'disabledDataTypes' )
		);
		$entityTypeDefinitions = new EntityTypeDefinitions( $entityTypeDefinitionsArray );

		return new self(
			$settings,
			$dataTypeDefinitions,
			$entityTypeDefinitions,
			MediaWikiServices::getInstance()->getSiteLookup(),
			self::getEntitySourceDefinitionsFromSettings( $settings, $entityTypeDefinitions )
		);
	}

	// TODO: current settings (especially (foreign) repositories blob) might be quite confusing
	// Having a "entitySources" or so setting might be better, and would also allow unifying
	// the way these are configured in Repo and in Client parts
	private static function getEntitySourceDefinitionsFromSettings(
		SettingsArray $settings,
		EntityTypeDefinitions $entityTypeDefinitions
	) {
		if ( $settings->hasSetting( 'entitySources' ) && !empty( $settings->getSetting( 'entitySources' ) ) ) {
			$configParser = new EntitySourceDefinitionsConfigParser();

			return $configParser->newDefinitionsFromConfigArray( $settings->getSetting( 'entitySources' ), $entityTypeDefinitions );
		}

		$parser = new EntitySourceDefinitionsLegacyClientSettingsParser();
		return $parser->newDefinitionsFromSettings( $settings, $entityTypeDefinitions );
	}

	/**
	 * IMPORTANT: Use only when it is not feasible to inject an instance properly.
	 *
	 * @param string $reset Flag: Pass "reset" to reset the default instance
	 *
	 * @return self
	 */
	public static function getDefaultInstance( $reset = 'noreset' ) {
		if ( $reset === 'reset' ) {
			self::$defaultInstance = null;
			self::$defaultSnakFormatterBuilders = null;
		}

		if ( self::$defaultInstance === null ) {
			self::$defaultInstance = self::newInstance();
		}

		return self::$defaultInstance;
	}

	public function getLogger(): LoggerInterface {
		return LoggerFactory::getInstance( 'Wikibase' );
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
	 */
	public function getSite(): Site {
		if ( $this->site === null ) {
			$globalId = $this->settings->getSetting( 'siteGlobalID' );
			$localId = $this->settings->getSetting( 'siteLocalID' );

			$this->site = $this->siteLookup->getSite( $globalId );

			// Todo inject me
			$logger = $this->getLogger();

			if ( !$this->site ) {
				$logger->debug(
					'{method}:  Unable to resolve site ID {globalId}!',
					[ 'method' => __METHOD__, 'globalId' => $globalId ]
				);

				$this->site = new MediaWikiSite();
				$this->site->setGlobalId( $globalId );
				$this->site->addLocalId( Site::ID_INTERWIKI, $localId );
				$this->site->addLocalId( Site::ID_EQUIVALENT, $localId );
			}

			if ( !in_array( $localId, $this->site->getLocalIds() ) ) {
				$logger->debug(
					'{method}: The configured local id {localId} does not match any local IDs of site {globalId}: {localIds}',
					[
						'method' => __METHOD__,
						'localId' => $localId,
						'globalId' => $globalId,
						'localIds' => json_encode( $this->site->getLocalIds() )
					]
				);
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

			$site = $this->siteLookup->getSite( $siteId );

			if ( !$site ) {
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
	 */
	private function getSnakFormatterFactory(): OutputFormatSnakFormatterFactory {
		if ( $this->snakFormatterFactory === null ) {
			$this->snakFormatterFactory = new OutputFormatSnakFormatterFactory(
				$this->dataTypeDefinitions->getSnakFormatterFactoryCallbacks(),
				$this->getValueFormatterFactory(),
				$this->getPropertyDataTypeLookup(),
				$this->getDataTypeFactory()
			);
		}

		return $this->snakFormatterFactory;
	}

	/**
	 * Returns a OutputFormatValueFormatterFactory the provides ValueFormatters
	 * for different output formats.
	 */
	private function getValueFormatterFactory(): OutputFormatValueFormatterFactory {
		if ( $this->valueFormatterFactory === null ) {
			$this->valueFormatterFactory = new OutputFormatValueFormatterFactory(
				$this->dataTypeDefinitions->getFormatterFactoryCallbacks( DataTypeDefinitions::PREFIXED_MODE ),
				$this->getContentLanguage(),
				$this->getLanguageFallbackChainFactory()
			);
		}

		return $this->valueFormatterFactory;
	}

	private function getRepoItemUriParser(): EntityIdParser {
		$itemConceptUriBase = $this->getItemSource()->getConceptBaseUri();

		return new SuffixEntityIdParser(
			$itemConceptUriBase,
			new ItemIdParser()
		);
	}

	public function getNamespaceChecker(): NamespaceChecker {
		if ( $this->namespaceChecker === null ) {
			$this->namespaceChecker = new NamespaceChecker(
				$this->settings->getSetting( 'excludeNamespaces' ),
				$this->settings->getSetting( 'namespaces' )
			);
		}

		return $this->namespaceChecker;
	}

	public function getLangLinkHandlerFactory(): LangLinkHandlerFactory {
		return new LangLinkHandlerFactory(
			$this->getLanguageLinkBadgeDisplay(),
			$this->getNamespaceChecker(),
			$this->getStore()->getSiteLinkLookup(),
			$this->getStore()->getEntityLookup(),
			$this->siteLookup,
			MediaWikiServices::getInstance()->getHookContainer(),
			$this->getLogger(),
			$this->settings->getSetting( 'siteGlobalID' ),
			$this->getLangLinkSiteGroup()
		);
	}

	public function getParserOutputDataUpdater(): ClientParserOutputDataUpdater {
		if ( $this->parserOutputDataUpdater === null ) {
			$this->parserOutputDataUpdater = new ClientParserOutputDataUpdater(
				$this->getOtherProjectsSidebarGeneratorFactory(),
				$this->getStore()->getSiteLinkLookup(),
				$this->getStore()->getEntityLookup(),
				new EntityUsageFactory( $this->getEntityIdParser() ),
				$this->settings->getSetting( 'siteGlobalID' ),
				$this->getLogger()
			);
		}

		return $this->parserOutputDataUpdater;
	}

	public function getSidebarLinkBadgeDisplay(): SidebarLinkBadgeDisplay {
		if ( $this->sidebarLinkBadgeDisplay === null ) {
			$labelDescriptionLookupFactory = $this->getLanguageFallbackLabelDescriptionLookupFactory();
			$badgeClassNames = $this->settings->getSetting( 'badgeClassNames' );
			$lang = $this->getUserLanguage();

			$this->sidebarLinkBadgeDisplay = new SidebarLinkBadgeDisplay(
				$labelDescriptionLookupFactory->newLabelDescriptionLookup( $lang ),
				is_array( $badgeClassNames ) ? $badgeClassNames : [],
				$lang
			);
		}

		return $this->sidebarLinkBadgeDisplay;
	}

	public function getLanguageLinkBadgeDisplay(): LanguageLinkBadgeDisplay {
		return new LanguageLinkBadgeDisplay(
			$this->getSidebarLinkBadgeDisplay()
		);
	}

	public function getBaseDataModelDeserializerFactory(): DeserializerFactory {
		return new DeserializerFactory(
			$this->getDataValueDeserializer(),
			$this->getEntityIdParser()
		);
	}

	private function getInternalFormatDeserializerFactory(): InternalDeserializerFactory {
		return new InternalDeserializerFactory(
			$this->getDataValueDeserializer(),
			$this->getEntityIdParser(),
			$this->getAllTypesEntityDeserializer()
		);
	}

	private function getAllTypesEntityDeserializer(): DispatchableDeserializer {
		if ( $this->entityDeserializer === null ) {
			$deserializerFactoryCallbacks = $this->getEntityDeserializerFactoryCallbacks();
			$baseDeserializerFactory = $this->getBaseDataModelDeserializerFactory();
			$deserializers = [];

			foreach ( $deserializerFactoryCallbacks as $callback ) {
				$deserializers[] = call_user_func( $callback, $baseDeserializerFactory );
			}

			$this->entityDeserializer = new DispatchingDeserializer( $deserializers );
		}

		return $this->entityDeserializer;
	}

	/**
	 * Returns a deserializer to deserialize statements in both current and legacy serialization.
	 */
	public function getInternalFormatStatementDeserializer(): Deserializer {
		return $this->getInternalFormatDeserializerFactory()->newStatementDeserializer();
	}

	/**
	 * @return callable[]
	 */
	public function getEntityDeserializerFactoryCallbacks() {
		return $this->entityTypeDefinitions->get( EntityTypeDefinitions::DESERIALIZER_FACTORY_CALLBACK );
	}

	/**
	 * @return string[]
	 */
	public function getLuaEntityModules() {
		return $this->entityTypeDefinitions->get( EntityTypeDefinitions::LUA_ENTITY_MODULE );
	}

	/**
	 * Returns a SerializerFactory creating serializers that generate the most compact serialization.
	 * A factory returned has knowledge about items, properties, and the elements they are made of,
	 * but no other entity types.
	 */
	public function getCompactBaseDataModelSerializerFactory(): SerializerFactory {
		return $this->getWikibaseServices()->getCompactBaseDataModelSerializerFactory();
	}

	/**
	 * Returns an entity serializer that generates the most compact serialization.
	 */
	public function getCompactEntitySerializer(): Serializer {
		return $this->getWikibaseServices()->getCompactEntitySerializer();
	}

	private function getDataValueDeserializer(): DataValueDeserializer {
		return new DataValueDeserializer( [
			'string' => StringValue::class,
			'unknown' => UnknownValue::class,
			'globecoordinate' => GlobeCoordinateValue::class,
			'monolingualtext' => MonolingualTextValue::class,
			'quantity' => QuantityValue::class,
			'time' => TimeValue::class,
			'wikibase-entityid' => function ( $value ) {
				return isset( $value['id'] )
					? new EntityIdValue( $this->getEntityIdParser()->parse( $value['id'] ) )
					: EntityIdValue::newFromArray( $value );
			},
		] );
	}

	public function getOtherProjectsSidebarGeneratorFactory(): OtherProjectsSidebarGeneratorFactory {
		return new OtherProjectsSidebarGeneratorFactory(
			$this->settings,
			$this->getStore()->getSiteLinkLookup(),
			$this->siteLookup,
			$this->getStore()->getEntityLookup(),
			$this->getSidebarLinkBadgeDisplay(),
			MediaWikiServices::getInstance()->getHookContainer(),
			$this->getLogger()
		);
	}

	public function getEntityChangeFactory(): EntityChangeFactory {
		//TODO: take this from a setting or registry.
		$changeClasses = [
			Item::ENTITY_TYPE => ItemChange::class,
			// Other types of entities will use EntityChange
		];

		return new EntityChangeFactory(
			$this->getEntityDiffer(),
			$this->getEntityIdParser(),
			$changeClasses,
			EntityChange::class,
			$this->getLogger()
		);
	}

	private function getEntityDiffer(): EntityDiffer {
		$entityDiffer = new EntityDiffer();
		foreach ( $this->entityTypeDefinitions->get( EntityTypeDefinitions::ENTITY_DIFFER_STRATEGY_BUILDER ) as $builder ) {
			$entityDiffer->registerEntityDifferStrategy( call_user_func( $builder ) );
		}
		return $entityDiffer;
	}

	private function getStatementGroupRendererFactory(): StatementGroupRendererFactory {
		return new StatementGroupRendererFactory(
			$this->getPropertyLabelResolver(),
			new SnaksFinder(),
			$this->getRestrictedEntityLookup(),
			$this->getDataAccessSnakFormatterFactory(),
			new EntityUsageFactory( $this->getEntityIdParser() ),
			$this->settings->getSetting( 'allowDataAccessInUserLanguage' )
		);
	}

	public function getDataAccessSnakFormatterFactory(): DataAccessSnakFormatterFactory {
		return new DataAccessSnakFormatterFactory(
			$this->getLanguageFallbackChainFactory(),
			$this->getSnakFormatterFactory(),
			$this->getPropertyDataTypeLookup(),
			$this->getRepoItemUriParser(),
			$this->getLanguageFallbackLabelDescriptionLookupFactory(),
			$this->settings->getSetting( 'allowDataAccessInUserLanguage' )
		);
	}

	public function getPropertyParserFunctionRunner(): Runner {
		return new Runner(
			$this->getStatementGroupRendererFactory(),
			$this->getStore()->getSiteLinkLookup(),
			$this->getEntityIdParser(),
			$this->getRestrictedEntityLookup(),
			$this->settings->getSetting( 'siteGlobalID' ),
			$this->settings->getSetting( 'allowArbitraryDataAccess' )
		);
	}

	public function getOtherProjectsSitesProvider(): OtherProjectsSitesProvider {
		return new CachingOtherProjectsSitesProvider(
			new OtherProjectsSitesGenerator(
				$this->siteLookup,
				$this->settings->getSetting( 'siteGlobalID' ),
				$this->settings->getSetting( 'specialSiteLinkGroups' )
			),
			// TODO: Make configurable? Should be similar, maybe identical to sharedCacheType and
			// sharedCacheDuration, but can not reuse these because this here is not shared.
			ObjectCache::getLocalClusterInstance(),
			60 * 60
		);
	}

	private function getAffectedPagesFinder(): AffectedPagesFinder {
		return new AffectedPagesFinder(
			$this->getStore()->getUsageLookup(),
			new TitleFactory(),
			MediaWikiServices::getInstance()->getLinkBatchFactory(),
			$this->settings->getSetting( 'siteGlobalID' ),
			$this->getLogger()
		);
	}

	public function getChangeHandler(): ChangeHandler {
		$logger = $this->getLogger();

		$pageUpdater = new WikiPageUpdater(
			JobQueueGroup::singleton(),
			$logger,
			MediaWikiServices::getInstance()->getStatsdDataFactory()
		);

		$pageUpdater->setPurgeCacheBatchSize( $this->settings->getSetting( 'purgeCacheBatchSize' ) );
		$pageUpdater->setRecentChangesBatchSize( $this->settings->getSetting( 'recentChangesBatchSize' ) );

		$changeListTransformer = new ChangeRunCoalescer(
			$this->getStore()->getEntityRevisionLookup(),
			$this->getEntityChangeFactory(),
			$logger,
			$this->settings->getSetting( 'siteGlobalID' )
		);

		return new ChangeHandler(
			$this->getAffectedPagesFinder(),
			new TitleFactory(),
			$pageUpdater,
			$changeListTransformer,
			$this->siteLookup,
			$logger,
			$this->settings->getSetting( 'injectRecentChanges' )
		);
	}

	public function getRecentChangeFactory(): RecentChangeFactory {
		$repoSite = $this->siteLookup->getSite(
			$this->getDatabaseDomainNameOfLocalRepo()
		);
		$interwikiPrefixes = ( $repoSite !== null ) ? $repoSite->getInterwikiIds() : [];
		$interwikiPrefix = ( $interwikiPrefixes !== [] ) ? $interwikiPrefixes[0] : null;

		return new RecentChangeFactory(
			$this->getContentLanguage(),
			new SiteLinkCommentCreator(
				$this->getContentLanguage(),
				$this->siteLookup,
				$this->settings->getSetting( 'siteGlobalID' )
			),
			CentralIdLookup::factoryNonLocal(),
			( $interwikiPrefix !== null ) ?
				new ExternalUserNames( $interwikiPrefix, false ) : null
		);
	}

	/**
	 * @return string|false
	 */
	public function getDatabaseDomainNameOfLocalRepo() {
		return $this->getEntitySourceOfLocalRepo()->getDatabaseName();
	}

	private function getEntitySourceOfLocalRepo(): EntitySource {
		$itemAndPropertySourceName = $this->settings->getSetting( 'itemAndPropertySourceName' );
		$sources = $this->entitySourceDefinitions->getSources();
		foreach ( $sources as $source ) {
			if ( $source->getSourceName() === $itemAndPropertySourceName ) {
				return $source;
			}
		}

		throw new LogicException( 'No source configured: ' . $itemAndPropertySourceName );
	}

	public function getWikibaseContentLanguages(): WikibaseContentLanguages {
		if ( $this->wikibaseContentLanguages === null ) {
			$this->wikibaseContentLanguages = WikibaseContentLanguages::getDefaultInstance();
		}

		return $this->wikibaseContentLanguages;
	}

	/**
	 * Get a ContentLanguages object holding the languages available for labels, descriptions and aliases.
	 */
	public function getTermsLanguages(): ContentLanguages {
		return $this->getWikibaseContentLanguages()->getContentLanguages( WikibaseContentLanguages::CONTEXT_TERM );
	}

	public function getRestrictedEntityLookup(): RestrictedEntityLookup {
		if ( $this->restrictedEntityLookup === null ) {
			$disabledEntityTypesEntityLookup = new DisabledEntityTypesEntityLookup(
				$this->getEntityLookup(),
				$this->settings->getSetting( 'disabledAccessEntityTypes' )
			);
			$this->restrictedEntityLookup = new RestrictedEntityLookup(
				$disabledEntityTypesEntityLookup,
				$this->settings->getSetting( 'entityAccessLimit' )
			);
		}

		return $this->restrictedEntityLookup;
	}

	public function getPropertyOrderProvider(): PropertyOrderProvider {
		if ( $this->propertyOrderProvider === null ) {
			$title = Title::newFromText( 'MediaWiki:Wikibase-SortedProperties' );
			$innerProvider = new WikiPagePropertyOrderProvider( $title );

			$url = $this->settings->getSetting( 'propertyOrderUrl' );
			if ( $url !== null ) {
				$innerProvider = new FallbackPropertyOrderProvider(
					$innerProvider,
					new HttpUrlPropertyOrderProvider(
						$url,
						new Http(),
						$this->getLogger()
					)
				);
			}

			$this->propertyOrderProvider = new CachingPropertyOrderProvider(
				$innerProvider,
				ObjectCache::getLocalClusterInstance()
			);
		}

		return $this->propertyOrderProvider;
	}

	public function getEntityNamespaceLookup(): EntityNamespaceLookup {
		return $this->getWikibaseServices()->getEntityNamespaceLookup();
	}

	public function getDataAccessLanguageFallbackChain( Language $language ): TermLanguageFallbackChain {
		return $this->getLanguageFallbackChainFactory()->newFromLanguage(
			$language,
			LanguageFallbackChainFactory::FALLBACK_ALL
		);
	}

	public function getTermFallbackCache(): TermFallbackCacheFacade {
		return new TermFallbackCacheFacade(
			$this->getTermFallbackCacheFactory()->getTermFallbackCache(),
			$this->getSettings()->getSetting( 'sharedCacheDuration' )
		);
	}

	public function getTermFallbackCacheFactory(): TermFallbackCacheFactory {
		global $wgSecretKey;

		if ( $this->termFallbackCacheFactory === null ) {
			$this->termFallbackCacheFactory = new TermFallbackCacheFactory(
				$this->settings->getSetting( 'sharedCacheType' ),
				$this->getLogger(),
				MediaWikiServices::getInstance()->getStatsdDataFactory(),
				hash( 'sha256', $wgSecretKey ),
				new TermFallbackCacheServiceFactory(),
				$this->settings->getSetting( 'termFallbackCacheVersion' )
			);
		}
		return $this->termFallbackCacheFactory;
	}

	public function getEntityIdLookup(): EntityIdLookup {
		if ( $this->entityIdLookup === null ) {
			$this->entityIdLookup = new ByTypeDispatchingEntityIdLookup(
				$this->entityTypeDefinitions->get( EntityTypeDefinitions::CONTENT_MODEL_ID ),
				$this->entityTypeDefinitions->get( EntityTypeDefinitions::ENTITY_ID_LOOKUP_CALLBACK ),
				new PagePropsEntityIdLookup(
					MediaWikiServices::getInstance()->getDBLoadBalancer(),
					$this->getEntityIdParser()
				)
			);
		}

		return $this->entityIdLookup;
	}

	public function getDescriptionLookup(): DescriptionLookup {
		if ( $this->descriptionLookup === null ) {
			$this->descriptionLookup = new DescriptionLookup(
				$this->getEntityIdLookup(),
				$this->getTermBuffer(),
				MediaWikiServices::getInstance()->getPageProps()
			);
		}
		return $this->descriptionLookup;
	}

	public function getPropertyLabelResolver(): PropertyLabelResolver {
		if ( $this->propertyLabelResolver === null ) {
			$languageCode = $this->getContentLanguage()->getCode();
			$cacheKeyPrefix = $this->settings->getSetting( 'sharedCacheKeyPrefix' );
			$cacheType = $this->settings->getSetting( 'sharedCacheType' );
			$cacheDuration = $this->settings->getSetting( 'sharedCacheDuration' );

			// Cache key needs to be language specific
			$cacheKey = $cacheKeyPrefix . ':TermPropertyLabelResolver' . '/' . $languageCode;

			$this->propertyLabelResolver = $this->getCachedDatabasePropertyLabelResolver(
				$languageCode,
				ObjectCache::getInstance( $cacheType ),
				$cacheDuration,
				$cacheKey
			);
		}

		return $this->propertyLabelResolver;
	}

	public function getReferenceFormatterFactory(): ReferenceFormatterFactory {
		if ( $this->referenceFormatterFactory === null ) {
			$logger = $this->getLogger();
			$this->referenceFormatterFactory = new ReferenceFormatterFactory(
				$this->getDataAccessSnakFormatterFactory(),
				WellKnownReferenceProperties::newFromArray(
					$this->getSettings()->getSetting( 'wellKnownReferencePropertyIds' ),
					$logger
				),
				$logger
			);
		}

		return $this->referenceFormatterFactory;
	}

	private function getCachedDatabasePropertyLabelResolver(
		$languageCode,
		$cache,
		$cacheDuration,
		$cacheKey
	): CachedDatabasePropertyLabelResolver {
		$loadBalancer = $this->getLoadBalancerForConfiguredPropertySource();
		$wanObjectCache = $this->getWANObjectCache();
		$typeIdsStore = new DatabaseTypeIdsStore(
			$loadBalancer,
			$wanObjectCache,
			$this->getDatabaseDomainForPropertySource()
		);
		$databaseTermIdsResolver = new DatabaseTermInLangIdsResolver(
			$typeIdsStore,
			$typeIdsStore,
			$loadBalancer,
			$this->getDatabaseDomainForPropertySource()
		);

		return new CachedDatabasePropertyLabelResolver(
			$languageCode,
			$databaseTermIdsResolver,
			$cache,
			$cacheDuration,
			$cacheKey
		);
	}

	private function getLoadBalancerForConfiguredPropertySource() {
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		return $lbFactory->getMainLB( $this->getDatabaseDomainForPropertySource() );
	}

	private function getDatabaseDomainForPropertySource() {
		$propertySource = $this->getPropertySource();

		return $propertySource->getDatabaseName();
	}

	private function getWANObjectCache() {
		return MediaWikiServices::getInstance()->getMainWANObjectCache();
	}

	private function getItemSource() {
		$itemSource = $this->entitySourceDefinitions->getSourceForEntityType( Item::ENTITY_TYPE );

		if ( $itemSource === null ) {
			throw new LogicException( 'No source providing Items configured!' );
		}

		return $itemSource;
	}

	private function getPropertySource() {
		$propertySource = $this->entitySourceDefinitions->getSourceForEntityType( Property::ENTITY_TYPE );

		if ( $propertySource === null ) {
			throw new LogicException( 'No source providing Properties configured!' );
		}

		return $propertySource;
	}

}
