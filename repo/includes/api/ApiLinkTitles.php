<?php

namespace Wikibase;
use ApiBase, User, Http, Status;

/**
 * API module to associate two pages on two different sites with a Wikibase item .
 * Requires API write mode to be enabled.
 *
 * @since 0.1
 *
 * @file ApiWikibaseLinkSite.php
 * @ingroup Wikibase
 * @ingroup API
 *
 * @licence GNU GPL v2+
 */
class ApiLinkTitles extends Api {

	/**
	 * @see  ApiModifyItem::getRequiredPermissions()
	 */
	protected function getRequiredPermissions( Item $item, array $params ) {
		$permissions = parent::getRequiredPermissions( $item, $params );

		$permissions[] = 'linktitles-update';
		return $permissions;
	}

	/**
	 * Main method. Does the actual work and sets the result.
	 *
	 * @since 0.1
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		//print($params["fromsite"]."\n");
		$user = $this->getUser();
		$this->flags = 0;

		if ( $params['gettoken'] ) {
			$this->addTokenToResult( $user->getEditToken() );
			$this->getResult()->addValue( null, 'success', (int)true );
			return;
		}

		// This is really already done with needsToken()
		if ( $this->needsToken() && !$user->matchEditToken( $params['token'] ) ) {
			$this->dieUsage( wfMsg( 'wikibase-api-session-failure' ), 'session-failure' );
		}

		if ( $params['fromsite'] === $params['tosite'] ) {
			$this->dieUsage( wfMsg( 'wikibase-api-fromsite-eq-tosite' ), 'fromsite-eq-tosite' );
		}

		if ( !( strlen( $params['fromtitle'] ) > 0 && strlen( $params['totitle'] ) > 0 ) ) {
			$this->dieUsage( wfMsg( 'wikibase-api-fromtitle-and-totitle' ), 'fromtitle-and-totitle' );
		}

		// Get all parts for the from-link
		// This code should probably use hasGlobal...
		print("From site: " . $params["fromsite"]."\n");
		$fromSite = Sites::singleton()->getSiteByGlobalId( $params['fromsite'] );
		if ($fromSite === false) {
			print("Found [1]: false\n");
		}
		elseif ($fromSite === true) {
			print("Found [2]: true\n");
		}
		elseif ($fromSite === null) {
			print("Found [3]: null\n");
		}
		else {
			print("Found [4]: " . get_class($fromSite) ."\n");
		}
		$fromPage = $fromSite->normalizePageName( $params['fromtitle'] );
		if ( $fromPage === false ) {
			$this->dieUsage( wfMsg( 'wikibase-api-no-external-page' ), 'no-external-page' );
		}
		$fromId = ItemHandler::singleton()->getIdForSiteLink( $params['fromsite'], $fromPage );

		// Get all part for the to-link
		$toSite = Sites::singleton()->getSiteByGlobalId( $params['tosite'] );
		//print_r($toSite);
		$toPage = $toSite->normalizePageName( $params['totitle'] );
		if ( $toPage === false ) {
			$this->dieUsage( wfMsg( 'wikibase-api-no-external-page' ), 'no-external-page' );
		}
		$toId = ItemHandler::singleton()->getIdForSiteLink( $params['tosite'], $toPage );

		$return = array();
		$flags = 0;
		$itemContent = null;

		// Figure out which parts to use and what to create anew
		if ( !$fromId && !$toId ) {
			// create new item
			$flags |= EDIT_NEW;
			$itemContent = ItemContent::newEmpty();
			$toLink = new SiteLink( $toSite, $toPage );
			$return[] = $itemContent->getItem()->addSiteLink( $toLink, 'set' );
			$fromLink = new SiteLink( $fromSite, $fromPage );
			$return[] = $itemContent->getItem()->addSiteLink( $fromLink, 'set' );
		}
		elseif ( !$fromId && $toId ) {
			// reuse to-site's item
			$itemContent = ItemHandler::singleton()->getContentFromSiteLink( $params['tosite'], $toPage );
			$fromLink = new SiteLink( $fromSite, $fromPage );
			$return[] = $itemContent->getItem()->addSiteLink( $fromLink, 'set' );
		}
		elseif ( $fromId && !$toId ) {
			// reuse from-site's item
			$itemContent = ItemHandler::singleton()->getContentFromSiteLink( $params['fromsite'], $fromPage );
			$toLink = new SiteLink( $toSite, $toPage );
			$return[] = $itemContent->getItem()->addSiteLink( $toLink, 'set' );
		}
		elseif ( $fromId === $toId ) {
			// no-op
			$this->dieUsage( wfMsg( 'wikibase-api-common-item' ), 'common-item' );
		}
		else {
			// dissimilar items
			$this->dieUsage( wfMsg( 'wikibase-api-no-common-item' ), 'no-common-item' );
		}

		$this->addSiteLinksToResult( $return, 'item' );

		if ( $this->flags & EDIT_NEW) {
			$this->flags |= EDIT_UPDATE;
		}
		$this->flags = ($user->isAllowed( 'bot' ) && $params['bot']) ? EDIT_FORCE_BOT : 0;
		$summary = '';

		if ( $itemContent === null ) {
			// to not have an ItemContent isn't really bad at this point
			$status = Status::newGood( true );
		}
		else {
			// Do the actual save, or if it don't exist yet create it.
			$status = $itemContent->save( $summary, $user, $this->flags );
		}
		$success = $status->isOK();

		if ( !$success ) {
			if ( $itemContent->isNew() ) {
				$this->dieUsage( $status->getWikiText( 'wikibase-api-create-failed' ), 'create-failed' );
			}
			else {
				$this->dieUsage( $status->getWikiText( 'wikibase-api-save-failed' ), 'save-failed' );
			}
		}

		if ( $itemContent !== null ) {
			$this->getResult()->addValue(
				'item',
				'id', $itemContent->getItem()->getId()
			);
		}

		$this->getResult()->addValue(
			null,
			'success',
			(int)$success
		);
	}

	/**
	 * Returns a list of all possible errors returned by the module
	 * @return array in the format of array( key, param1, param2, ... ) or array( 'code' => ..., 'info' => ... )
	 */
	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'code' => 'create-failed', 'info' => wfMsg( 'wikibase-api-create-failed' ) ),
			array( 'code' => 'save-failed', 'info' => wfMsg( 'wikibase-api-save-failed' ) ),
			array( 'code' => 'session-failure', 'info' => wfMsg( 'wikibase-api-session-failure' ) ),
			array( 'code' => 'no-common-item', 'info' => wfMsg( 'wikibase-api-no-common-item' ) ),
			array( 'code' => 'no-external-page', 'info' => wfMsg( 'wikibase-api-no-external-page' ) ),
			array( 'code' => 'fromtitle-and-totitle', 'info' => wfMsg( 'wikibase-api-fromtitle-and-totitle' ) ),
			array( 'code' => 'fromsite-eq-tosite', 'info' => wfMsg( 'wikibase-api-fromsite-eq-tosite' ) ),
		) );
	}
	/**
	 * Returns whether this module requires a Token to execute
	 * @return bool
	 */
	public function needsToken() {
		return Settings::get( 'apiInDebug' ) ? Settings::get( 'apiDebugWithTokens' ) : true ;
	}

	/**
	 * Indicates whether this module must be called with a POST request
	 * @return bool
	 */
	public function mustBePosted() {
		return Settings::get( 'apiInDebug' ) ? Settings::get( 'apiDebugWithPost' ) : true ;
	}

	/**
	 * Indicates whether this module requires write mode
	 * @return bool
	 */
	public function isWriteMode() {
		return Settings::get( 'apiInDebug' ) ? Settings::get( 'apiDebugWithWrite' ) : true ;
	}

	/**
	 * Returns an array of allowed parameters (parameter name) => (default
	 * value) or (parameter name) => (array with PARAM_* constants as keys)
	 * Don't call this function directly: use getFinalParams() to allow
	 * hooks to modify parameters as needed.
	 * @return array|bool
	 */
	public function getAllowedParams() {
		return array_merge( parent::getAllowedParams(), array(
			'tosite' => array(
				ApiBase::PARAM_TYPE => Sites::singleton()->getGlobalIdentifiers(),
			),
			'totitle' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'fromsite' => array(
				ApiBase::PARAM_TYPE => Sites::singleton()->getGlobalIdentifiers(),
			),
			'fromtitle' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'token' => null,
			'bot' => false,
		) );
	}

	/**
	 * Get final parameter descriptions, after hooks have had a chance to tweak it as
	 * needed.
	 *
	 * @return array|bool False on no parameter descriptions
	 */
	public function getParamDescription() {
		return array_merge( parent::getParamDescription(), array(
			'tosite' => array( 'An identifier for the site on which the page resides.',
				"Use together with 'title' to make a complete sitelink."
			),
			'totitle' => array( 'Title of the page to associate.',
				"Use together with 'site' to make a complete sitelink."
			),
			'fromsite' => array( 'An identifier for the site on which the page resides.',
				"Use together with 'title' to make a complete sitelink."
			),
			'fromtitle' => array( 'Title of the page to associate.',
				"Use together with 'site' to make a complete sitelink."
			),
			'token' => array( 'A "wbitemtoken" token previously obtained through the gettoken parameter.', // or prop=info,
				'During a normal reply a token can be returned spontaneously and the requester should',
				'then start using the new token from the next request, possibly when repeating a failed',
				'request.'
			),
		) );
	}

	/**
	 * Returns the description string for this module
	 * @return mixed string or array of strings
	 */
	public function getDescription() {
		return array(
			'API module to associate two article on two different wikis with a Wikibase item.'
		);
	}

	/**
	 * Returns usage examples for this module. Return false if no examples are available.
	 * @return bool|string|array
	 */
	protected function getExamples() {
		return array(
			'api.php?action=wblinktitles&fromsite=enwiki&linktitle=Hydrogen&tosite=dewiki&totitle=Wesserstoff'
			=> 'Add a link "Hydrogen" from the English page to "Wesserstoff" at the German page',
		);
	}

	/**
	 * @return bool|string|array Returns a false if the module has no help url, else returns a (array of) string
	 */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:Wikibase/API#wblinktitles';
	}

	/**
	 * Returns a string that identifies the version of this class.
	 * @return string
	 */
	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}

}