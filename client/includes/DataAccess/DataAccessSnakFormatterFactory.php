<?php

namespace Wikibase\Client\DataAccess;

use Language;
use ValueFormatters\FormatterOptions;
use Wikibase\Client\Usage\UsageAccumulator;
use Wikibase\Client\Usage\UsageTrackingSnakFormatter;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\BinaryOptionDispatchingSnakFormatter;
use Wikibase\Lib\EscapingSnakFormatter;
use Wikibase\Lib\FormatterLabelDescriptionLookupFactory;
use Wikibase\Lib\OutputFormatSnakFormatterFactory;
use Wikibase\Lib\SnakFormatter;

/**
 * A factory for SnakFormatters in a client context, to be reused in different methods that "access
 * repository data" from a client (typically parser functions and Lua scripts).
 *
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Marius Hoch
 */
class DataAccessSnakFormatterFactory {

	/**
	 * @var LanguageFallbackChainFactory
	 */
	private $languageFallbackChainFactory;

	/**
	 * @var OutputFormatSnakFormatterFactory
	 */
	private $snakFormatterFactory;

	/**
	 * @var PropertyDataTypeLookup
	 */
	private $propertyDataTypeLookup;

	/**
	 * @param LanguageFallbackChainFactory $languageFallbackChainFactory
	 * @param OutputFormatSnakFormatterFactory $snakFormatterFactory
	 * @param PropertyDataTypeLookup $propertyDataTypeLookup
	 */
	public function __construct(
		LanguageFallbackChainFactory $languageFallbackChainFactory,
		OutputFormatSnakFormatterFactory $snakFormatterFactory,
		PropertyDataTypeLookup $propertyDataTypeLookup
	) {
		$this->languageFallbackChainFactory = $languageFallbackChainFactory;
		$this->snakFormatterFactory = $snakFormatterFactory;
		$this->propertyDataTypeLookup = $propertyDataTypeLookup;
	}

	/**
	 * This returns a SnakFormatter that will return wikitext escaped plain text.
	 * The only exception are URLs, these are plain text, but not escaped in any
	 * way.
	 *
	 * @param Language $language
	 * @param UsageAccumulator $usageAccumulator
	 *
	 * @return SnakFormatter
	 */
	public function newEscapedPlainTextSnakFormatter(
		Language $language,
		UsageAccumulator $usageAccumulator
	) {
		return $this->newSnakFormatter( 'escaped-plaintext', $language, $usageAccumulator );
	}

	/**
	 * This returns a SnakFormatter that will return "rich" wikitext.
	 *
	 * @param Language $language
	 * @param UsageAccumulator $usageAccumulator
	 *
	 * @return SnakFormatter
	 */
	public function newRichWikitextSnakFormatter(
		Language $language,
		UsageAccumulator $usageAccumulator
	) {
		return $this->newSnakFormatter( 'rich-wikitext', $language, $usageAccumulator );
	}

	/**
	 * @param string $type
	 * @param Language $language
	 * @param UsageAccumulator $usageAccumulator
	 *
	 * @return SnakFormatter
	 */
	private function newSnakFormatter(
		$type,
		Language $language,
		UsageAccumulator $usageAccumulator
	) {
		$fallbackChain = $this->languageFallbackChainFactory->newFromLanguage(
			$language,
			LanguageFallbackChainFactory::FALLBACK_ALL
		);

		$options = new FormatterOptions( [
			FormatterLabelDescriptionLookupFactory::OPT_LANGUAGE_FALLBACK_CHAIN => $fallbackChain,
			SnakFormatter::OPT_LANG => $language->getCode(),
		] );

		if ( $type === 'rich-wikitext' ) {
			$snakFormatter = $this->getRichWikitextSnakFormatterForOptions( $options );
		} else {
			$snakFormatter = $this->getPlainTextSnakFormatterForOptions( $options );
		}

		return new UsageTrackingSnakFormatter(
			$snakFormatter,
			$usageAccumulator,
			$fallbackChain->getFetchLanguageCodes()
		);
	}

	/**
	 * @param FormatterOptions $options
	 * @return BinaryOptionDispatchingSnakFormatter
	 */
	private function getRichWikitextSnakFormatterForOptions( FormatterOptions $options ) {
		$snakFormatter = $this->snakFormatterFactory->getSnakFormatter(
			SnakFormatter::FORMAT_WIKI,
			$options
		);

		return new EscapingSnakFormatter(
			SnakFormatter::FORMAT_WIKI,
			$snakFormatter,
			function( $str ) {
				return $str === '' ? '' : "<span>$str</span>";
			}
		);
	}

	/**
	 * Our output format is basically wikitext escaped plain text, except
	 * for URLs, these are not wikitext escaped.
	 *
	 * @param FormatterOptions $options
	 * @return BinaryOptionDispatchingSnakFormatter
	 */
	private function getPlainTextSnakFormatterForOptions( FormatterOptions $options ) {
		$plainTextSnakFormatter = $this->snakFormatterFactory->getSnakFormatter(
			SnakFormatter::FORMAT_PLAIN,
			$options
		);

		$escapingSnakFormatter = new EscapingSnakFormatter(
			SnakFormatter::FORMAT_PLAIN,
			$plainTextSnakFormatter,
			'wfEscapeWikiText'
		);

		return new BinaryOptionDispatchingSnakFormatter(
			SnakFormatter::FORMAT_PLAIN,
			$this->propertyDataTypeLookup,
			$plainTextSnakFormatter,
			$escapingSnakFormatter,
			[ 'url' ]
		);
	}

}
