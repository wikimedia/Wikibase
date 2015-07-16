<?php

namespace Wikibase\Client\Changes;

use Job;
use JobQueueGroup;
use RefreshLinksJob;
use Title;
use Wikibase\Client\RecentChanges\ExternalRecentChange;

/**
 * Service object for triggering different kinds of page updates
 * and generally notifying the local wiki of external changes.
 *
 * Used by ChangeHandler as an interface to the local wiki.
 *
 * @since 0.4
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 *
 */
class WikiPageUpdater implements PageUpdater {

	/**
	 * Invalidates local cached of the given pages.
	 *
	 * @since 0.4
	 *
	 * @param Title[] $titles The Titles of the pages to update
	 */
	public function purgeParserCache( array $titles ) {
		/* @var Title $title */
		foreach ( $titles as $title ) {
			wfDebugLog( __CLASS__, __FUNCTION__ . ": purging page " . $title->getText() );
			$title->invalidateCache();
		}
	}

	/**
	 * Invalidates external web cached of the given pages.
	 *
	 * @since 0.4
	 *
	 * @param Title[] $titles The Titles of the pages to update
	 */
	public function purgeWebCache( array $titles ) {
		/* @var Title $title */
		foreach ( $titles as $title ) {
			wfDebugLog( __CLASS__, __FUNCTION__ . ": purging web cache for " . $title->getText() );
			$title->purgeSquid();
		}
	}

	/**
	 * Schedules RefreshLinks jobs for the given titles
	 *
	 * @since 0.4
	 *
	 * @param Title[] $titles The Titles of the pages to update
	 */
	public function scheduleRefreshLinks( array $titles ) {
		/* @var Title $title */
		foreach ( $titles as $title ) {
			wfDebugLog( __CLASS__, __FUNCTION__ . ": scheduling refresh links for "
				. $title->getText() );

			$job = new RefreshLinksJob(
				$title,
				Job::newRootJobParams( //XXX: the right thing?
					$title->getPrefixedDBkey()
				)
			);

			JobQueueGroup::singleton()->push( $job );
			JobQueueGroup::singleton()->deduplicateRootJob( $job );
		}
	}

	/**
	 * Injects an RC entry into the recentchanges, using the the given title and attribs
	 *
	 * @param Title[] $titles
	 * @param array $attribs
	 */
	public function injectRCRecords( array $titles, array $attribs ) {
		foreach ( $titles as $title ) {
			if ( !$title->exists() ) {
				continue;
			}

			$rc = ExternalRecentChange::newFromAttribs( $attribs, $title );

			wfDebugLog( __CLASS__, __FUNCTION__ . ": saving RC entry for " . $title->getFullText() );
			$rc->save();
		}
	}

}
