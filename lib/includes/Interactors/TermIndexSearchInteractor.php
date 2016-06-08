<?php

namespace Wikibase\Lib\Interactors;

use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Term\Term;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookup;
use Wikibase\Store\BufferingTermLookup;
use Wikibase\TermIndex;
use Wikibase\TermIndexEntry;
use Wikimedia\Assert\Assert;

/**
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Addshore
 */
class TermIndexSearchInteractor implements TermSearchInteractor {

	/**
	 * @var TermIndex
	 */
	private $termIndex;

	/**
	 * @var LanguageFallbackChainFactory
	 */
	private $languageFallbackChainFactory;

	/**
	 * @var BufferingTermLookup
	 */
	private $bufferingTermLookup;

	/**
	 * @var LanguageFallbackLabelDescriptionLookup
	 */
	private $labelDescriptionLookup;

	/**
	 * @var string languageCode to use for display terms
	 */
	private $displayLanguageCode;

	/**
	 * @var TermSearchOptions
	 */
	private $termSearchOptions;

	/**
	 * @param TermIndex $termIndex Used to search the terms
	 * @param LanguageFallbackChainFactory $fallbackFactory
	 * @param BufferingTermLookup $bufferingTermLookup Provides the displayTerms
	 * @param string $displayLanguageCode
	 */
	public function __construct(
		TermIndex $termIndex,
		LanguageFallbackChainFactory $fallbackFactory,
		BufferingTermLookup $bufferingTermLookup,
		$displayLanguageCode
	) {
		Assert::parameterType( 'string', $displayLanguageCode, '$displayLanguageCode' );
		$this->termIndex = $termIndex;
		$this->bufferingTermLookup = $bufferingTermLookup;
		$this->languageFallbackChainFactory = $fallbackFactory;
		$this->displayLanguageCode = $displayLanguageCode;
		$this->labelDescriptionLookup = new LanguageFallbackLabelDescriptionLookup(
			$this->bufferingTermLookup,
			$this->languageFallbackChainFactory->newFromLanguageCode( $this->displayLanguageCode )
		);

		$this->termSearchOptions = new TermSearchOptions();
	}

	/**
	 * @param TermSearchOptions $termSearchOptions
	 */
	public function setTermSearchOptions( TermSearchOptions $termSearchOptions ) {
		$this->termSearchOptions = $termSearchOptions;
	}

	/**
	 * @param int $limit Hard upper limit of 5000
	 * @deprecated
	 */
	public function setLimit( $limit ) {
		$this->termSearchOptions->setLimit( $limit );
	}

	/**
	 * @param bool $caseSensitive
	 * @deprecated
	 */
	public function setIsCaseSensitive( $caseSensitive ) {
		$this->termSearchOptions->setIsCaseSensitive( $caseSensitive );
	}

	/**
	 * @param bool $prefixSearch
	 * @deprecated
	 */
	public function setIsPrefixSearch( $prefixSearch ) {
		$this->termSearchOptions->setIsPrefixSearch( $prefixSearch );
	}

	/**
	 * @param bool $useLanguageFallback
	 * @deprecated
	 */
	public function setUseLanguageFallback( $useLanguageFallback ) {
		$this->termSearchOptions->setUseLanguageFallback( $useLanguageFallback );
	}

	/**
	 * @see TermSearchInteractor::searchForEntities
	 *
	 * @param string $text
	 * @param string $languageCode
	 * @param string $entityType
	 * @param string[] $termTypes
	 *
	 * @return TermSearchResult[]
	 */
	public function searchForEntities(
		$text,
		$languageCode,
		$entityType,
		array $termTypes
	) {
		$matchedTermIndexEntries = $this->getMatchingTermIndexEntries(
			$text,
			$languageCode,
			$entityType,
			$termTypes
		);
		$entityIds = $this->getEntityIdsForTermIndexEntries( $matchedTermIndexEntries );

		$this->preFetchLabelsAndDescriptionsForDisplay( $entityIds );
		return $this->getSearchResults( $matchedTermIndexEntries );
	}

	/**
	 * @param string $text
	 * @param string $languageCode
	 * @param string $entityType
	 * @param string[] $termTypes
	 *
	 * @return TermIndexEntry[]
	 */
	private function getMatchingTermIndexEntries(
		$text,
		$languageCode,
		$entityType,
		array $termTypes
	) {
		$languageCodes = [ $languageCode ];

		$matchedTermIndexEntries = $this->termIndex->getTopMatchingTerms(
			$this->makeTermIndexEntryTemplates(
				$text,
				$languageCodes,
				$termTypes
			),
			null,
			$entityType,
			$this->getTermIndexOptions( $this->termSearchOptions )
		);

		$limit = $this->termSearchOptions->getLimit();

		// Shortcut out if we already have enough TermIndexEntries
		if ( count( $matchedTermIndexEntries ) >= $limit
			|| !$this->termSearchOptions->getUseLanguageFallback()
		) {
			return $matchedTermIndexEntries;
		}

		if ( $this->termSearchOptions->getUseLanguageFallback() ) {
			// Matches in the main language will always be first
			$matchedTermIndexEntries = array_merge(
				$matchedTermIndexEntries,
				$this->getFallbackMatchedTermIndexEntries(
					$text,
					$languageCodes,
					$termTypes,
					$entityType,
					$this->getMatchedEntityIdSerializations( $matchedTermIndexEntries )
				)
			);

			if ( count( $matchedTermIndexEntries ) > $limit ) {
				array_slice( $matchedTermIndexEntries, 0, $limit, true );
			}
		}

		return $matchedTermIndexEntries;
	}

	/**
	 * @param TermIndexEntry[]
	 *
	 * @return string[]
	 */
	private function getMatchedEntityIdSerializations( array $matchedTermIndexEntries ) {
		$matchedEntityIdSerializations = [];

		foreach ( $matchedTermIndexEntries as $termIndexEntry ) {
			$matchedEntityIdSerializations[] = $termIndexEntry->getEntityId()->getSerialization();
		}

		return $matchedEntityIdSerializations;
	}

	/**
	 * @param string $text
	 * @param string[] $languageCodes
	 * @param string[] $termTypes
	 * @param string $entityType
	 * @param string[] $matchedEntityIdSerializations
	 */
	private function getFallbackMatchedTermIndexEntries(
		$text,
		array $languageCodes,
		$termTypes,
		$entityType,
		array $matchedEntityIdSerializations
	) {
		$fallbackMatchedTermIndexEntries = $this->termIndex->getTopMatchingTerms(
			$this->makeTermIndexEntryTemplates(
				$text,
				$this->addFallbackLanguageCodes( $languageCodes ),
				$termTypes
			),
			null,
			$entityType,
			$this->getTermIndexOptions()
		);

		// Remove any IndexEntries that are already have an match for
		foreach ( $fallbackMatchedTermIndexEntries as $key => $termIndexEntry ) {
			if ( in_array(
				$termIndexEntry->getEntityId()->getSerialization(),
				$matchedEntityIdSerializations
			) ) {
				unset( $fallbackMatchedTermIndexEntries[$key] );
			}
		}

		return $fallbackMatchedTermIndexEntries;
	}

	/**
	 * @param TermIndexEntry[] $termIndexEntries
	 *
	 * @return array[]
	 * @see TermSearchInteractor interface for return format
	 */
	private function getSearchResults( array $termIndexEntries ) {
		$searchResults = array();
		foreach ( $termIndexEntries as $termIndexEntry ) {
			$searchResults[] = $this->convertToSearchResult( $termIndexEntry );
		}
		return array_values( $searchResults );
	}

	/**
	 * @param EntityId[] $entityIds
	 */
	private function preFetchLabelsAndDescriptionsForDisplay( array $entityIds ) {
		$this->bufferingTermLookup->prefetchTerms(
			$entityIds,
			array( TermIndexEntry::TYPE_LABEL, TermIndexEntry::TYPE_DESCRIPTION ),
			$this->addFallbackLanguageCodes( array( $this->displayLanguageCode ) )
		);
	}

	/**
	 * @param TermIndexEntry[] $termsIndexEntries
	 *
	 * @return EntityId[]
	 */
	private function getEntityIdsForTermIndexEntries( array $termsIndexEntries ) {
		$entityIds = array();
		foreach ( $termsIndexEntries as $termIndexEntry ) {
			$entityId = $termIndexEntry->getEntityId();
			// We would hope that this would never happen, but is possible
			if ( $entityId !== null ) {
				// Use a key so that the array will end up being full of unique IDs
				$entityIds[$entityId->getSerialization()] = $entityId;
			}
		}
		return $entityIds;
	}

	/**
	 * @param TermIndexEntry $termIndexEntry
	 *
	 * @return TermSearchResult
	 */
	private function convertToSearchResult( TermIndexEntry $termIndexEntry ) {
		$entityId = $termIndexEntry->getEntityId();
		return new TermSearchResult(
			$termIndexEntry->getTerm(),
			$termIndexEntry->getType(),
			$entityId,
			$this->getLabelDisplayTerm( $entityId ),
			$this->getDescriptionDisplayTerm( $entityId )
		);
	}

	private function getTermIndexOptions() {
		return array(
			'caseSensitive' => $this->termSearchOptions->getIsCaseSensitive(),
			'prefixSearch' => $this->termSearchOptions->getIsPrefixSearch(),
			'LIMIT' => $this->termSearchOptions->getLimit(),
		);
	}

	/**
	 * @param string[] $languageCodes
	 *
	 * @return string[]
	 */
	private function addFallbackLanguageCodes( array $languageCodes ) {
		$languageCodesWithFallback = array();
		foreach ( $languageCodes as $languageCode ) {
			$fallbackChain = $this->languageFallbackChainFactory->newFromLanguageCode( $languageCode );
			$languageCodesWithFallback = array_merge(
				$languageCodesWithFallback,
				$fallbackChain->getFetchLanguageCodes()
			);
		}

		return array_unique( $languageCodesWithFallback );
	}

	/**
	 * @param EntityId $entityId
	 *
	 * @return null|Term
	 */
	private function getLabelDisplayTerm( EntityId $entityId ) {
		return $this->labelDescriptionLookup->getLabel( $entityId );
	}

	/**
	 * @param EntityId $entityId
	 *
	 * @return null|Term
	 */
	private function getDescriptionDisplayTerm( EntityId $entityId ) {
		return $this->labelDescriptionLookup->getDescription( $entityId );
	}

	/**
	 * @param string $text
	 * @param string[] $languageCodes
	 * @param string[] $termTypes
	 *
	 * @return TermIndexEntry[]
	 */
	private function makeTermIndexEntryTemplates( $text, array $languageCodes, array $termTypes ) {
		$terms = array();
		foreach ( $languageCodes as $languageCode ) {
			foreach ( $termTypes as $termType ) {
				$terms[] = new TermIndexEntry( array(
					'termText' => $text,
					'termLanguage' => $languageCode,
					'termType' => $termType,
				) );
			}
		}
		return $terms;
	}

}
