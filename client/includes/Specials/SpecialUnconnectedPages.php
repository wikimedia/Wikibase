<?php

namespace Wikibase\Client\Specials;

use DatabaseBase;
use FakeResultWrapper;
use Html;
use Linker;
use MWNamespace;
use QueryPage;
use ResultWrapper;
use Skin;
use Title;
use Wikibase\Client\WikibaseClient;
use Wikibase\NamespaceChecker;

/**
 * List client pages that are not connected to repository items.
 *
 * @since 0.4
 * @licence GNU GPL v2+
 * @author John Erling Blad < jeblad@gmail.com >
 * @author Amir Sarabadani < ladsgroup@gmail.com >
 * @author Daniel Kinzler
 */
class SpecialUnconnectedPages extends QueryPage {

	/**
	 * @var int maximum supported offset
	 */
	const MAX_OFFSET = 10000;

	/**
	 * @var NamespaceChecker|null
	 */
	private $namespaceChecker = null;

	/**
	 * @see SpecialPage::__construct
	 *
	 * @param string $name
	 */
	public function __construct( $name = 'UnconnectedPages' ) {
		parent::__construct( $name );
	}

	/**
	 * @see QueryPage::isSyndicated
	 *
	 * @return bool Always false because we do not want to build RSS/Atom feeds for this page.
	 */
	public function isSyndicated() {
		return false;
	}

	/**
	 * @see QueryPage::isCacheable
	 *
	 * @return bool Always false because we can not have caching since we will store additional information.
	 */
	public function isCacheable() {
		return false;
	}

	/**
	 * @since 0.4
	 *
	 * @param NamespaceChecker $namespaceChecker
	 */
	public function setNamespaceChecker( NamespaceChecker $namespaceChecker ) {
		$this->namespaceChecker = $namespaceChecker;
	}

	/**
	 * @since 0.4
	 *
	 * @return NamespaceChecker
	 */
	public function getNamespaceChecker() {
		if ( $this->namespaceChecker === null ) {
			$settings = WikibaseClient::getDefaultInstance()->getSettings();

			$this->namespaceChecker = new NamespaceChecker(
				$settings->getSetting( 'excludeNamespaces' ),
				$settings->getSetting( 'namespaces' )
			);
		}

		return $this->namespaceChecker;
	}

	/**
	 * Build conditionals for namespace
	 *
	 * @since 0.4
	 *
	 * @param DatabaseBase $dbr
	 * @param Title|null $title
	 * @param NamespaceChecker|null $checker
	 *
	 * @return string[]
	 */
	public function buildConditionals( DatabaseBase $dbr, Title $title = null, NamespaceChecker $checker = null ) {
		$conds = array();

		if ( $checker === null ) {
			$checker = $this->getNamespaceChecker();
		}
		if ( $title !== null ) {
			$conds[] = 'page_title >= ' . $dbr->addQuotes( $title->getDBkey() );
			$conds[] = 'page_namespace = ' . (int)$title->getNamespace();
		}
		$wbNamespaces = $checker->getWikibaseNamespaces();
		$ns = $this->getRequest()->getIntOrNull( 'namespace' );
		if ( $ns !== null && in_array( $ns, $wbNamespaces ) ) {
			$conds[] = 'page_namespace = ' . $ns;
		} else {
			$conds[] = 'page_namespace IN (' . implode( ',', $wbNamespaces ) . ')';
		}

		return $conds;
	}

	/**
	 * @see QueryPage::getQueryInfo
	 *
	 * @return array[]
	 */
	public function getQueryInfo() {
		$dbr = wfGetDB( DB_SLAVE );

		$conds = $this->buildConditionals( $dbr );
		$conds['page_is_redirect'] = 0;
		$conds[] = 'pp_propname IS NULL';

		return array(
			'tables' => array(
				'page',
				'page_props',
			),
			'fields' => array(
				'value' => 'page_id',
				'namespace' => 'page_namespace',
				'title' => 'page_title',
				// Placeholder, we'll get this from page_props in the future.
				'page_num_iwlinks' => '0',
			),
			'conds' => $conds,
			// Sorting is determined getOrderFields(), which returns array( 'value' ) per default.
			'options' => array(),
			'join_conds' => array(
				// TODO Also get explicit_langlink_count from page_props once that is populated.
				// Could even filter or sort by it via pp_sortkey.
				'page_props' => array(
					'LEFT JOIN',
					array( 'page_id = pp_page', "pp_propname = 'wikibase_item'" )
				),
			)
		);
	}

	/**
	 * @see QueryPage::reallyDoQuery
	 *
	 * @param int|bool $limit
	 * @param int|bool $offset
	 *
	 * @return ResultWrapper
	 */
	public function reallyDoQuery( $limit, $offset = false ) {
		if ( is_int( $offset ) && $offset > self::MAX_OFFSET ) {
			return new FakeResultWrapper( array() );
		}

		return parent::reallyDoQuery( $limit, $offset );
	}

	/**
	 * @see QueryPage::formatResult
	 *
	 * @param Skin $skin
	 * @param object $result
	 *
	 * @return string
	 */
	public function formatResult( $skin, $result ) {
		// FIXME: This should use a TitleFactory.
		$title = Title::newFromID( $result->value );
		$out = Linker::linkKnown( $title );

		if ( $result->page_num_iwlinks > 0 ) {
			$out .= ' ' . $this->msg( 'wikibase-unconnectedpages-format-row' )
				->numParams( $result->page_num_iwlinks )->text();
		}

		return $out;
	}

	/**
	 * @see QueryPage::getPageHeader
	 *
	 * @return string
	 */
	public function getPageHeader() {
		$excludeNamespaces = array_diff(
			MWNamespace::getValidNamespaces(),
			$this->getNamespaceChecker()->getWikibaseNamespaces()
		);

		$limit = $this->getRequest()->getIntOrNull( 'limit' );
		$ns = $this->getRequest()->getIntOrNull( 'namespace' );

		return Html::openElement(
			'form',
			array(
				'action' => $this->getPageTitle()->getLocalURL()
			)
		) .
		( $limit === null ? '' : Html::hidden( 'limit', $limit ) ) .
		Html::namespaceSelector( array(
			'selected' => $ns === null ? '' : $ns,
			'all' => '',
			'exclude' => $excludeNamespaces,
			'label' => $this->msg( 'namespace' )->text()
		) ) . ' ' .
		Html::submitButton(
			$this->msg( 'wikibase-unconnectedpages-submit' )->text(),
			array()
		) .
		Html::closeElement( 'form' );
	}

	/**
	 * @see SpecialPage::getGroupName
	 *
	 * @return string
	 */
	protected function getGroupName() {
		return 'maintenance';
	}

	/**
	 * @see QueryPage::linkParameters
	 *
	 * @return array
	 */
	public function linkParameters() {
		return array(
			'namespace' => $this->getRequest()->getIntOrNull( 'namespace' ),
		);
	}

}
