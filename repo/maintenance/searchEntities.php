<?php

namespace Wikibase;

use CirrusSearch\Maintenance\OrderedStreamingForkController;
use Maintenance;
use RequestContext;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookup;
use Wikibase\Repo\Api\EntitySearcher;
use Wikibase\Repo\Api\EntitySearchHelper;
use Wikibase\Repo\Search\Elastic\EntitySearchElastic;
use Wikibase\Repo\WikibaseRepo;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../../..';

require_once $basePath . '/maintenance/Maintenance.php';

/**
 * The script is intended to run searches in the same way as wbsearchentities does.
 * This is mainly intended to test configurations and search options using relforge
 * or analogous tools. It is modeled after runSearch.php script in CirrusSearch extension.
 *
 * The script accepts search requests from stdin, line by line,
 * and outputs results, preserving order.
 */
class SearchEntities extends Maintenance {

	/**
	 * @var EntitySearcher
	 */
	private $searchHelper;

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Search entity a-la wbsearchentities API.' );

		$this->addOption( 'entity-type', "Only search this kind of entity, e.g. `item` or `property`.", true, true );
		$this->addOption( 'limit', "Limit how many results are returned. Default is 5.", false, true );
		$this->addOption( 'language', "Language for the search.", true, true );
		$this->addOption( 'display-language', "Language for the display.", false, true );
		$this->addOption( 'strict', "Should we use strict language match?", false, true );
		$this->addOption( 'engine', "Which engine to use - e.g. sql, elastic.", false, true );
		$this->addOption( 'fork', 'Fork multiple processes to run queries from.' .
		                          'defaults to false.', false, true );
		$this->addOption( 'options', 'A JSON object mapping from global variable to ' .
		                             'its test value', false, true );
	}

	/**
	 * Do the actual work. All child classes will need to implement this
	 */
	public function execute() {
		$engine = $this->getOption( 'engine', 'sql' );
		$this->searchHelper = $this->getSearchHelper( $engine );

		$callback = [ $this, 'doSearch' ];
		$this->applyGlobals();
		$forks = $this->getOption( 'fork', false );
		$forks = ctype_digit( $forks ) ? intval( $forks ) : 0;
		$controller = new OrderedStreamingForkController( $forks, $callback, STDIN, STDOUT );
		$controller->start();
	}

	/**
	 * Applies global variables provided as the options CLI argument
	 * to override current settings.
	 */
	protected function applyGlobals() {
		$optionsData = $this->getOption( 'options', 'false' );
		if ( substr_compare( $optionsData, 'B64://', 0, strlen( 'B64://' ) ) === 0 ) {
			$optionsData = base64_decode( substr( $optionsData, strlen( 'B64://' ) ) );
		}
		$options = json_decode( $optionsData, true );
		if ( $options ) {
			foreach ( $options as $key => $value ) {
				if ( array_key_exists( $key, $GLOBALS ) ) {
					$GLOBALS[$key] = $value;
				} else {
					$this->error( "\nERROR: $key is not a valid global variable\n" );
					exit();
				}
			}
		}
	}

	/**
	 * Run search for one query.
	 * @param string $query
	 * @return string
	 */
	public function doSearch( $query ) {
		$limit = (int)$this->getOption( 'limit', 5 );

		$results = $this->searchHelper->getRankedSearchResults(
			$query,
			$this->getOption( 'language' ),
			$this->getOption( 'entity-type' ),
			$limit,
			$this->getOption( 'strict', false )
		);
		$out = [
			'query' => $query,
		    'totalHits' => count($results),
			'rows' => []
		];

		foreach ( $results as $match ) {
			$entityId = $match->getEntityId();

			$out['rows'][] = [
				'pageId' => $entityId->getSerialization(),
				'title' => $entityId->getSerialization(),
				'snippets' => [
					'term' => $match->getMatchedTerm()->getText(),
					'type' => $match->getMatchedTermType(),
					'title' => $match->getDisplayLabel()->getText(),
					'text' => $match->getDisplayDescription()->getText(),
				]
			];
		}
		return json_encode( $out ) . "\n";
	}

	/**
	 * Get appropriate searcher.
	 * @param string $engine
	 * @return EntitySearcher
	 * @throws \MWException
	 */
	private function getSearchHelper( $engine ) {
		$repo = WikibaseRepo::getDefaultInstance();
		$settings = $repo->getSettings()->getSetting( 'entitySearch' );

		switch ( $engine ) {
			case 'sql':
				return new EntitySearchHelper(
					$repo->getEntityLookup(),
					$repo->getEntityIdParser(),
					$repo->newTermSearchInteractor( $repo->getUserLanguage()->getCode() ),
					new LanguageFallbackLabelDescriptionLookup(
						$repo->getTermLookup(),
						$repo->getLanguageFallbackChainFactory()->newFromLanguage( $repo->getUserLanguage() )
					)
				);
			case 'elastic':
				$lang = $repo->getUserLanguage();
				return new EntitySearchElastic(
					$repo->getLanguageFallbackChainFactory(),
					$repo->getEntityIdParser(),
					$lang,
					$repo->getContentModelMappings(),
					RequestContext::getMain()->getRequest(),
					$settings
				);
			default:
				throw new \MWException( "Unknown engine: $engine" );
		}

	}
}

$maintClass = SearchEntities::class;
require_once RUN_MAINTENANCE_IF_MAIN;
