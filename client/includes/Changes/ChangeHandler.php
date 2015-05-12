<?php

namespace Wikibase\Client\Changes;

use Exception;
use InvalidArgumentException;
use IORMRow;
use MWException;
use Title;
use Wikibase\Change;
use Wikibase\Client\Store\TitleFactory;
use Wikibase\Client\Usage\EntityUsage;
use Wikibase\Client\Usage\PageEntityUsages;
use Wikibase\EntityChange;
use Wikibase\ItemChange;
use Wikibase\SiteLinkCommentCreator;

/**
 * Interface for change handling. Whenever a change is detected,
 * it should be fed to this service which then takes care handling
 * it.
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
	 * @var string
	 */
	private $localSiteId;

	/**
	 * @var bool
	 */
	private $injectRecentChanges;

	/**
	 * @param AffectedPagesFinder $affectedPagesFinder
	 * @param TitleFactory $titleFactory
	 * @param PageUpdater $updater
	 * @param ChangeListTransformer $changeListTransformer
	 * @param string $localSiteId
	 * @param bool $injectRecentChanges
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct(
		AffectedPagesFinder $affectedPagesFinder,
		TitleFactory $titleFactory,
		PageUpdater $updater,
		ChangeListTransformer $changeListTransformer,
		$localSiteId,
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
		$this->localSiteId = $localSiteId;
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

		if ( !wfRunHooks( 'WikibaseHandleChanges', array( $changes ) ) ) {
			return;
		}

		foreach ( $changes as $change ) {
			if ( !wfRunHooks( 'WikibaseHandleChange', array( $change ) ) ) {
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
	 * @return bool
	 */
	public function handleChange( Change $change ) {
		$changeId = $this->getChangeIdForLog( $change );
		wfDebugLog( __CLASS__, __FUNCTION__ . ": handling change #$changeId"
			. ' (' . $change->getType() . ')' );

		$usagesPerPage = $this->affectedPagesFinder->getAffectedUsagesByPage( $change );

		if ( empty( $usagesPerPage ) ) {
			// nothing to do
			wfDebugLog( __CLASS__, __FUNCTION__ . ": No pages to update for change #$changeId." );
			return false;
		}

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

		return true;
	}

	/**
	 * @param string[] $aspects Usage aspects (without modifier), see EntityUsage.
	 *
	 * @return string[] A list of actions, as defined by the self::XXXX_ACTION constants.
	 */
	public function getUpdateActions( $aspects ) {
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
	private function updateActionBuckets( &$buckets, $pageId, $actions ) {
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
	private function getTitlesForPageIds( $pageIds ) {
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
	 * @param EntityChange $change The Change that caused the update
	 *
	 * @return array[]|bool an array of RC attributes,
	 *         or false if the change does not provide edit meta data
	 */
	private function getRCAttributes( EntityChange $change ) {
		$rcinfo = $change->getMetadata();

		if ( !is_array( $rcinfo ) ) {
			return false;
		}

		//@todo: add getFields() to the interface, or provide getters!
		$fields = $change->getFields();
		$fields['entity_type'] = $change->getEntityId()->getEntityType();

		if ( $change instanceof ItemChange ) {
			$rcinfo['comment'] = $this->getEditComment( $change );

			if ( isset( $fields['info']['changes'] ) ) {
				$rcinfo['composite-comment'] = array();

				foreach ( $fields['info']['changes'] as $part ) {
					$rcinfo['composite-comment'][] = $this->getEditComment( $part );
				}
			}
		}

		unset( $fields['info'] );

		$rcinfo = array_merge( $fields, $rcinfo );

		return array(
			'wikibase-repo-change' => array_merge( $fields, $rcinfo )
		);
	}

	/**
	 * Returns the comment as structured array of information, to be
	 * stored in recent change entries and used to display formatted
	 * comments for wikibase changes in recent changes, watchlist, etc.
	 *
	 * @since 0.4
	 *
	 * @param EntityChange $change the change to get a comment for
	 *
	 * @throws MWException
	 * @return array|string
	 */
	public function getEditComment( EntityChange $change ) {
		$siteLinkDiff = $change instanceof ItemChange
			? $change->getSiteLinkDiff()
			: null;
		$action = $change->getAction();
		$comment = $change->getComment();

		$commentCreator = new SiteLinkCommentCreator( $this->localSiteId );
		$editComment = $commentCreator->getEditComment( $siteLinkDiff, $action, $comment );

		if ( is_array( $editComment ) && !isset( $editComment['message'] ) ) {
			throw new MWException( 'getEditComment returned an empty comment' );
		}

		return $editComment;
	}

}
