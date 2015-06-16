<?php

namespace Wikibase\Api;

use ApiBase;
use ApiMain;
use OutOfBoundsException;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\ContentLanguages;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Lib\Store\LabelDescriptionLookup;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookup;
use Wikibase\Lib\Store\TermLookup;
use Wikibase\Repo\Interactors\TermIndexSearchInteractor;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\TermIndex;
use Wikibase\TermIndexEntry;

/**
 * API module to search for Wikibase entities.
 *
 * @since 0.2
 *
 * @licence GNU GPL v2+
 * @author John Erling Blad < jeblad@gmail.com >
 * @author Jens Ohlig < jens.ohlig@wikimedia.de >
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 * @author Thiemo Mättig
 * @author Adam Shorland
 */
class SearchEntities extends ApiBase {

	/**
	 * @var EntityTitleLookup
	 */
	private $titleLookup;

	/**
	 * @var EntityIdParser
	 */
	private $idParser;

	/**
	 * @var TermIndexSearchInteractor
	 */
	private $termIndexSearchInteractor;

	/**
	 * @var TermIndex
	 */
	private $termIndex;

	/**
	 * @var LabelDescriptionLookup
	 */
	private $labelDescriptionLookup;

	/**
	 * @var ContentLanguages
	 */
	private $termsLanguages;

	/**
	 * @var string[]
	 */
	private $entityTypes;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param string $modulePrefix
	 *
	 * @see ApiBase::__construct
	 */
	public function __construct( ApiMain $mainModule, $moduleName, $modulePrefix = '' ) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );

		$repo = WikibaseRepo::getDefaultInstance();
		$this->setServices(
			$repo->getEntityTitleLookup(),
			$repo->getEntityIdParser(),
			$repo->getEntityFactory()->getEntityTypes(),
			$repo->getTermsLanguages(),
			$repo->newTermSearchInteractor( $this->getLanguage()->getCode() ),
			$repo->getStore()->getTermIndex(),
			new LanguageFallbackLabelDescriptionLookup(
				$repo->getTermLookup(),
				$repo->getLanguageFallbackChainFactory()
				->newFromLanguageCode( $this->getLanguage()->getCode() )
			)
		);
	}

	/**
	 * Override services, for use for testing.
	 *
	 * @param EntityTitleLookup $titleLookup
	 * @param EntityIdParser $idParser
	 * @param array $entityTypes
	 * @param ContentLanguages $termLanguages
	 * @param TermIndexSearchInteractor $termIndexSearchInteractor
	 * @param TermIndex $termIndex
	 * @param LabelDescriptionLookup $labelDescriptionLookup
	 */
	public function setServices(
		EntityTitleLookup $titleLookup,
		EntityIdParser $idParser,
		array $entityTypes,
		ContentLanguages $termLanguages,
		TermIndexSearchInteractor $termIndexSearchInteractor,
		TermIndex $termIndex,
		LabelDescriptionLookup $labelDescriptionLookup
	) {
		$this->titleLookup = $titleLookup;
		$this->idParser = $idParser;
		$this->entityTypes = $entityTypes;
		$this->termsLanguages = $termLanguages;
		$this->termIndexSearchInteractor = $termIndexSearchInteractor;
		$this->termIndex = $termIndex;
		$this->labelDescriptionLookup = $labelDescriptionLookup;
	}

	/**
	 * Wrapper around TermSearchInteractor::searchForTerms
	 *
	 * @see TermSearchInteractor::searchForTerms
	 *
	 * @param string $text
	 * @param string $entityType
	 * @param string $languageCode
	 * @param int $limit
	 * @param bool $prefixSearch
	 * @param bool $strictlanguage
	 *
	 * @return array[]
	 */
	private function searchEntities( $text, $entityType, $languageCode, $limit, $prefixSearch, $strictlanguage ) {
		$this->termIndexSearchInteractor->setLimit( $limit );
		$this->termIndexSearchInteractor->setIsPrefixSearch( $prefixSearch );
		$this->termIndexSearchInteractor->setIsCaseSensitive( false );
		$this->termIndexSearchInteractor->setUseLanguageFallback( !$strictlanguage );
		return $this->termIndexSearchInteractor->searchForTerms(
			$text,
			array( $languageCode ),
			$entityType,
			array( TermIndexEntry::TYPE_LABEL, TermIndexEntry::TYPE_DESCRIPTION )
		);
	}

	/**
	 * Populates the search result returning the number of requested matches plus one additional
	 * item for being able to determine if there would be any more results.
	 * If there are not enough exact matches, the list of returned entries will be additionally
	 * filled with prefixed matches.
	 *
	 * @param array $params
	 *
	 * @return array[]
	 */
	private function getSearchEntries( array $params ) {
		$matches = $this->getRankedMatches(
			$params['search'],
			$params['type'],
			$params['language'],
			$params['continue'] + $params['limit'] + 1,
			$params['strictlanguage']
		);

		$entries = array();
		foreach ( $matches as $match ) {
			//TODO: use EntityInfoBuilder, EntityInfoTermLookup
			$title = $this->titleLookup->getTitleForId(
				$this->idParser->parse( $match['entityId'] )
			);
			$entry = array();;
			$entry['id'] = $match['entityId'];
			$entry['url'] = $title->getFullUrl();
			$entry = array_merge( $entry, $this->termsToArray( $match['displayTerms'] ) );
			$entry['matched'] = array_unique( $this->termsToArray( $match['matchedTerms'] ) );
			$entries[] = $entry;
		}
		return $entries;
	}

	/**
	 * @param Term[]|array[]|string[] $terms
	 *
	 * @return array[]
	 */
	private function termsToArray( array $terms ) {
		$termArray = array();
		foreach( $terms as $key => $term ) {
			if( $term instanceof Term ) {
				$termArray[$key] = $term->getText();
			} elseif ( is_array( $term ) ) {
				$termArray[$key] = $this->termsToArray( $term );
			} else {
				$termArray[$key] = $term;
			}
		}
		return $termArray;
	}

	/**
	 * Gets exact match for the search term as an EntityId if it can be found.
	 *
	 * @param string $term
	 * @param string $entityType
	 *
	 * @return EntityId|null
	 */
	private function getExactMatchForEntityId( $term, $entityType ) {
		try {
			$entityId = $this->idParser->parse( $term );
			$title = $this->titleLookup->getTitleForId( $entityId );

			if ( $title && $title->exists() && ( $entityId->getEntityType() === $entityType ) ) {
				return $entityId;
			}
		} catch ( EntityIdParsingException $ex ) {
			// never mind, doesn't look like an ID.
		}

		return null;
	}

	/**
	 * Gets exact matches. If there are not enough exact matches, it gets prefixed matches.
	 *
	 * @param string $text
	 * @param string $entityType
	 * @param string $languageCode
	 * @param int $limit
	 * @param bool $strictlanguage
	 *
	 * @return array[] Key: string Serialized EntityId
	 *           Value: array( displayTerms => Term[], matchedTerms => Term[] )
	 *           Note: displayTerms has possible keys Wikibase\TermIndexEntry::TYPE_*
	 *                 matchedTerms has no keys / integer keys only
	 */
	private function getRankedMatches( $text, $entityType, $languageCode, $limit, $strictlanguage ) {
		$allSearchResults = array();
		$entityIdMap = array();
		$nextSearchKey = 0;

		// If $text is the ID of an existing item, include it in the result.
		$entityId = $this->getExactMatchForEntityId( $text, $entityType );
		if ( $entityId !== null ) {
			$allSearchResults[$nextSearchKey] = array(
				'entityId' => $entityId->getSerialization(),
				'matchedTerms' => array( $entityId->getSerialization() ),
				'displayTerms' => $this->termsToArray( $this->getDisplayTerms( $entityId ) ),
			);
			$entityIdMap[$entityId->getSerialization()] = $nextSearchKey;
			$nextSearchKey++;
		}

		// If not matched enough then search for full term matches
		// If still not enough matched then search for prefix matches
		foreach( array( false, true ) as $prefixSearch ) {
			$missing = $limit - count( $allSearchResults );
			if ( $missing <= 0 ) {
				continue;
			}
			$searchResults = $this->searchEntities( $text, $entityType, $languageCode, $missing, $prefixSearch, $strictlanguage );
			foreach( $searchResults as $searchResult ) {
				/** @var EntityId $entityId */
				$entityId = $searchResult['entityId'];
				$entityIdString = $entityId->getSerialization();
				if( !array_key_exists( $entityIdString, $entityIdMap ) ) {
					$allSearchResults[$nextSearchKey] = array(
						'entityId' => $entityIdString,
						'matchedTerms' => array( $searchResult['matchedTerm'] ),
						'displayTerms' => $searchResult['displayTerms'],
					);
					$entityIdMap[$entityIdString] = $nextSearchKey;
					$nextSearchKey++;
				} else {
					$allSearchResults[$entityIdMap[$entityIdString]]['matchedTerms'][] = $searchResult['matchedTerm'];
				}
			}
		}
		return $allSearchResults;
	}

	/**
	 * @param EntityId $entityId
	 *
	 * @return Term[] array with possible keys TermIndexEntry::TYPE_*
	 */
	private function getDisplayTerms( EntityId $entityId ) {
		$displayTerms = array();
		try{
			$displayTerms[TermIndexEntry::TYPE_LABEL] = $this->labelDescriptionLookup->getLabel( $entityId );
		} catch( OutOfBoundsException $e ) {
			// Ignore
		};
		try{
			$displayTerms[TermIndexEntry::TYPE_DESCRIPTION] = $this->labelDescriptionLookup->getDescription( $entityId );
		} catch( OutOfBoundsException $e ) {
			// Ignore
		};
		$aliasTerms = $this->getTermsFromTermIndexEntries(
			$this->termIndex->getTermsOfEntity(
				$entityId,
				array( TermIndexEntry::TYPE_ALIAS ),
				array( $this->getLanguage()->getCode() )
			)
		);
		if( !empty( $aliasTerms ) ) {
			$displayTerms[TermIndexEntry::TYPE_ALIAS] = $aliasTerms;
		}
		return $displayTerms;
	}

	/**
	 * @param TermIndexEntry[] $termIndexEntries
	 *
	 * @return Term[]
	 */
	private function getTermsFromTermIndexEntries( array $termIndexEntries ) {
		$terms = array();
		foreach( $termIndexEntries as $indexEntry ) {
			$terms[] = $indexEntry->getTerm();
		}
		return $terms;
	}

	/**
	 * @see ApiBase::execute()
	 */
	public function execute() {
		$params = $this->extractRequestParams();

		$entries = $this->getSearchEntries( $params );

		$this->getResult()->addValue(
			null,
			'searchinfo',
			array(
				'search' => $params['search']
			)
		);

		$this->getResult()->addValue(
			null,
			'search',
			array()
		);

		// getSearchEntities returns one more item than requested in order to determine if there
		// would be any more results coming up.
		$hits = count( $entries );

		// Actual result set.
		$entries = array_slice( $entries, $params['continue'], $params['limit'] );

		$nextContinuation = $params['continue'] + $params['limit'];

		// Only pass search-continue param if there are more results and the maximum continuation
		// limit is not exceeded.
		if ( $hits > $nextContinuation && $nextContinuation <= ApiBase::LIMIT_SML1 ) {
			$this->getResult()->addValue(
				null,
				'search-continue',
				$nextContinuation
			);
		}

		$this->getResult()->addValue(
			null,
			'search',
			$entries
		);

		$this->getResult()->addIndexedTagName( array( 'search' ), 'entity' );

		// @todo use result builder?
		$this->getResult()->addValue(
			null,
			'success',
			(int)true
		);
	}

	/**
	 * @see ApiBase::getAllowedParams
	 */
	protected function getAllowedParams() {
		return array(
			'search' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
			'language' => array(
				ApiBase::PARAM_TYPE => $this->termsLanguages->getLanguages(),
				ApiBase::PARAM_REQUIRED => true,
			),
			'strictlanguage' => array(
				ApiBase::PARAM_TYPE => 'boolean',
				ApiBase::PARAM_DFLT => false
			),
			'type' => array(
				ApiBase::PARAM_TYPE => $this->entityTypes,
				ApiBase::PARAM_DFLT => 'item',
			),
			'limit' => array(
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_DFLT => 7,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_SML1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_SML2,
				ApiBase::PARAM_MIN => 0,
				ApiBase::PARAM_RANGE_ENFORCE => true,
			),
			'continue' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => false,
			),
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages
	 */
	protected function getExamplesMessages() {
		return array(
			'action=wbsearchentities&search=abc&language=en' => 'apihelp-wbsearchentities-example-1',
			'action=wbsearchentities&search=abc&language=en&limit=50' => 'apihelp-wbsearchentities-example-2',
			'action=wbsearchentities&search=alphabet&language=en&type=property' => 'apihelp-wbsearchentities-example-3',
		);
	}

}
