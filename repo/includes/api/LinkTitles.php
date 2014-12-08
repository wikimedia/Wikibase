<?php

namespace Wikibase\Api;

use ApiBase;
use ApiMain;
use SiteList;
use Status;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\SiteLink;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Summary;

/**
 * API module to associate two pages on two different sites with a Wikibase item.
 * Requires API write mode to be enabled.
 *
 * @since 0.1
 * @licence GNU GPL v2+
 * @author John Erling Blad < jeblad@gmail.com >
 * @author Adam Shorland
 */
class LinkTitles extends ApiWikibase {

	/**
	 * @var SiteLinkTargetProvider
	 */
	private $siteLinkTargetProvider;

	/**
	 * @since 0.5
	 *
	 * @var array
	 */
	protected $siteLinkGroups;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param string $modulePrefix
	 *
	 * @see ApiBase::__construct
	 */
	public function __construct( ApiMain $mainModule, $moduleName, $modulePrefix = '' ) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();

		$this->siteLinkTargetProvider = new SiteLinkTargetProvider(
			$wikibaseRepo->getSiteStore(),
			$wikibaseRepo->getSettings()->getSetting( 'specialSiteLinkGroups' )
		);

		$this->siteLinkGroups = $wikibaseRepo->getSettings()->getSetting( 'siteLinkGroups' );
	}

	/**
	 * Main method. Does the actual work and sets the result.
	 *
	 * @since 0.1
	 */
	public function execute() {
		wfProfileIn( __METHOD__ );

		$lookup = $this->getEntityRevisionLookup();

		$params = $this->extractRequestParams();
		$this->validateParameters( $params );

		// Sites are already tested through allowed params ;)
		$sites = $this->siteLinkTargetProvider->getSiteList( $this->siteLinkGroups );

		list( $fromSite, $fromPage ) = $this->getSiteAndNormalizedPageName(
			$sites,
			$params['fromsite'],
			$params['fromtitle']
		);
		list( $toSite, $toPage ) = $this->getSiteAndNormalizedPageName(
			$sites,
			$params['tosite'],
			$params['totitle']
		);

		$siteLinkCache = WikibaseRepo::getDefaultInstance()->getStore()->newSiteLinkCache();
		$fromId = $siteLinkCache->getItemIdForLink( $fromSite->getGlobalId(), $fromPage );
		$toId = $siteLinkCache->getItemIdForLink( $toSite->getGlobalId(), $toPage );

		$return = array();
		$flags = 0;
		$item = null;

		$summary = new Summary( $this->getModuleName() );
		$summary->addAutoSummaryArgs(
			$fromSite->getGlobalId() . ':' . $fromPage,
			$toSite->getGlobalId() . ':' . $toPage );

		//FIXME: use ChangeOps for consistency!

		// Figure out which parts to use and what to create anew
		if ( $fromId === null && $toId === null ) {
			// create new item
			$item = Item::newEmpty();
			$toLink = new SiteLink( $toSite->getGlobalId(), $toPage );
			$item->addSiteLink( $toLink );
			$return[] = $toLink;
			$fromLink = new SiteLink( $fromSite->getGlobalId(), $fromPage );
			$item->addSiteLink( $fromLink );
			$return[] = $fromLink;

			$flags |= EDIT_NEW;
			$summary->setAction( 'create' );
		}
		elseif ( $fromId === null && $toId !== null ) {
			// reuse to-site's item
			/** @var Item $item */
			$itemRev = $lookup->getEntityRevision( $toId );
			$item = $itemRev->getEntity();
			$fromLink = new SiteLink( $fromSite->getGlobalId(), $fromPage );
			$item->addSiteLink( $fromLink );
			$return[] = $fromLink;
			$summary->setAction( 'connect' );
		}
		elseif ( $fromId !== null && $toId === null ) {
			// reuse from-site's item
			/** @var Item $item */
			$itemRev = $lookup->getEntityRevision( $fromId );
			$item = $itemRev->getEntity();
			$toLink = new SiteLink( $toSite->getGlobalId(), $toPage );
			$item->addSiteLink( $toLink );
			$return[] = $toLink;
			$summary->setAction( 'connect' );
		}
		// we can be sure that $fromId and $toId are not null here
		elseif ( $fromId->equals( $toId ) ) {
			// no-op
			wfProfileOut( __METHOD__ );
			$this->dieError( 'Common item detected, sitelinks are both on the same item', 'common-item' );
		}
		else {
			// dissimilar items
			wfProfileOut( __METHOD__ );
			$this->dieError( 'No common item detected, unable to link titles' , 'no-common-item' );
		}

		$this->getResultBuilder()->addSiteLinks( $return, 'entity' );
		$status = $this->getAttemptSaveStatus( $item, $summary, $flags );
		$this->buildResult( $item, $status );
		wfProfileOut( __METHOD__ );
	}

	/**
	 * @param SiteList $sites
	 * @param string $site
	 * @param string $pageTitle
	 */
	private function getSiteAndNormalizedPageName( SiteList $sites, $site, $pageTitle ) {
		$siteObj = $sites->getSite( $site );
		$page = $siteObj->normalizePageName( $pageTitle );
		if( $page === false ) {
			$this->dieMessage( 'no-external-page', $site, $pageTitle );
		}
		return array( $siteObj, $page );
	}

	/**
	 * @param Item|null $item
	 * @param Summary $summary
	 * @param int $flags
	 * @return Status
	 */
	private function getAttemptSaveStatus( Item $item = null, Summary $summary, $flags ) {
		if ( $item === null ) {
			// to not have an Item isn't really bad at this point
			return Status::newGood( true );
		}
		else {
			// Do the actual save, or if it don't exist yet create it.
			return $this->attemptSaveEntity( $item,
				$summary,
				$flags );
		}
	}

	private function buildResult( Item $item = null, Status $status ) {
		if ( $item !== null ) {
			$this->getResultBuilder()->addRevisionIdFromStatusToResult( $status, 'entity' );
			$this->getResultBuilder()->addBasicEntityInformation( $item->getId(), 'entity' );
		}

		$this->getResultBuilder()->markSuccess( $status->isOK() );
	}

	/**
	 * @see ModifyEntity::validateParameters
	 */
	protected function validateParameters( array $params ) {
		if ( $params['fromsite'] === $params['tosite'] ) {
			$this->dieError( 'The from site can not match the to site' , 'param-illegal' );
		}

		if( !( strlen( $params['fromtitle'] ) > 0) || !( strlen( $params['totitle'] ) > 0) ){
			$this->dieError( 'The from title and to title must have a value' , 'param-illegal' );
		}
	}

	/**
	 * @see ApiBase::isWriteMode
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * Returns an array of allowed parameters (parameter name) => (default
	 * value) or (parameter name) => (array with PARAM_* constants as keys)
	 * Don't call this function directly: use getFinalParams() to allow
	 * hooks to modify parameters as needed.
	 * @return array|bool
	 */
	public function getAllowedParams() {
		$sites = $this->siteLinkTargetProvider->getSiteList( $this->siteLinkGroups );
		return array_merge( parent::getAllowedParams(), array(
			'tosite' => array(
				ApiBase::PARAM_TYPE => $sites->getGlobalIdentifiers(),
			),
			'totitle' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'fromsite' => array(
				ApiBase::PARAM_TYPE => $sites->getGlobalIdentifiers(),
			),
			'fromtitle' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'token' => null,
			'bot' => false,
		) );
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 *
	 * @return array
	 */
	protected function getExamplesMessages() {
		return array(
			'action=wblinktitles&fromsite=enwiki&fromtitle=Hydrogen&tosite=dewiki&totitle=Wasserstoff'
			=> 'apihelp-wblinktitles-example-1',
		);
	}

}
