<?php

namespace Wikibase\Client\Changes;

use Exception;
use Hooks;
use InvalidArgumentException;
use IORMRow;
use Language;
use Message;
use MWException;
use SiteStore;
use Title;
use Wikibase\Change;
use Wikibase\Client\Store\TitleFactory;
use Wikibase\Client\Usage\EntityUsage;
use Wikibase\Client\Usage\PageEntityUsages;
use Wikibase\EntityChange;
use Wikibase\ItemChange;
use Wikibase\SiteLinkCommentCreator;
use Wikimedia\Assert\Assert;

/**
 * Interface for change handling. Whenever a change is detected,
 * it should be fed to this service which then takes care handling it.
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class ChangeHandler {

	/**
	 * The change requites any rendered version of the page to be purged from the parser cache.
	 */
	const PARSER_PURGE_ACTION = 'parser';

	/**
	 * The change requites a LinksUpdate job to be scheduled to update any links
	 * associated with the page.
	 */
	const LINKS_UPDATE_ACTION = 'links';

	/**
	 * The change requites any HTML output generated from the page to be purged from web cached.
	 */
	const WEB_PURGE_ACTION = 'web';

	/**
	 * The change requites an entry to be injected into the recentchanges table.
	 */
	const RC_ENTRY_ACTION = 'rc';

	/**
	 * The change requites an entry to be injected into the revision table.
	 */
	const HISTORY_ENTRY_ACTION = 'history';

	/**
	 * @var AffectedPagesFinder
	 */
	private $affectedPagesFinder;

	/**
	 * @var TitleFactory
	 */
	private $titleFactory;

	/**
	 * @var PageUpdater
	 */
	private $updater;

	/**
	 * @var ChangeListTransformer
	 */
	private $changeListTransformer;

	/**
	 * @var Language
	 */
	private $language;

	/**
	 * @var SiteStore
	 */
	private $siteStore;

	/**
	 * @var string
	 */
	private $localSiteId;

	/**
	 * @var string
	 */
	private $repoId;

	/**
	 * @var bool
	 */
	private $injectRecentChanges;

	/**
	 * @param AffectedPagesFinder $affectedPagesFinder
	 * @param TitleFactory $titleFactory
	 * @param PageUpdater $updater
	 * @param ChangeListTransformer $changeListTransformer
	 * @param Language $language
	 * @param SiteStore $siteStore
	 * @param string $localSiteId
	 * @param string $repoId
	 * @param bool $injectRecentChanges
	 * @throws InvalidArgumentException
	 */
	public function __construct(
		AffectedPagesFinder $affectedPagesFinder,
		TitleFactory $titleFactory,
		PageUpdater $updater,
		ChangeListTransformer $changeListTransformer,
		Language $language,
		SiteStore $siteStore,
		$localSiteId,
		$repoId,
		$injectRecentChanges = true
	) {
		if ( !is_string( $localSiteId ) ) {
			throw new InvalidArgumentException( '$localSiteId must be a string' );
		}

		if ( !is_bool( $injectRecentChanges ) ) {
			throw new InvalidArgumentException( '$injectRecentChanges must be a bool' );
		}

		$this->affectedPagesFinder = $affectedPagesFinder;
		$this->titleFactory = $titleFactory;
		$this->updater = $updater;
		$this->changeListTransformer = $changeListTransformer;
		$this->language = $language;
		$this->siteStore = $siteStore;
		$this->localSiteId = $localSiteId;
		$this->repoId = $repoId;
		$this->injectRecentChanges = $injectRecentChanges;
	}

	/**
	 * Handle the provided changes.
	 *
	 * @since 0.1
	 *
	 * @param Change[] $changes
	 */
	public function handleChanges( array $changes ) {
		$changes = $this->changeListTransformer->transformChangeList( $changes );

		if ( !Hooks::run( 'WikibaseHandleChanges', array( $changes ) ) ) {
			return;
		}

		foreach ( $changes as $change ) {
			if ( !Hooks::run( 'WikibaseHandleChange', array( $change ) ) ) {
				continue;
			}

			$this->handleChange( $change );
		}
	}

	/**
	 * Main entry point for handling changes
	 *
	 * @todo: process multiple changes at once!
	 *
	 * @since 0.1
	 *
	 * @param Change $change
	 *
	 * @throws MWException
	 */
	public function handleChange( Change $change ) {
		$changeId = $this->getChangeIdForLog( $change );
		wfDebugLog( __CLASS__, __FUNCTION__ . ": handling change #$changeId"
			. ' (' . $change->getType() . ')' );

		$usagesPerPage = $this->affectedPagesFinder->getAffectedUsagesByPage( $change );

		wfDebugLog( __CLASS__, __FUNCTION__ . ': updating ' . count( $usagesPerPage )
			. " page(s) for change #$changeId." );

		$actionBuckets = array();

		/** @var PageEntityUsages $usages */
		foreach ( $usagesPerPage as $usages ) {
			$actions = $this->getUpdateActions( $usages->getAspects() );
			$this->updateActionBuckets( $actionBuckets, $usages->getPageId(), $actions );
		}

		foreach ( $actionBuckets as $action => $bucket ) {
			$this->applyUpdateAction( $action, $bucket, $change );
		}
	}

	/**
	 * @param string[] $aspects List of usage aspects (without modifiers), as defined by the
	 * EntityUsage::..._USAGE constants.
	 *
	 * @return string[] List of actions, as defined by the ChangeHandler::..._ACTION constants.
	 */
	public function getUpdateActions( array $aspects ) {
		$actions = array();
		$aspects = array_flip( $aspects );

		$all = isset( $aspects[EntityUsage::ALL_USAGE] );

		if ( isset( $aspects[EntityUsage::SITELINK_USAGE] ) || $all ) {
			$actions[self::LINKS_UPDATE_ACTION] = true;

			// TODO: introduce an update action that updates just the metadata
			// in the cached ParserOutput, without re-parsing the page!
			$actions[self::PARSER_PURGE_ACTION] = true;
		}

		if ( isset( $aspects[EntityUsage::LABEL_USAGE] ) || $all ) {
			$actions[self::PARSER_PURGE_ACTION] = true;
		}

		if ( isset( $aspects[EntityUsage::TITLE_USAGE] ) || $all ) {
			$actions[self::PARSER_PURGE_ACTION] = true;
		}

		if ( isset( $aspects[EntityUsage::OTHER_USAGE] ) || $all ) {
			$actions[self::PARSER_PURGE_ACTION] = true;
		}

		// Purge caches and inject log entries if we have reason
		// to update the cached ParserOutput object in some way.
		if ( isset( $actions[self::PARSER_PURGE_ACTION] ) || isset( $actions[self::LINKS_UPDATE_ACTION] ) ) {
			$actions[self::WEB_PURGE_ACTION] = true;
			$actions[self::RC_ENTRY_ACTION] = true;
			$actions[self::HISTORY_ENTRY_ACTION] = true;
		}

		return array_keys( $actions );
	}

	/**
	 * @param array[] &$buckets Map of action names to lists of page IDs. To be updated.
	 * @param int $pageId The page ID
	 * @param string[] $actions Actions to perform on the page
	 */
	private function updateActionBuckets( array &$buckets, $pageId, array $actions ) {
		foreach ( $actions as $action ) {
			$buckets[$action][] = $pageId;
		}
	}

	/**
	 * @param string $action
	 * @param int[] $pageIds
	 * @param EntityChange $change
	 */
	private function applyUpdateAction( $action, array $pageIds, EntityChange $change ) {
		$titlesToUpdate = $this->getTitlesForPageIds( $pageIds );

		switch ( $action ) {
			case self::PARSER_PURGE_ACTION:
				$this->updater->purgeParserCache( $titlesToUpdate );
				break;

			case self::WEB_PURGE_ACTION:
				$this->updater->purgeWebCache( $titlesToUpdate );
				break;

			case self::LINKS_UPDATE_ACTION:
				$this->updater->scheduleRefreshLinks( $titlesToUpdate );
				break;

			case self::RC_ENTRY_ACTION:
				$rcAttribs = $this->getRCAttributes( $change );

				if ( $rcAttribs !== false && $this->injectRecentChanges ) {
					//FIXME: The same change may be reported to several target pages;
					//       The comment we generate should be adapted to the role that page
					//       plays in the change, e.g. when a sitelink changes from one page to another,
					//       the link was effectively removed from one and added to the other page.
					$this->updater->injectRCRecords( $titlesToUpdate, $rcAttribs );
				}

				break;

			//TODO: handling for self::HISTORY_ENTRY_ACTION goes here.
			//      should probably be $this->updater->injectHistoryRecords() or some such.
		}
	}

	/**
	 * @param int[] $pageIds
	 *
	 * @return Title[]
	 */
	private function getTitlesForPageIds( array $pageIds ) {
		$titles = array();

		foreach ( $pageIds as $id ) {
			try {
				$title = $this->titleFactory->newFromID( $id );
				$titles[] = $title;
			} catch ( Exception $ex ) {
				// No title for that ID, maybe the page got deleted just now.
			}
		}

		return $titles;
	}

	/**
	 * Returns a human readable change ID, containing multiple IDs in case of a
	 * coalesced change.
	 *
	 * @param Change $change
	 *
	 * @return string
	 */
	private function getChangeIdForLog( Change $change ) {
		if ( $change instanceof IORMRow ) {
			//@todo: add getFields() to the interface, or provide getters!
			$fields = $change->getFields();

			if ( isset( $fields['info']['change-ids'] ) ) {
				return implode( '|', $fields['info']['change-ids'] );
			}
		}

		return $change->getId();
	}

	/**
	 * Constructs RC attributes for the given change
	 *
	 * @see ExternalRecentChange::buildAttributes
	 *
	 * @param EntityChange $change The Change that caused the update
	 *
	 * @return array[]|bool an array of RC attributes,
	 *         as understood by ExternalRecentChange::buildAttributes.
	 */
	private function getRCAttributes( EntityChange $change ) {
		$rcinfo = $change->getMetadata();

		//@todo: add getFields() to the interface, or provide getters!
		$fields = $change->getFields();
		$fields['entity_type'] = $change->getEntityId()->getEntityType();

		if ( isset( $fields['info']['changes'] ) ) {
			$changesForComment = $fields['info']['changes'];
		} else {
			$changesForComment = array( $change );
		}

		unset( $fields['info'] );
		$changeParams = array_merge( $fields, $rcinfo );

		if ( !isset( $changeParams['site_id'] ) ) {
			$changeParams['site_id'] = $this->repoId;
		}

		$comment = $this->getEditCommentMulti( $changesForComment );

		// Use keys known to ExternalRecentChange::buildAttributes.
		// FIXME: Simplify the way this is passed around.
		// FIXME: Move all this into a factory for RecentChange objects.
		// FIXME: ExternalRecentChange could be converted to such a factory.
		return array(
			'wikibase-repo-change' => $changeParams,
			'comment' => $comment
		);
	}

	/**
	 * Returns a human readable comment representing the given changes.
	 *
	 * @param EntityChange[] $changes
	 *
	 * @throws MWException
	 * @return string
	 */
	private function getEditCommentMulti( array $changes ) {
		$comments = array();
		$c = 0;

		foreach ( $changes as $change ) {
			$c++;
			$comments[] = $this->getEditComment( $change );
		}

		if ( $c === 0 ) {
			return '';
		} elseif ( $c === 1 ) {
			return reset( $comments );
		} else {
			//@todo: handle overly long lists nicely!
			return $this->language->semicolonList( $comments );
		}
	}

	/**
	 * Returns a human readable comment representing the change.
	 *
	 * @since 0.4
	 *
	 * @param EntityChange $change the change to get a comment for
	 *
	 * @throws MWException
	 * @return string
	 */
	public function getEditComment( EntityChange $change ) {
		$siteLinkDiff = $change instanceof ItemChange
			? $change->getSiteLinkDiff()
			: null;

		$editComment = '';

		if ( $siteLinkDiff !== null && !$siteLinkDiff->isEmpty() ) {
			$action = $change->getAction();
			$commentCreator = new SiteLinkCommentCreator( $this->language, $this->siteStore, $this->localSiteId );
			$siteLinkComment = $commentCreator->getEditComment( $siteLinkDiff, $action );
			$editComment = $siteLinkComment === null ? '' : $siteLinkComment;
		}

		if ( $editComment === '' ) {
			$editComment = $change->getComment();
		}

		if ( $editComment === '' ) {
			// If there is no comment, use something generic. This shouldn't happen.
			wfWarn( 'Failed to generate edit comment for EntityChange' );
			$editComment = $this->msg( 'wikibase-comment-update' )->text();
		}

		Assert::postcondition( is_string( $editComment ), '$editComment must be a string' );
		return $editComment;
	}

	/**
	 * @param string $key
	 *
	 * @return Message
	 * @throws MWException
	 */
	private function msg( $key ) {
		$params = func_get_args();
		array_shift( $params );
		if ( isset( $params[0] ) && is_array( $params[0] ) ) {
			$params = $params[0];
		}

		return wfMessage( $key, $params )->inLanguage( $this->language );
	}

}
