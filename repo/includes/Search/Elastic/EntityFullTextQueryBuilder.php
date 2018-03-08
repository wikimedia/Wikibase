<?php
namespace Wikibase\Repo\Search\Elastic;

use CirrusSearch\Query\FullTextQueryBuilder;
use CirrusSearch\Search\SearchContext;
use Elastica\Query\BoolQuery;
use Elastica\Query\DisMax;
use Elastica\Query\Match;
use Elastica\Query\Term;
use Wikibase\LanguageFallbackChain;
use Wikibase\Repo\WikibaseRepo;

/**
 * Builder for entity fulltext queries
 */
class EntityFullTextQueryBuilder implements FullTextQueryBuilder {

	/**
	 * @var array
	 */
	private $settings;
	/**
	 * This is regular fulltext builder which we'll use
	 * if we can't use the main one.
	 * @var FullTextQueryBuilder
	 */
	private $delegate;
	/**
	 * @var WikibaseRepo
	 */
	private $repo;
	/**
	 * Repository 'entitySearch' settings
	 * @var array
	 */
	private $searchSettings;

	/**
	 * @param FullTextQueryBuilder $delegate
	 * @param array $settings Settings from EntitySearchProfiles.php
	 * @param WikibaseRepo $repo
	 */
	public function __construct( FullTextQueryBuilder $delegate, array $settings, WikibaseRepo $repo ) {
		$this->repo = $repo;
		$repoSettings = $this->repo->getSettings();
		$this->searchSettings = $repoSettings->getSetting( 'entitySearch' );
		$this->settings = $settings;
		$this->delegate = $delegate;
	}

	/**
	 * Search articles with provided term.
	 *
	 * @param SearchContext $searchContext
	 * @param string $term term to search
	 * @param bool $showSuggestion should this search suggest alternative
	 * searches that might be better?
	 * @throws \MWException
	 */
	public function build( SearchContext $searchContext, $term, $showSuggestion ) {
		$lookup = $this->repo->getEntityNamespaceLookup();

		$entityNs = [];
		$articleNs = [];
		foreach ( $searchContext->getNamespaces() as $ns ) {
			if ( $lookup->isEntityNamespace( (int)$ns ) ) {
				$entityNs[] = $ns;
			} else {
				$articleNs[] = $ns;
			}
		}
		if ( empty( $entityNs ) ) {
			$this->delegate->build( $searchContext, $term, $showSuggestion );
			return;
		}
		// TODO: if we have a mix here of article & entity namespaces, the search may not work
		// very well here. Right now we're just forcing it to entity space. We may want to look
		// for a better solution.
		$searchContext->setNamespaces( $entityNs );

		// TODO: eventually we should deal with combined namespaces, probably running
		// a union of entity query for entity namespaces and delegate query for article namespaces
		$this->buildEntitySearch( $this->repo, $searchContext, $term );
	}

	/**
	 * Set up entity search query
	 * @param WikibaseRepo $repo
	 * @param SearchContext $searchContext
	 * @param $term
	 * @throws \MWException
	 */
	protected function buildEntitySearch( WikibaseRepo $repo, SearchContext $searchContext, $term ) {
		$languageCode = $repo->getUserLanguage()->getCode();
		$langChain = $repo->getLanguageFallbackChainFactory()->newFromLanguageCode( $languageCode );

		// Try the old builder first.
		$this->delegate->build( $searchContext, $term, false );
		if ( $searchContext->areResultsPossible() && !$searchContext->isSpecialKeywordUsed() ) {
			// We use entity search query if we did not find any advanced syntax
			// and the base builder did not reject the query
			$this->buildEntitySearchQuery( $searchContext, $term, $languageCode, $langChain );
		}
		// if we did find advanced query, we keep the old setup but change the result type
		$searchContext->setResultsType( new EntityResultType( $languageCode, $langChain ) );
	}

	/**
	 * Build a fulltext query for Wikibase entity.
	 * @param SearchContext $searchContext
	 * @param string $term Search term
	 * @param string $languageCode Display language
	 * @param LanguageFallbackChain $langChain Language fallback chain for search language
	 */
	protected function buildEntitySearchQuery( SearchContext $searchContext, $term, $languageCode,
												LanguageFallbackChain $langChain ) {
		$searchContext->setProfileContext( EntitySearchElastic::CONTEXT_WIKIBASE_FULLTEXT );
		$searchContext->addSyntaxUsed( 'entity_full_text', 10 );
		/*
		 * Overall query structure is as follows:
		 * - Bool with:
		 *   Filter of namespace = N
		 *   OR (Should with 1 mininmum) of:
		 *     title.keyword = QUERY
		 *     fulltext match query
		 *
		 * Fulltext match query is:
		 *   Filter of:
		 *      at least one of: all, all.plain matching
		 *      description (for stemmed) or description.en (for non-stemmed) matching, with fallback
		 *   OR (should with 0 minimum) of:
		 *     DISMAX query of: all labels.near_match in fallback chain
		 *     OR (should with 0 minimum) of:
		 *        all
		 *        all.plain
		 *        DISMAX of: all fulltext matches for tokenized fields
		 */

		$profile = $this->settings;
		// $fields is collecting all the fields for dismax query to be used in
		// scoring match
		$fields = [
			[ "labels.{$languageCode}.near_match", $profile['lang-exact'] ],
			[ "labels.{$languageCode}.near_match_folded", $profile['lang-folded'] ],
		];

		if ( empty( $this->searchSettings['useStemming'][$languageCode]['query'] ) ) {
			$fieldsTokenized = [
				[ "labels.{$languageCode}.plain", $profile['lang-partial'] ],
				[ "descriptions.{$languageCode}.plain", $profile['lang-partial'] ],
			];
		} else {
			$fieldsTokenized = [
				[ "descriptions.{$languageCode}", $profile['lang-partial'] ],
				[ "labels.{$languageCode}.plain", $profile['lang-partial'] ],
				[ "descriptions.{$languageCode}.plain", $profile['lang-partial'] ],
			];
		}

		$searchLanguageCodes = $langChain->getFetchLanguageCodes();

		$discount = $profile['fallback-discount'];
		$stemFilterFields = [];

		foreach ( $searchLanguageCodes as $fallbackCode ) {
			if ( empty( $this->searchSettings['useStemming'][$fallbackCode]['query'] ) ) {
				$stemFilterFields[] = "descriptions.{$fallbackCode}.plain";
			} else {
				$stemFilterFields[] = "descriptions.{$fallbackCode}";
			}

			if ( $fallbackCode === $languageCode ) {
				continue;
			}

			$weight = $profile['fallback-exact'] * $discount;
			$fields[] = [ "labels.{$fallbackCode}.near_match", $weight ];

			$weight = $profile['fallback-folded'] * $discount;
			$fields[] = [ "labels.{$fallbackCode}.near_match_folded", $weight ];

			$weight = $profile['fallback-partial'] * $discount;
			$fieldsTokenized[] = [ "labels.{$fallbackCode}.plain", $weight ];
			if ( empty( $this->searchSettings['useStemming'][$fallbackCode]['query'] ) ) {
				$fieldsTokenized[] = [ "descriptions.{$fallbackCode}.plain", $weight ];
			} else {
				$fieldsTokenized[] = [ "descriptions.{$fallbackCode}", $weight ];
				$fieldsTokenized[] = [ "descriptions.{$fallbackCode}.plain", $weight ];
			}

			$discount *= $profile['fallback-discount'];
		}

		$titleMatch = new Term( [
				'title.keyword' => EntitySearchUtils::normalizeId( $term,
					$this->repo->getEntityIdParser() )
		] );

		// Main query filter
		$filterQuery = $this->buildSimpleAllFilter( $term );
		foreach ( $stemFilterFields as $filterField ) {
			$filterQuery->addShould( $this->buildFieldMatch( $filterField, $term, 'AND' ) );
		}

		// Near match ones, they use constant score
		$nearMatchQuery = new DisMax();
		$nearMatchQuery->setTieBreaker( 0 );
		foreach ( $fields as $field ) {
			$nearMatchQuery->addQuery( EntitySearchUtils::makeConstScoreQuery( $field[0], $field[1], $term ) );
		}

		// Tokenized ones
		$tokenizedQuery = $this->buildSimpleAllFilter( $term, 'OR', $profile['any'] );
		$tokenizedQueryFields = new DisMax();
		$tokenizedQueryFields->setTieBreaker( 0.2 );
		foreach ( $fieldsTokenized as $field ) {
			$m = $this->buildFieldMatch( $field[0], $term );
			$m->setFieldBoost( $field[0], $field[1] );
			$tokenizedQueryFields->addQuery( $m );
		}
		$tokenizedQuery->addShould( $tokenizedQueryFields );

		// Main labels/desc query
		$labelsDescQuery = new BoolQuery();
		$labelsDescQuery->addFilter( $filterQuery );
		$labelsDescQuery->addShould( $nearMatchQuery );
		$labelsDescQuery->addShould( $tokenizedQuery );

		// Main query
		$query = new BoolQuery();
		$query->setParam( 'disable_coord', true );

		// Match either labels or exact match to title
		$query->addShould( $titleMatch );
		$query->addShould( $labelsDescQuery );
		$query->setMinimumShouldMatch( 1 );

		$searchContext->setMainQuery( $query );
		// setup results type
		$searchContext->setResultsType( new EntityResultType( $languageCode, $langChain ) );
	}

	/**
	 * @param SearchContext $searchContext
	 * @return bool
	 */
	public function buildDegraded( SearchContext $searchContext ) {
		return $this->delegate->buildDegraded( $searchContext );
	}

	/**
	 * Builds a simple filter on all and all.plain when all terms must match
	 *
	 * @param string $query
	 * @param string $operator
	 * @param null $boost
	 * @return BoolQuery
	 */
	private function buildSimpleAllFilter( $query, $operator = 'AND', $boost = null ) {
		$filter = new BoolQuery();
		// FIXME: We can't use solely the stem field here
		// - Depending on languages it may lack stopwords,
		// A dedicated field used for filtering would be nice
		foreach ( [ 'all', 'all.plain' ] as $field ) {
			$m = new Match();
			$m->setFieldQuery( $field, $query );
			$m->setFieldOperator( $field, $operator );
			if ( $boost ) {
				$m->setFieldBoost( $field, $boost );
			}
			$filter->addShould( $m );
		}
		return $filter;
	}

	/**
	 * Build simple match clause, matching field against term
	 * @param string $field
	 * @param string $term
	 * @param string|null $operator
	 * @return Match
	 */
	private function buildFieldMatch( $field, $term, $operator = null ) {
		$m = new Match();
		$m->setFieldQuery( $field, $term );
		if ( $operator ) {
			$m->setFieldOperator( $field, $operator );
		}
		return $m;
	}

}
