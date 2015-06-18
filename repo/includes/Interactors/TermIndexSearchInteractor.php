<?php

namespace Wikibase\Repo\Interactors;

use OutOfBoundsException;
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
 * @licence GNU GPL v2+
 * @author Adam Shorland
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
	 * @var bool do a case sensitive search
	 */
	private $isCaseSensitive = false;

	/**
	 * @var bool do a prefix search
	 */
	private $isPrefixSearch = false;

	/**
	 * @var bool use language fallback in the search
	 */
	private $useLanguageFallback = true;

	/**
	 * @var int
	 */
	private $limit = 5000;

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
	}

	/**
	 * @return int
	 */
	public function getLimit() {
		return $this->limit;
	}

	/**
	 * @return bool
	 */
	public function getIsCaseSensitive() {
		return $this->isCaseSensitive;
	}

	/**
	 * @return bool
	 */
	public function getIsPrefixSearch() {
		return $this->isPrefixSearch;
	}

	/**
	 * @return bool
	 */
	public function getUseLanguageFallback() {
		return $this->useLanguageFallback;
	}

	/**
	 * @param int $limit Hard upper limit of 5000
	 */
	public function setLimit( $limit ) {
		Assert::parameterType( 'integer', $limit, '$limit' );
		Assert::parameter( $limit > 0, '$limit', 'Must be positive' );
		if ( $limit > 5000 ) {
			$limit = 5000;
		}
		$this->limit = $limit;
	}

	/**
	 * @param bool $caseSensitive
	 */
	public function setIsCaseSensitive( $caseSensitive ) {
		Assert::parameterType( 'boolean', $caseSensitive, '$caseSensitive' );
		$this->isCaseSensitive = $caseSensitive;
	}

	/**
	 * @param bool $prefixSearch
	 */
	public function setIsPrefixSearch( $prefixSearch ) {
		Assert::parameterType( 'boolean', $prefixSearch, '$prefixSearch' );
		$this->isPrefixSearch = $prefixSearch;
	}

	/**
	 * @param bool $useLanguageFallback
	 */
	public function setUseLanguageFallback( $useLanguageFallback ) {
		Assert::parameterType( 'boolean', $useLanguageFallback, '$useLanguageFallback' );
		$this->useLanguageFallback = $useLanguageFallback;
	}

	/**
	 * @see TermSearchInteractor::searchForEntities
	 *
	 * @param string $text
	 * @param string $languageCode
	 * @param string $entityType
	 * @param string[] $termTypes
	 *
	 * @returns array[]
	 */
	public function searchForEntities(
		$text,
		$languageCode,
		$entityType,
		array $termTypes
	) {
		$languageCodes = array( $languageCode );

		$matchedTermIndexEntries = $this->termIndex->getMatchingTerms(
			$this->makeTermIndexEntryTemplates(
				$text,
				$languageCodes,
				$termTypes
			),
			null,
			$entityType,
			$this->getTermIndexOptions()
		);

		if( $this->useLanguageFallback ) {
			$fallbackMatchedTermIndexEntries = $this->termIndex->getMatchingTerms(
				$this->makeTermIndexEntryTemplates(
					$text,
					$this->addFallbackLanguageCodes( $languageCodes ),
					$termTypes
				),
				null,
				$entityType,
				$this->getTermIndexOptions()
			);
			// Matches in the main language will always be first
			$matchedTermIndexEntries = array_merge( $matchedTermIndexEntries, $fallbackMatchedTermIndexEntries );
		}

		$entityIds = $this->getEntityIdsForTermIndexEntries( $matchedTermIndexEntries );
		$this->preFetchLabelsAndDescriptionsForDisplay( $entityIds );
		$aliasTermMap = $this->getAliasesTermMap(
			$this->fetchAllAliasesForDisplay( $entityIds ),
			$text
		);

		return $this->getSearchResults( $matchedTermIndexEntries, $aliasTermMap );
	}

	/**
	 * @param TermIndexEntry[] $termIndexEntries
	 * @param array[] $aliasTermMap
	 *
	 * @returns array[]
	 * @see TermSearchInteractor interface for return format
	 */
	private function getSearchResults( array $termIndexEntries, array $aliasTermMap ) {
		$searchResults = array();
		foreach ( $termIndexEntries as $termIndexEntry ) {
			$entityIdString = $termIndexEntry->getEntityId()->getSerialization();
			$newSearchResult = $this->convertToSearchResult( $termIndexEntry, $aliasTermMap );

			// If there are no search results yet for this entityId then just add it
			if( !array_key_exists( $entityIdString, $searchResults ) ) {
				$searchResults[$entityIdString] = $newSearchResult;
				continue;
			}

			// Note: 5.4 STRICT dictates you cannot wrap array_keys in a function that attempts to reference the array
			$matchedTermsArrayKeys = array_keys( $newSearchResult[TermSearchInteractor::MATCHEDTERMS_KEY] );
			$newMatchedTermType = $matchedTermsArrayKeys[0];
			if( $newMatchedTermType == TermIndexEntry::TYPE_ALIAS ) {
				$newMatchedTerm = $newSearchResult[TermSearchInteractor::MATCHEDTERMS_KEY][$newMatchedTermType][0];
			} else {
				$newMatchedTerm = $newSearchResult[TermSearchInteractor::MATCHEDTERMS_KEY][$newMatchedTermType];
			}

			// Otherwise we must merge our matches into existing results
			if( !$this->matchedTermIsAlreadyInSearchResult( $newMatchedTerm, $newMatchedTermType, $searchResults[$entityIdString] ) ) {
				$searchResults[$entityIdString] = $this->addMatchedTermToSearchResult(
					$newMatchedTerm,
					$newMatchedTermType,
					$searchResults[$entityIdString]
				);
			}

		}
		// Remove the entityId keys so things don't rely on them
		return array_values( $searchResults );
	}

	/**
	 * @param Term $term
	 * @param string $termType
	 * @param array[] $searchResult
	 *
	 * @returns bool
	 */
	private function matchedTermIsAlreadyInSearchResult( Term $term, $termType, $searchResult ) {
		$matchedTerms = $searchResult[TermSearchInteractor::MATCHEDTERMS_KEY];
		if( array_key_exists( $termType, $matchedTerms ) ) {
			if( $termType !== TermIndexEntry::TYPE_ALIAS ) {
				return true;
			} elseif( in_array( $term, $matchedTerms[$termType] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param Term $term
	 * @param string $termType
	 * @param array[] $searchResult
	 *
	 * @return array[]
	 */
	private function addMatchedTermToSearchResult( Term $term, $termType, $searchResult ) {
		if( $termType == TermIndexEntry::TYPE_ALIAS ) {
			$searchResult[TermSearchInteractor::MATCHEDTERMS_KEY][$termType][] = $term;
		} else {
			$searchResult[TermSearchInteractor::MATCHEDTERMS_KEY][$termType] = $term;
		}
		return $searchResult;
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
	 * @param EntityId[] $entityIds
	 *
	 * @return TermIndexEntry[]
	 */
	private function fetchAllAliasesForDisplay( array $entityIds ) {
		return $this->termIndex->getTermsOfEntities(
			$entityIds,
			array( TermIndexEntry::TYPE_ALIAS ),
			array( $this->displayLanguageCode )
		);
	}

	/**
	 * @param TermIndexEntry[] $termIndexEntries
	 * @param string $text to match
	 *
	 * @return array[] Keys: Serialized entityIds, Values Term[]
	 */
	private function getAliasesTermMap( array $termIndexEntries, $text ) {
		$map = array();
		foreach( $termIndexEntries as $entry ) {
			$entityIdSerialization = $entry->getEntityId()->getSerialization();
			if( $entityIdSerialization !== null && $this->isTermIndexEntrySearchMatch( $entry, $text ) ) {
				$map[$entityIdSerialization][] = $entry->getTerm();
			}
		}
		return $map;
	}

	/**
	 * @param TermIndexEntry $entry
	 * @param string $text
	 *
	 * @return bool
	 */
	private function isTermIndexEntrySearchMatch( TermIndexEntry $entry, $text ) {
		$regex = '/^' . preg_quote( $text, '/' );
		if( $this->isPrefixSearch ) {
			$regex .= '.*';
		}
		$regex .= '$/';
		if( !$this->isCaseSensitive ) {
			$regex .= 'i';
		}
		if( preg_match( $regex, $entry->getText() ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param TermIndexEntry[] $termsIndexEntries
	 *
	 * @return EntityId[]
	 */
	private function getEntityIdsForTermIndexEntries( array $termsIndexEntries ) {
		$entityIds = array();
		foreach( $termsIndexEntries as $termIndexEntry ) {
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
	 * @param array[] $aliasTermMap
	 *
	 * @returns array
	 * @see TermSearchInteractor interface for return format
	 */
	private function convertToSearchResult( TermIndexEntry $termIndexEntry, array $aliasTermMap ) {
		$entityId = $termIndexEntry->getEntityId();
		$termType = $termIndexEntry->getType();

		if( $termType == TermIndexEntry::TYPE_ALIAS ) {
			$matchedTerms = array( $termType => array( $termIndexEntry->getTerm() ) );
		} else {
			$matchedTerms = array( $termType => $termIndexEntry->getTerm() );
		}

		$searchResult = array(
			TermSearchInteractor::ENTITYID_KEY => $entityId,
			TermSearchInteractor::MATCHEDTERMS_KEY => $matchedTerms,
			TermSearchInteractor::DISPLAYTERMS_KEY => $this->getDisplayTerms( $entityId, $aliasTermMap ),
		);
		return $searchResult;
	}

	private function getTermIndexOptions() {
		return array(
			'caseSensitive' => $this->isCaseSensitive,
			'prefixSearch' => $this->isPrefixSearch,
			'LIMIT' => $this->limit,
		);
	}

	/**
	 * @param array $languageCodes
	 *
	 * @return array
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
	 * @param array[] $aliasTermMap
	 *
	 * @return Term[] array with possible keys TermIndexEntry::TYPE_*
	 */
	private function getDisplayTerms( EntityId $entityId, array $aliasTermMap ) {
		$displayTerms = array();
		try{
			$displayTerms[TermIndexEntry::TYPE_LABEL] =
				$this->labelDescriptionLookup->getLabel( $entityId );
		} catch( OutOfBoundsException $e ) {
			// Ignore
		};
		try{
			$displayTerms[TermIndexEntry::TYPE_DESCRIPTION] =
				$this->labelDescriptionLookup->getDescription( $entityId );
		} catch( OutOfBoundsException $e ) {
			// Ignore
		};
		if( array_key_exists( $entityId->getSerialization(), $aliasTermMap ) ) {
			$displayTerms[TermIndexEntry::TYPE_ALIAS] = $aliasTermMap[$entityId->getSerialization()];
		}
		return $displayTerms;
	}

	/**
	 * @param string $text
	 * @param string[] $languageCodes
	 * @param string[] $termTypes
	 *
	 * @returns TermIndexEntry[]
	 */
	private function makeTermIndexEntryTemplates( $text, $languageCodes, $termTypes ) {
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
