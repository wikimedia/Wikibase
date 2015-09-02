<?php

namespace Wikibase\Client\DataAccess\Scribunto;

use Deserializers\Exceptions\DeserializationException;
use Language;
use Scribunto_LuaLibraryBase;
use ScribuntoException;
use ValueFormatters\FormatterOptions;
use Wikibase\Client\DataAccess\EntityAccessLimitException;
use Wikibase\Client\DataAccess\PropertyIdResolver;
use Wikibase\Client\PropertyLabelNotResolvedException;
use Wikibase\Client\Usage\ParserOutputUsageAccumulator;
use Wikibase\Client\Usage\UsageTrackingSnakFormatter;
use Wikibase\Client\Usage\UsageTrackingTermLookup;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Services\Lookup\EntityRetrievingTermLookup;
use Wikibase\LanguageFallbackChain;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookup;

/**
 * Registers and defines functions to access Wikibase through the Scribunto extension
 *
 * @since 0.4
 *
 * @licence GNU GPL v2+
 * @author Jens Ohlig < jens.ohlig@wikimedia.de >
 * @author Marius Hoch < hoo@online.de >
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class Scribunto_LuaWikibaseLibrary extends Scribunto_LuaLibraryBase {

	/**
	 * @var WikibaseLuaBindings|null
	 */
	private $luaBindings = null;

	/**
	 * @var EntityAccessor|null
	 */
	private $entityAccessor = null;

	/**
	 * @var SnakSerializationRenderer|null
	 */
	private $snakSerializationRenderer = null;

	/**
	 * @var LanguageFallbackChain|null
	 */
	private $fallbackChain = null;

	/**
	 * @var ParserOutputUsageAccumulator|null
	 */
	private $usageAccumulator = null;

	/**
	 * @var PropertyIdResolver|null
	 */
	private $propertyIdResolver = null;

	/**
	 * @return WikibaseLuaBindings
	 */
	private function getLuaBindings() {
		if ( $this->luaBindings === null ) {
			$this->luaBindings = $this->newLuaBindings();
		}

		return $this->luaBindings;
	}

	/**
	 * @return EntityAccessor
	 */
	private function getEntityAccessor() {
		if ( $this->entityAccessor === null ) {
			$this->entityAccessor = $this->newEntityAccessor();
		}

		return $this->entityAccessor;
	}

	/**
	 * @return SnakSerializationRenderer
	 */
	private function getSnakSerializationRenderer() {
		if ( $this->snakSerializationRenderer === null ) {
			$this->snakSerializationRenderer = $this->newSnakSerializationRenderer();
		}

		return $this->snakSerializationRenderer;
	}

	/**
	 * @return LanguageFallbackChain
	 */
	private function getLanguageFallbackChain() {
		if ( $this->fallbackChain === null ) {
			$fallbackChainFactory = WikibaseClient::getDefaultInstance()->getLanguageFallbackChainFactory();

			$this->fallbackChain = $fallbackChainFactory->newFromLanguage(
				$this->getLanguage(),
				LanguageFallbackChainFactory::FALLBACK_SELF | LanguageFallbackChainFactory::FALLBACK_VARIANTS
			);
		}

		return $this->fallbackChain;
	}

	/**
	 * @return ParserOutputUsageAccumulator
	 */
	public function getUsageAccumulator() {
		if ( $this->usageAccumulator === null ) {
			$parserOutput = $this->getParser()->getOutput();
			$this->usageAccumulator = new ParserOutputUsageAccumulator( $parserOutput );
		}

		return $this->usageAccumulator;
	}

	/**
	 * @return PropertyIdResolver
	 */
	private function getPropertyIdResolver() {
		if ( $this->propertyIdResolver === null ) {
			$wikibaseClient = WikibaseClient::getDefaultInstance();
			$entityLookup = $wikibaseClient->getStore()->getEntityLookup();
			$propertyLabelResolver = $wikibaseClient->getStore()->getPropertyLabelResolver();

			$this->propertyIdResolver = new PropertyIdResolver( $entityLookup, $propertyLabelResolver );
		}

		return $this->propertyIdResolver;
	}

	/**
	 * @return Language
	 */
	private function getLanguage() {
		// For the language we need $wgContLang, not parser target language or anything else.
		// See Scribunto_LuaLanguageLibrary::getContLangCode().
		global $wgContLang;
		return $wgContLang;
	}

	private function newEntityAccessor() {
		$wikibaseClient = WikibaseClient::getDefaultInstance();

		return new EntityAccessor(
			$wikibaseClient->getEntityIdParser(),
			$wikibaseClient->getRestrictedEntityLookup(),
			$this->getUsageAccumulator(),
			$wikibaseClient->getPropertyDataTypeLookup(),
			$this->getLanguageFallbackChain(),
			$this->getLanguage(),
			$wikibaseClient->getTermsLanguages()
		);
	}

	private function newSnakSerializationRenderer() {
		$wikibaseClient = WikibaseClient::getDefaultInstance();

		$formatterOptions = new FormatterOptions( array(
			SnakFormatter::OPT_LANG => $this->getLanguage()->getCode()
		) );

		$snakFormatter = new UsageTrackingSnakFormatter(
			$wikibaseClient->getSnakFormatterFactory()->getSnakFormatter(
				SnakFormatter::FORMAT_WIKI, $formatterOptions
			),
			$this->getUsageAccumulator(),
			$this->getLanguageFallbackChain()->getFetchLanguageCodes()
		);

		$snakDeserializer = $wikibaseClient->getDeserializerFactory()->newSnakDeserializer();
		$snaksDeserializer = $wikibaseClient->getDeserializerFactory()->newSnakListDeserializer();

		return new SnakSerializationRenderer(
			$snakFormatter,
			$snakDeserializer,
			$this->getLanguage(),
			$snaksDeserializer
		);
	}

	private function newLuaBindings() {
		$wikibaseClient = WikibaseClient::getDefaultInstance();
		$entityLookup = $wikibaseClient->getStore()->getEntityLookup();
		$usageAccumulator = $this->getUsageAccumulator();

		$labelDescriptionLookup = new LanguageFallbackLabelDescriptionLookup(
			new UsageTrackingTermLookup(
				new EntityRetrievingTermLookup( $entityLookup ),
				$usageAccumulator
			),
			$this->getLanguageFallbackChain()
		);

		return new WikibaseLuaBindings(
			$wikibaseClient->getEntityIdParser(),
			$entityLookup,
			$wikibaseClient->getStore()->getSiteLinkLookup(),
			$wikibaseClient->getSettings(),
			$labelDescriptionLookup,
			$usageAccumulator,
			$this->getParserOptions(),
			$wikibaseClient->getSettings()->getSetting( 'siteGlobalID' )
		);
	}

	/**
	 * Register mw.wikibase.lua library
	 *
	 * @since 0.4
	 *
	 * @return array
	 */
	public function register() {
		// These functions will be exposed to the Lua module.
		// They are member functions on a Lua table which is private to the module, thus
		// these can't be called from user code, unless explicitly exposed in Lua.
		$lib = array(
			'getLabel' => array( $this, 'getLabel' ),
			'getEntity' => array( $this, 'getEntity' ),
			'getSetting' => array( $this, 'getSetting' ),
			'renderSnak' => array( $this, 'renderSnak' ),
			'renderSnaks' => array( $this, 'renderSnaks' ),
			'getEntityId' => array( $this, 'getEntityId' ),
			'getUserLang' => array( $this, 'getUserLang' ),
			'getDescription' => array( $this, 'getDescription' ),
			'resolvePropertyId' => array( $this, 'resolvePropertyId' ),
			'getSiteLinkPageName' => array( $this, 'getSiteLinkPageName' ),
			'incrementExpensiveFunctionCount' => array( $this, 'incrementExpensiveFunctionCount' ),
		);

		return $this->getEngine()->registerInterface(
			__DIR__ . '/mw.wikibase.lua', $lib, array()
		);
	}

	/**
	 * Wrapper for getEntity in EntityAccessor
	 *
	 * @since 0.5
	 *
	 * @param string $prefixedEntityId
	 *
	 * @throws ScribuntoException
	 * @return array
	 */
	public function getEntity( $prefixedEntityId ) {
		$this->checkType( 'getEntity', 1, $prefixedEntityId, 'string' );

		try {
			$entityArr = $this->getEntityAccessor()->getEntity( $prefixedEntityId );
			return array( $entityArr );
		} catch ( EntityIdParsingException $ex ) {
			throw new ScribuntoException( 'wikibase-error-invalid-entity-id' );
		} catch ( EntityAccessLimitException $ex ) {
			throw new ScribuntoException( 'wikibase-error-exceeded-entity-access-limit' );
		} catch ( Exception $ex ) {
			throw new ScribuntoException( 'wikibase-error-serialize-error' );
		}
	}

	/**
	 * Wrapper for getEntityId in WikibaseLuaBindings
	 *
	 * @since 0.5
	 *
	 * @param string $pageTitle
	 *
	 * @return array
	 */
	public function getEntityId( $pageTitle = null ) {
		$this->checkType( 'getEntityByTitle', 1, $pageTitle, 'string' );
		return array( $this->getLuaBindings()->getEntityId( $pageTitle ) );
	}

	/**
	 * Wrapper for getSetting in WikibaseLuaBindings
	 *
	 * @since 0.5
	 *
	 * @param string $setting
	 *
	 * @return array
	 */
	public function getSetting( $setting ) {
		$this->checkType( 'setting', 1, $setting, 'string' );
		return array( $this->getLuaBindings()->getSetting( $setting ) );
	}

	/**
	 * Wrapper for getLabel in Scribunto_LuaWikibaseLibraryImplementation
	 *
	 * @since 0.5
	 *
	 * @param string $prefixedEntityId
	 *
	 * @return string[]
	 */
	public function getLabel( $prefixedEntityId ) {
		$this->checkType( 'getLabel', 1, $prefixedEntityId, 'string' );
		return array( $this->getLuaBindings()->getLabel( $prefixedEntityId ) );
	}

	/**
	 * Wrapper for getDescription in Scribunto_LuaWikibaseLibraryImplementation
	 *
	 * @since 0.5
	 *
	 * @param string $prefixedEntityId
	 *
	 * @return string[]
	 */
	public function getDescription( $prefixedEntityId ) {
		$this->checkType( 'getDescription', 1, $prefixedEntityId, 'string' );
		return array( $this->getLuaBindings()->getDescription( $prefixedEntityId ) );
	}

	/**
	 * Wrapper for getSiteLinkPageName in WikibaseLuaBindings
	 *
	 * @since 0.5
	 *
	 * @param string $prefixedEntityId
	 *
	 * @return string[]
	 */
	public function getSiteLinkPageName( $prefixedEntityId ) {
		$this->checkType( 'getSiteLinkPageName', 1, $prefixedEntityId, 'string' );
		return array( $this->getLuaBindings()->getSiteLinkPageName( $prefixedEntityId ) );
	}

	/**
	 * Wrapper for renderSnak in SnakRenderer
	 *
	 * @since 0.5
	 *
	 * @param array $snakSerialization
	 *
	 * @throws ScribuntoException
	 * @return string[]
	 */
	public function renderSnak( $snakSerialization ) {
		$this->checkType( 'renderSnak', 1, $snakSerialization, 'table' );
		try {
			$ret = array( $this->getSnakSerializationRenderer()->renderSnak( $snakSerialization ) );
			return $ret;
		} catch ( DeserializationException $e ) {
			throw new ScribuntoException( 'wikibase-error-deserialize-error' );
		}
	}

	/**
	 * Wrapper for renderSnaks in SnakRenderer
	 *
	 * @since 0.5
	 *
	 * @param array $snaksSerialization
	 *
	 * @throws ScribuntoException
	 * @return string[]
	 */
	public function renderSnaks( $snaksSerialization ) {
		$this->checkType( 'renderSnaks', 1, $snaksSerialization, 'table' );
		try {
			$ret = array( $this->getSnakSerializationRenderer()->renderSnaks( $snaksSerialization ) );
			return $ret;
		} catch ( DeserializationException $e ) {
			throw new ScribuntoException( 'wikibase-error-deserialize-error' );
		}
	}

	/**
	 * Wrapper for PropertyIdResolver
	 *
	 * @since 0.5
	 *
	 * @param string $propertyLabelOrId
	 *
	 * @return string[]|null[]
	 */
	public function resolvePropertyId( $propertyLabelOrId ) {
		$this->checkType( 'resolvePropertyId', 1, $propertyLabelOrId, 'string' );
		try {
			$languageCode = $this->getLanguage()->getCode();
			$propertyId = $this->getPropertyIdResolver()->resolvePropertyId( $propertyLabelOrId, $languageCode );
			$ret = array( $propertyId->getSerialization() );
			return $ret;
		} catch ( PropertyLabelNotResolvedException $e ) {
			return array( null );
		}
	}

	/**
	 * Wrapper for getUserLang in WikibaseLuaBindings
	 * Side effect: Splits the parser cache by user language!
	 *
	 * @since 0.5
	 *
	 * @return string[]
	 */
	public function getUserLang() {
		return array( $this->getLuaBindings()->getUserLang() );
	}

}
