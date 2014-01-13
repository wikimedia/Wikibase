<?php

namespace Wikibase;
use Wikibase\DataModel\SimpleSiteLink;
use Wikibase\Repo\WikibaseRepo;

/**
 * Job for updating the repo after a page on the client has been moved.
 *
 * This needs to be in lib as the client needs it for injecting it and
 * the repo to execute it.
 *
 * @since 0.4
 *
 * @licence GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 */
class UpdateRepoOnMoveJob extends \Job {

	/**
	 * Constructs a UpdateRepoOnMoveJob propagating a page move to the repo
	 *
	 * @note: This is for use by Job::factory, don't call it directly;
	 *           use newFrom*() instead.
	 *
	 * @note: the constructor's signature is dictated by Job::factory, so we'll have to
	 *           live with it even though it's rather ugly for our use case.
	 *
	 * @see Job::factory.
	 *
	 * @param \Title $title Ignored
	 * @param array|bool $params
	 * @param integer $id
	 */
	public function __construct( \Title $title, $params = false, $id = 0 ) {
		parent::__construct( 'UpdateRepoOnMove', $title, $params, $id );
	}

	/**
	 * Creates a UpdateRepoOnMoveJob representing the given move.
	 *
	 * @param \Title $oldTitle
	 * @param \Title $newTitle
	 * @param EntityId entityId
	 * @param \User $user User who moved the page
	 * @param string $globalId Global id of the site from which the is coming
	 * @param array|bool $params extra job parameters, see Job::__construct (default: false).
	 *
	 * @return \Wikibase\UpdateRepoOnMoveJob: the job
	 */
	public static function newFromMove( $oldTitle, $newTitle, $entityId, $user, $globalId, $params = false ) {
		wfProfileIn( __METHOD__ );

		if ( $params === false ) {
			$params = array();
		}

		$params['siteId'] = $globalId;
		$params['entityId'] = $entityId;
		$params['oldTitle'] = $oldTitle->getPrefixedText();
		$params['newTitle'] = $newTitle->getPrefixedText();
		$params['user'] = $user->getName();

		// The Title object isn't really being used but \Job demands it... so we just insert something
		// A Title belonging to the entity on the repo would be more sane, but it doesn't really matter
		$job = new self( $newTitle, $params );

		wfProfileOut( __METHOD__ );
		return $job;
	}

	/**
	 * Get an EntityContentFactory object
	 *
	 * @return EntityContentFactory
	 */
	protected function getEntityContentFactory() {
		return WikibaseRepo::getDefaultInstance()->getEntityContentFactory();
	}

	/**
	 * Get a Site object for a global id
	 *
	 * @param string $globalId
	 *
	 * @return \Site
	 */
	protected function getSite( $globalId ) {
		$sitesTable = \SiteSQLStore::newInstance();
		return $sitesTable->getSite( $globalId );
	}

	/**
	 * Get a SimpleSiteLink for a specific item and site
	 *
	 * @param Item $item
	 * @param string $globalId
	 *
	 * @return SimpleSiteLink|null
	 */
	protected function getSimpleSiteLink( $item, $globalId ) {
		try {
			return $item->getSiteLink( $globalId );
		} catch( \OutOfBoundsException $e ) {
			return null;
		}
	}

	/**
	 * Get a Summary object for the edit
	 *
	 * @param string $globalId Global id of the target site
	 * @param string $oldPage
	 * @param string $newPage
	 *
	 * @return Summary
	 */
	public function getSummary( $globalId, $oldPage, $newPage ) {
		return new Summary(
			'clientsitelink',
			'update',
			$globalId,
			array(
				$globalId . ":$oldPage",
				$globalId . ":$newPage",
			)
		);
	}

	/**
	 * Update the siteLink on the repo to reflect the change in the client
	 *
	 * @param string $siteId Id of the client the change comes from
	 * @param EntityId $entityId
	 * @param string $oldPage
	 * @param string $newPage
	 * @param \User $user User who we'll attribute the update to
	 *
	 * @return bool Whether something changed
	 */
	public function updateSiteLink( $siteId, $entityId, $oldPage, $newPage, $user ) {
		wfProfileIn( __METHOD__ );

		$itemContent = $this->getEntityContentFactory()->getFromId( $entityId );
		if ( !$itemContent ) {
			// The entity assigned with the moved page can't be found
			wfDebugLog( __CLASS__, __FUNCTION__ . ": entity with id " . $entityId->getPrefixedId() . " not found" );
			wfProfileOut( __METHOD__ );
			return false;
		}

		$editEntity = new EditEntity( $itemContent, $user, true );

		$site = $this->getSite( $siteId );

		$item = $itemContent->getItem();
		$oldSiteLink = $this->getSimpleSiteLink( $item, $siteId );
		if ( !$oldSiteLink || $oldSiteLink->getPageName() !== $oldPage ) {
			// Probably something changed since the job has been inserted
			wfDebugLog( __CLASS__, __FUNCTION__ . ": The site link to " . $siteId . " is no longer $oldPage" );
			wfProfileOut( __METHOD__ );
			return false;
		}

		// Normalize the name again, just in case the page has been updated in the mean time
		$newPage = $site->normalizePageName( $newPage );
		if ( !$newPage ) {
			wfDebugLog( __CLASS__, __FUNCTION__ . ": Normalizing the page name $newPage failed" );
			wfProfileOut( __METHOD__ );
			return false;
		}

		$siteLink = new SimpleSiteLink(
			$siteId,
			$newPage
		);

		$summary = $this->getSummary( $siteId, $oldPage, $newPage );

		return $this->doUpdateSiteLink( $itemContent, $siteLink, $editEntity, $summary, $user );
	}

	/**
	 * Update the given item with the given sitelink
	 *
	 * @param ItemContent $itemContent
	 * @param Wikibase\DataModel\SimpleSiteLink $siteLink
	 * @param EditEntity $editEntity
	 * @param Summary $summary
	 * @param \User $user User who we'll attribute the update to
	 *
	 * @return bool Whether something changed
	 */
	public function doUpdateSiteLink( $itemContent, $siteLink, $editEntity, $summary, $user ) {
		$item = $itemContent->getItem();

		$item->addSiteLink( $siteLink );

		//NOTE: Temporary hack to avoid more dependency mess.
		//      The Right Thing would be to use a SummaryFormatter.
		//      This is fixed in a follow-up.
		$commentArgs = implode( '|', $summary->getCommentArgs() );
		$autoComment = '/* ' . $summary->getMessageKey()
			. ':2|' . $summary->getLanguageCode()
			. '|' . $commentArgs . ' */';

		$status = $editEntity->attemptSave(
			$autoComment,
			EDIT_UPDATE,
			false,
			// Don't (un)watch any pages here, as the user didn't explicitly kick this off
			$user->isWatched( $itemContent->getTitle() )
		);

		wfProfileOut( __METHOD__ );

		// TODO: Analyze what happened and let the user know in case a manual fix could be needed
		return $status->isOK();
	}

	/**
	 * Run the job
	 *
	 * @return boolean success
	 */
	public function run() {
		wfProfileIn( __METHOD__ );
		$params = $this->getParams();

		$user = \User::newFromName( $params['user'] );
		if ( !$user || !$user->isLoggedIn() ) {
			// This should never happen as we check with CentralAuth
			// that the user actually does exist
			wfLogWarning( 'User ' . $params['user'] . " doesn't exist while CentralAuth pretends it does" );
			wfProfileOut( __METHOD__ );
			return true;
		}

		$this->updateSiteLink(
			$params['siteId'],
			$params['entityId'],
			$params['oldTitle'],
			$params['newTitle'],
			$user
		);

		wfProfileOut( __METHOD__ );
		return true;
	}
}
