<?php

namespace Wikibase\Lib\Store;

use Language;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Services\Lookup\LabelDescriptionLookup;
use Wikibase\DataModel\Services\Lookup\TermLookup;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookup;
use Wikibase\Store\TermBuffer;

/**
 * Factory to provide an LabelDescriptionLookup which does automatic prefetching
 * of terms, applies a language fallback and returns the LabelDescriptionLookup.
 *
 * @license GPL 2+
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class LanguageFallbackLabelDescriptionLookupFactory {

	/**
	 * @var LanguageFallbackChainFactory
	 */
	private $languageFallbackChainFactory;

	/**
	 * @var TermLookup
	 */
	private $termLookup;

	/**
	 * @var TermBuffer|null
	 */
	private $termBuffer;

	/**
	 * @see LanguageFallbackChainFactory::FALLBACK_
	 * @var int
	 */
	private $fallbackMode;

	/**
	 * @param LanguageFallbackChainFactory $languageFallbackChainFactory
	 * @param TermLookup $termLookup
	 * @param TermBuffer|null $termBuffer
	 * @param int $fallbackMode
	 */
	public function __construct(
		LanguageFallbackChainFactory $languageFallbackChainFactory,
		TermLookup $termLookup,
		TermBuffer $termBuffer = null,
		$fallbackMode = -1
	) {
		$this->languageFallbackChainFactory = $languageFallbackChainFactory;
		$this->termLookup = $termLookup;
		$this->termBuffer = $termBuffer;
		$this->fallbackMode = $fallbackMode >= 0 ? $fallbackMode : LanguageFallbackChainFactory::FALLBACK_SELF
			| LanguageFallbackChainFactory::FALLBACK_VARIANTS
			| LanguageFallbackChainFactory::FALLBACK_OTHERS;
	}

	/**
	 * Returns a LabelDescriptionLookup where terms are prefetched for the given
	 * entity ids with a language fallback chain applied for the given language.
	 *
	 * @param Language $language
	 * @param EntityId[] $entityIds
	 * @param string[] $termTypes default is only labels
	 *
	 * @return LabelDescriptionLookup
	 */
	public function newLabelDescriptionLookup( Language $language, array $entityIds, array $termTypes = array( 'label' ) ) {
		$languageFallbackChain = $this->languageFallbackChainFactory->newFromLanguage(
			$language,
			$this->fallbackMode
		);

		$languages = $languageFallbackChain->getFetchLanguageCodes();

		$labelDescriptionLookup = new LanguageFallbackLabelDescriptionLookup(
			$this->termLookup,
			$languageFallbackChain
		);

		// Optionally prefetch the terms of the entities passed in here
		// $termLookup and $termBuffer should be the same BufferingTermLookup then
		if ( $this->termBuffer !== null ) {
			$this->termBuffer->prefetchTerms( $entityIds, $termTypes, $languages );
		}

		return $labelDescriptionLookup;
	}

}
