<?php

namespace Wikibase\Client\Api;

use ApiBase;
use ApiQuery;
use ApiQueryGeneratorBase;
use ApiResult;
use ResultWrapper;
use Title;
use Wikibase\Client\RepoLinker;
use Wikibase\Client\Usage\EntityUsage;
use Wikibase\Client\WikibaseClient;

/**
 * API module to get the usage of entities.
 *
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Amir Sarabadani < ladsgroup@gmail.com >
 */
class ApiListEntityUsage extends ApiQueryGeneratorBase {

	/**
	 * @var RepoLinker|null
	 */
	private $repoLinker = null;

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 */
	public function __construct( ApiQuery $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'wbeu' );
		$this->repoLinker = WikibaseClient::getDefaultInstance()->newRepoLinker();
	}

	public function executeGenerator( $resultPageSet ) {
		$this->run( $resultPageSet );
	}

	public function execute() {
		$this->run();
	}

	/**
	 * @param ApiPageSet|null $resultPageSet
	 * @return void
	 */
	public function run( $resultPageSet = null ) {
		$params = $this->extractRequestParams();
		$res = $this->doQuery( $params, $resultPageSet );
		if ( !$res ) {
			return;
		}

		$prop = array_flip( (array)$params['prop'] );
		$this->formatResult( $res, $params['limit'], $prop, $resultPageSet );
	}

	/**
	 * @param object $row
	 * @return array
	 */
	private function addPageData( $row ) {
		$pageData = [];
		$title = Title::makeTitle( $row->page_namespace, $row->page_title );
		$this::addTitleInfo( $pageData, $title );
		$pageData['pageid'] = (int)$row->page_id;
		return $pageData;

	}

	/**
	 * @param ResultWrapper $res
	 * @param int $limit
	 * @param array $prop
	 * @param ApiPageSet|null $resultPageSet
	 */
	private function formatResult( ResultWrapper $res, $limit, array $prop, $resultPageSet ) {
		$currentPageId = null;
		$entry = [];
		$count = 0;
		$result = $this->getResult();

		foreach ( $res as $row ) {
			if ( ++$count > $limit ) {
				// We've reached the one extra which shows that
				// there are additional pages to be had. Stop here...
				$this->setContinueFromRow( $row );
				break;
			}

			if ( $resultPageSet !== null ) {
				$resultPageSet->processDbRow( $row );
			}

			if ( isset( $currentPageId ) && $row->eu_page_id !== $currentPageId ) {
				// Flush out everything we built
				$pageData = $this->addPageData( $prRow );
				$result->addValue( [ 'query', 'pages' ], intval( $currentPageId ), $pageData );
				$fit = $this->addPageSubItems( $currentPageId, $entry );
				if ( !$fit ) {
					$this->setContinueFromRow( $row );
					break;
				}
				$entry = [];
			}

			$currentPageId = $row->eu_page_id;
			$prRow = $row;
			if ( array_key_exists( $row->eu_entity_id, $entry ) ) {
				$entry[$row->eu_entity_id]['aspects'][] = $row->eu_aspect;
			} else {
				$entry[$row->eu_entity_id] = [ 'aspects' => [ $row->eu_aspect ] ];
				if ( isset( $prop['url'] ) ) {
					$entry[$row->eu_entity_id]['url'] = $this->repoLinker->getPageUrl(
						'Special:EntityData/' . $row->eu_entity_id );
				}
				ApiResult::setIndexedTagName(
					$entry[$row->eu_entity_id]['aspects'], 'aspect'
				);
				ApiResult::setArrayType( $entry, 'kvp', 'id' );
			}

		}
		if ( $entry ) { // Sanity
			// Flush out remaining ones
			$pageData = $this->addPageData( $row );
			$result->addValue( [ 'query', 'pages' ], intval( $currentPageId ), $pageData );
			$this->addPageSubItems( $currentPageId, $entry );
		}
	}

	/**
	 * @param object $row
	 */
	private function setContinueFromRow( $row ) {
		$this->setContinueEnumParameter(
			'continue',
			"{$row->eu_page_id}|{$row->eu_entity_id}|{$row->eu_aspect}"
		);
	}

	public function getCacheMode( $params ) {
		return 'public';
	}

	/**
	 * @param array $params
	 * @param ApiPageSet|null $resultPageSet
	 *
	 * @return ResultWrapper|null
	 */
	public function doQuery( array $params, $resultPageSet ) {
		if ( !$params['entities'] ) {
			return null;
		}

		$this->addFields( [
			'eu_page_id',
			'eu_entity_id',
			'eu_aspect'
		] );

		$this->addTables( 'wbc_entity_usage' );

		if ( $resultPageSet === null ) {
			$this->addFields( [ 'page_id', 'page_title', 'page_namespace' ] );
		} else {
			$this->addFields( $resultPageSet->getPageTableFields() );
		}

		$this->addTables( [ 'page' ] );
		$this->addJoinConds( [ 'wbc_entity_usage' => [ 'LEFT JOIN', 'eu_page_id=page_id' ] ] );

		$this->addWhereFld( 'eu_entity_id', $params['entities'] );

		if ( !is_null( $params['continue'] ) ) {
			$db = $this->getDB();
			$continueParams = explode( '|', $params['continue'] );
			$pageContinue = intval( $continueParams[0] );
			$entityContinue = $db->addQuotes( $continueParams[1] );
			$aspectContinue = $db->addQuotes( $continueParams[2] );
			// Filtering out results that has been shown already and
			// starting the query from where it ended.
			$this->addWhere(
				"eu_page_id > $pageContinue OR " .
				"(eu_page_id = $pageContinue AND " .
				"(eu_entity_id > $entityContinue OR " .
				"(eu_entity_id = $entityContinue AND " .
				"eu_aspect >= $aspectContinue)))"
			);
		}

		$orderBy = [ 'eu_page_id' , 'eu_entity_id' ];
		if ( isset( $params['aspect'] ) ) {
			$this->addWhereFld( 'eu_aspect', $params['aspect'] );
		} else {
			$orderBy[] = 'eu_aspect';
		}
		$this->addOption( 'ORDER BY', $orderBy );

		$this->addOption( 'LIMIT', $params['limit'] + 1 );
		$res = $this->select( __METHOD__ );
		return $res;
	}

	public function getAllowedParams() {
		return [
			'prop' => [
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => [
					'url',
				],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			'aspect' => [
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => [
					EntityUsage::SITELINK_USAGE,
					EntityUsage::LABEL_USAGE,
					EntityUsage::TITLE_USAGE,
					EntityUsage::ALL_USAGE,
					EntityUsage::OTHER_USAGE,
				]
			],
			'entities' => [
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_REQUIRED => true,
			],
			'limit' => [
				ApiBase::PARAM_DFLT => 10,
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			'continue' => [
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
		];
	}

	protected function getExamplesMessages() {
		return [
			'action=query&list=wbentityusagelist&wbleuentities=Q2'
				=> 'apihelp-query+wbentityusagelist-example-simple',
		];
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Wikibase/API#wbentityusage';
	}

}
