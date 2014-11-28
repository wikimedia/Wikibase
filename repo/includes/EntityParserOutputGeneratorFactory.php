<?php

namespace Wikibase;

use InvalidArgumentException;
use ParserOptions;
use ParserOutput;
use RequestContext;
use User;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\Serializers\SerializationOptions;
use Wikibase\Lib\Store\EntityInfoBuilderFactory;
use Wikibase\Lib\Store\EntityLookup;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Repo\View\EntityViewFactory;
use Wikibase\Repo\WikibaseRepo;

/**
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class EntityParserOutputGeneratorFactory {

	/**
	 * @var EntityViewFactory
	 */
	private $entityViewFactory;

	/**
	 * @var EntityInfoBuilderFactory
	 */
	private $entityInfoBuilderFactory;

	/**
	 * @var EntityTitleLookup
	 */
	private $entityTitleLookup;

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 * @var ValuesFinder
	 */
	private $valuesFinder;

	/**
	 * @var LanguageFallbackChainFactory
	 */
	private $languageFallbackChainFactory;

	public function __construct(
		EntityViewFactory $entityViewFactory,
		EntityInfoBuilderFactory $entityInfoBuilderFactory,
		EntityTitleLookup $entityTitleLookup,
		EntityIdParser $entityIdParser,
		ValuesFinder $valuesFinder,
		LanguageFallbackChainFactory $languageFallbackChainFactory
	) {
		$this->entityViewFactory = $entityViewFactory;
		$this->entityInfoBuilderFactory = $entityInfoBuilderFactory;
		$this->entityTitleLookup = $entityTitleLookup;
		$this->entityIdParser = $entityIdParser;
		$this->valuesFinder = $valuesFinder;
		$this->languageFallbackChainFactory = $languageFallbackChainFactory;
	}

	/**
	 * Creates an EntityParserOutputGenerator to create the ParserOutput for the entity
	 *
	 * @param ParserOptions|null $options
	 *
	 * @return EntityParserOutputGenerator
	 */
	public function getEntityParserOutputGenerator( ParserOptions $options = null ) {
		$languageCode = $this->getLanguageCode( $options );

		return new EntityParserOutputGenerator(
			$this->entityViewFactory,
			$this->newParserOutputJsConfigBuilder( $languageCode ),
			$this->entityTitleLookup,
			$this->valuesFinder,
			$this->entityInfoBuilderFactory,
			$this->getLanguageFallbackChain( $languageCode ),
			$languageCode
		);
	}

	/**
	 * @param string $languageCode
	 *
	 * @return ParserOutputJsConfigBuilder
	 */
	private function newParserOutputJsConfigBuilder( $languageCode ) {
		return new ParserOutputJsConfigBuilder(
			$this->entityIdParser,
			$this->entityTitleLookup,
			$this->makeJsConfigSerializationOptions( $languageCode )
		);
	}

	/**
	 * @param ParserOptions|null $options
	 *
	 * @return string
	 */
	private function getLanguageCode( ParserOptions $options = null ) {
		// NOTE: Parser Options language overrides context language!
		if ( $options !== null ) {
			$languageCode = $options->getUserLang();
		} else {
			// @todo do we still need to fallback to context here?
			// if needed, then maybe inject some 'default' in the constructor.
			$context = RequestContext::getMain();
			$languageCode = $context->getLanguage()->getCode();
		}

		return $languageCode;
	}

	/**
	 * @param string $languageCode
	 *
	 * @return LanguageFallbackChain
	 */
	private function getLanguageFallbackChain( $languageCode ) {
		// Language fallback must depend ONLY on the target language,
		// so we don't confuse the parser cache with user specific HTML.
		return $this->languageFallbackChainFactory->newFromLanguageCode(
			$languageCode
		);
	}

	/**
	 * @param string $languageCode
	 *
	 * @return SerializationOptions
	 */
	private function makeJsConfigSerializationOptions( $languageCode ) {
		// NOTE: when serializing the full entity to be stored in the
		// wbEntity JS config variable, we currently do not want any
		// language fallback to be applied.

		$options = new SerializationOptions();
		$options->setOption( ValueFormatter::OPT_LANG, $languageCode );

		return $options;
	}

}
