<?php

namespace Wikibase\Client\Api;

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use ApiResult;
use Wikibase\Client\WikibaseClient;
use Wikibase\Client\Usage\EntityUsage;

/**
 * API module to get the usage of entities.
 *
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Amir Sarabadani < ladsgroup@gmail.com >
 */
class ApiPropsEntityUsage extends ApiQueryBase {

	public function __construct( ApiQuery $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'wbeu' );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$res = $this->doQuery( $params );
		$count = 0;
		$prop = array_flip( (array)$params['prop'] );
		$wikibaseClient = WikibaseClient::getDefaultInstance();
		$repoLinker = $wikibaseClient->newRepoLinker();
		foreach ( $res as $row ) {
			if ( ++$count > $params['limit'] ) {
				// We've reached the one extra which shows that
				// there are additional pages to be had. Stop here...
				$this->setContinueFromRow( $row );
				break;
			}
			$entry = [ 'aspect' => $row->eu_aspect ];
			if ( isset( $prop['url'] ) ) {
				$entry['url'] = $repoLinker->getPageUrl( 'Special:EntityData/' . $row->eu_entity_id );
			}
			ApiResult::setContentValue( $entry, 'entity', $row->eu_entity_id );
			$fit = $this->addPageSubItem( $row->eu_page_id, $entry );
			if ( !$fit ) {
				$this->setContinueFromRow( $row );
				break;
			}
		}
	}

	private function setContinueFromRow( $row ) {
		$this->setContinueEnumParameter(
			'continue',
			"{$row->eu_page_id}|{$row->eu_entity_id}|{$row->eu_aspect}"
		);
	}

	public function getCacheMode( $params ) {
		return 'public';
	}

	public function doQuery( $params ) {
		$pages = $this->getPageSet()->getGoodTitles();
		$pagesCount = count( $pages );
		if ( $pagesCount === 0 ) {
			return;
		}

		$this->addFields( [
			'eu_page_id',
			'eu_entity_id',
			'eu_aspect'
		] );

		$this->addTables( 'wbc_entity_usage' );
		if ( $pagesCount !== 0 ) {
			$this->addWhereFld( 'eu_page_id', array_keys( $pages ) );
		} else {
			$this->addTables( 'page' );
			$this->addJoinConds( [ 'wbc_entity_usage' => [ 'INNER JOIN', 'eu_page_id=page_id'] ] );
			$this->addFields( [
				'page_title',
				'page_namespace',
			] );
		}

		if ( isset( $params['entities'] ) ) {
			$this->addWhereFld( 'eu_entity_id',  $params['entities'] );
		}

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
			'action=query&prop=wbentityusage&titles=Main%20Page'
				=> 'apihelp-query+wbentityusage-example-simple',
		];
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/API:Wikibase/EntityUsage';
	}

}
