<?php

namespace Wikibase\Client\DataAccess\PropertyParserFunction;

use Language;
use Parser;
use ParserOutput;
use Title;
use Wikibase\Client\DataAccess\DataAccessSnakFormatterFactory;
use Wikibase\Client\DataAccess\PropertyIdResolver;
use Wikibase\Client\DataAccess\SnaksFinder;
use Wikibase\Client\DataAccess\StatementTransclusionInteractor;
use Wikibase\Client\Usage\ParserOutputUsageAccumulator;
use Wikibase\Client\Usage\UsageAccumulator;
use Wikibase\DataModel\Services\Lookup\EntityLookup;

/**
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Thiemo Mättig
 */
class StatementGroupRendererFactory {

	/**
	 * @var PropertyIdResolver
	 */
	private $propertyIdResolver;

	/**
	 * @var SnaksFinder
	 */
	private $snaksFinder;

	/**
	 * @var LanguageAwareRenderer[]
	 */
	private $languageAwareRenderers = [];

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var DataAccessSnakFormatterFactory
	 */
	private $dataAccessSnakFormatterFactory;

	/**
	 * @var bool
	 */
	private $allowDataAccessInUserLanguage;

	/**
	 * @param PropertyIdResolver $propertyIdResolver
	 * @param SnaksFinder $snaksFinder
	 * @param EntityLookup $entityLookup
	 * @param DataAccessSnakFormatterFactory $dataAccessSnakFormatterFactory
	 * @param bool $allowDataAccessInUserLanguage
	 */
	public function __construct(
		PropertyIdResolver $propertyIdResolver,
		SnaksFinder $snaksFinder,
		EntityLookup $entityLookup,
		DataAccessSnakFormatterFactory $dataAccessSnakFormatterFactory,
		$allowDataAccessInUserLanguage
	) {
		$this->propertyIdResolver = $propertyIdResolver;
		$this->snaksFinder = $snaksFinder;
		$this->entityLookup = $entityLookup;
		$this->dataAccessSnakFormatterFactory = $dataAccessSnakFormatterFactory;
		$this->allowDataAccessInUserLanguage = $allowDataAccessInUserLanguage;
	}

	/**
	 * @param Parser $parser
	 * @param string $type Either "escaped-plaintext" or "rich-wikitext".
	 *
	 * @return StatementGroupRenderer
	 */
	public function newRendererFromParser( Parser $parser, $type = 'escaped-plaintext' ) {
		$usageAccumulator = new ParserOutputUsageAccumulator( $parser->getOutput() );

		if ( $this->allowDataAccessInUserLanguage ) {
			// Use the user's language.
			// Note: This splits the parser cache.
			$targetLanguage = $parser->getOptions()->getUserLangObj();
			return $this->newLanguageAwareRenderer(
				$type,
				$targetLanguage,
				$usageAccumulator,
				$parser->getOutput(),
				$parser->getTitle()
			);
		} elseif ( $this->useVariants( $parser ) ) {
			$variants = $parser->getConverterLanguage()->getVariants();
			return $this->newVariantsAwareRenderer(
				$type,
				$variants,
				$usageAccumulator,
				$parser->getOutput(),
				$parser->getTitle()
			);
		} else {
			$targetLanguage = $parser->getTargetLanguage();
			return $this->newLanguageAwareRenderer(
				$type,
				$targetLanguage,
				$usageAccumulator,
				$parser->getOutput(),
				$parser->getTitle()
			);
		}
	}

	/**
	 * @param string $type
	 * @param Language $language
	 * @param UsageAccumulator $usageAccumulator
	 * @param ParserOutput $parserOutput
	 * @param Title $title
	 *
	 * @return LanguageAwareRenderer
	 */
	private function newLanguageAwareRenderer(
		$type,
		Language $language,
		UsageAccumulator $usageAccumulator,
		ParserOutput $parserOutput,
		Title $title
	) {
		$snakFormatter = $this->dataAccessSnakFormatterFactory->newWikitextSnakFormatter(
			$language,
			$usageAccumulator,
			$type
		);

		$entityStatementsRenderer = new StatementTransclusionInteractor(
			$language,
			$this->propertyIdResolver,
			$this->snaksFinder,
			$snakFormatter,
			$this->entityLookup
		);

		return new LanguageAwareRenderer(
			$language,
			$entityStatementsRenderer,
			$parserOutput,
			$title
		);
	}

	/**
	 * @param string $type
	 * @param string $languageCode
	 * @param UsageAccumulator $usageAccumulator
	 * @param ParserOutput $parserOutput
	 * @param Title $title
	 *
	 * @return LanguageAwareRenderer
	 */
	private function getLanguageAwareRendererFromCode(
		$type,
		$languageCode,
		UsageAccumulator $usageAccumulator,
		ParserOutput $parserOutput,
		Title $title
	) {
		if ( !isset( $this->languageAwareRenderers[$languageCode] ) ) {
			$this->languageAwareRenderers[$languageCode] = $this->newLanguageAwareRenderer(
				$type,
				Language::factory( $languageCode ),
				$usageAccumulator,
				$parserOutput,
				$title
			);
		}

		return $this->languageAwareRenderers[$languageCode];
	}

	/**
	 * @param string $type
	 * @param string[] $variants
	 * @param UsageAccumulator $usageAccumulator
	 * @param ParserOutput $parserOutput
	 * @param Title $title
	 *
	 * @return VariantsAwareRenderer
	 */
	private function newVariantsAwareRenderer(
		$type,
		array $variants,
		UsageAccumulator $usageAccumulator,
		ParserOutput $parserOutput,
		Title $title
	) {
		$languageAwareRenderers = [];

		foreach ( $variants as $variant ) {
			$languageAwareRenderers[$variant] = $this->getLanguageAwareRendererFromCode(
				$type,
				$variant,
				$usageAccumulator,
				$parserOutput,
				$title
			);
		}

		return new VariantsAwareRenderer( $languageAwareRenderers, $variants );
	}

	/**
	 * Check whether variants are used in this parser run.
	 *
	 * @param Parser $parser
	 *
	 * @return bool
	 */
	private function isParserUsingVariants( Parser $parser ) {
		$parserOptions = $parser->getOptions();

		return $parser->OutputType() === Parser::OT_HTML
			&& !$parserOptions->getInterfaceMessage()
			&& !$parserOptions->getDisableContentConversion();
	}

	/**
	 * @param Parser $parser
	 *
	 * @return bool
	 */
	private function useVariants( Parser $parser ) {
		return $this->isParserUsingVariants( $parser )
			&& $parser->getConverterLanguage()->hasVariants();
	}

}
