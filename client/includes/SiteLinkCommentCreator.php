<?php

namespace Wikibase;

use Diff\DiffOp\Diff\Diff;
use Diff\DiffOp\DiffOp;
use Diff\DiffOp\DiffOpAdd;
use Diff\DiffOp\DiffOpChange;
use Diff\DiffOp\DiffOpRemove;
use Language;
use Message;
use MWException;
use SiteStore;

/**
 * Creates an array structure with comment information for storing
 * in the rc_params column of the RecentChange table, for use in
 * generating recent change comments for wikibase changes.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Daniel Kinzler
 */
class SiteLinkCommentCreator {

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
	private $siteId;

	/**
	 * @param Language $language
	 * @param SiteStore $siteStore
	 * @param string $siteId
	 */
	public function __construct( Language $language, SiteStore $siteStore, $siteId ) {
		$this->siteId = $siteId;
		$this->siteStore = $siteStore;
		$this->language = $language;
	}

	/**
	 * Returns the comment to use in RC and history entries for this change.
	 * This may be a complex structure. It will be interpreted by
	 *
	 * @since 0.5
	 *
	 * @param Diff|null $siteLinkDiff
	 * @param string $action e.g. 'remove', see the constants in EntityChange
	 *
	 * @return string|null A human readable edit summary (limited wikitext),
	 *         or null if no summary could be created for the sitelink change.
	 */
	public function getEditComment( Diff $siteLinkDiff = null, $action ) {
		if ( $siteLinkDiff !== null && !$siteLinkDiff->isEmpty() ) {
			$siteLinkMessage = $this->getSiteLinkMessage( $action, $siteLinkDiff );

			if ( !empty( $siteLinkMessage ) ) {
				return $this->generateComment( $siteLinkMessage );
			}
		}

		return null;
	}

	/**
	 * Returns an array structure suitable for building an edit summary for the respective
	 * change to site links.
	 *
	 * @param string $action e.g. 'remove', see the constants in EntityChange
	 * @param Diff $siteLinkDiff The change's site link diff
	 *
	 * @return array|null
	 */
	private function getSiteLinkMessage( $action, Diff $siteLinkDiff ) {
		if ( $siteLinkDiff->isEmpty() ) {
			return null;
		}

		//TODO: Implement comments specific to the affected page.
		//       Different pages may be affected in different ways by the same change.
		//       Also, merged changes may affect the same page in multiple ways.
		$diffOps = $siteLinkDiff->getOperations();
		$siteId = $this->siteId;

		// change involved site link to client wiki
		if ( array_key_exists( $siteId, $diffOps ) ) {
			// $siteLinkDiff changed from containing atomic diffs to
			// containing map diffs. For B/C, handle both cases.
			$diffOp = $diffOps[$siteId];

			if ( $diffOp instanceof Diff ) {
				if ( array_key_exists( 'name', $diffOp ) ) {
					$diffOp = $diffOp['name'];
				} else {
					// change to badges only, use original message
					return null;
				}
			}

			$params = $this->getSiteLinkAddRemoveParams( $diffOp, $action, $siteId );
		} else {
			$diffOpCount = count( $diffOps );
			if ( $diffOpCount === 1 ) {
				$params = $this->getSiteLinkChangeParams( $diffOps );
			} else {
				// multiple changes, use original message
				return null;
			}
		}

		return $params;
	}

	/**
	 * @param Diff[] $diffs
	 *
	 * @return array|null
	 */
	private function getSiteLinkChangeParams( array $diffs ) {
		$messagePrefix = 'wikibase-comment-sitelink-';
		/* Messages used:
			wikibase-comment-sitelink-add wikibase-comment-sitelink-change wikibase-comment-sitelink-remove
		*/
		$params['message'] = $messagePrefix . 'change';

		foreach ( $diffs as $siteId => $diff ) {
			// backwards compatibility in case of old, pre-badges changes in the queue
			$diffOp = ( ( $diff instanceof Diff ) && array_key_exists( 'name', $diff ) ) ? $diff['name'] : $diff;
			$args = $this->getChangeParamsForDiffOp( $diffOp, $siteId, $messagePrefix );

			if ( empty( $args ) ) {
				return null;
			}

			$params = array_merge(
				$params,
				$args
			);

			// TODO: Handle if there are multiple DiffOp's here.
			break;
		}

		return $params;
	}

	/**
	 * @param DiffOp $diffOp
	 * @param string $siteId
	 * @param string $messagePrefix
	 *
	 * @return array|null
	 */
	private function getChangeParamsForDiffOp( DiffOp $diffOp, $siteId, $messagePrefix ) {
		$params = array();

		if ( $diffOp instanceof DiffOpAdd ) {
			$params['message'] = $messagePrefix . 'add';
			$params['sitelink'] = array(
				'newlink' => array(
					'site' => $siteId,
					'page' => $diffOp->getNewValue()
				)
			);
		} elseif ( $diffOp instanceof DiffOpRemove ) {
			$params['message'] = $messagePrefix . 'remove';
			$params['sitelink'] = array(
				'oldlink' => array(
					'site' => $siteId,
					'page' => $diffOp->getOldValue()
				)
			);
		} elseif ( $diffOp instanceof DiffOpChange ) {
			$params['sitelink'] = array(
				'oldlink' => array(
					'site' => $siteId,
					'page' => $diffOp->getOldValue()
				),
				'newlink' => array(
					'site' => $siteId,
					'page' => $diffOp->getNewValue()
				)
			);
		} else {
			// whatever
			$params = null;
		}

		return $params;
	}

	/**
	 * @param DiffOp $diffOp
	 * @param string $action e.g. 'remove', see the constants in EntityChange
	 * @param string $siteId
	 *
	 * @return array|null
	 */
	private function getSiteLinkAddRemoveParams( DiffOp $diffOp, $action, $siteId ) {
		$params = array();

		if ( in_array( $action, array( 'remove', 'restore' ) ) ) {
			// Messages: wikibase-comment-remove, wikibase-comment-restore
			$params['message'] = 'wikibase-comment-' . $action;
		} elseif ( $diffOp instanceof DiffOpAdd ) {
			$params['message'] = 'wikibase-comment-linked';
		} elseif ( $diffOp instanceof DiffOpRemove ) {
			$params['message'] = 'wikibase-comment-unlink';
		} elseif ( $diffOp instanceof DiffOpChange ) {
			$params['message'] = 'wikibase-comment-sitelink-change';

			$params['sitelink'] = array(
				'oldlink' => array(
					'site' => $siteId,
					'page' => $diffOp->getOldValue()
				),
				'newlink' => array(
					'site' => $siteId,
					'page' => $diffOp->getNewValue()
				)
			);
		} else {
			// whatever
			$params = null;
		}

		return $params;
	}

	/**
	 * @param string $siteId
	 * @param string $pageTitle
	 *
	 * @return string wikitext interwiki link
	 */
	private function getSitelinkWikitext( $siteId, $pageTitle ) {
		$interwikiId = $siteId;

		// Try getting the interwiki id from the Site object of the link target
		$site = $this->siteStore->getSite( $siteId );
		if ( $site ) {
			$iw_ids = $site->getInterwikiIds();
			if ( isset( $iw_ids[0] ) ) {
				$interwikiId = $iw_ids[0];
			}
		}

		return "[[:$interwikiId:$pageTitle]]";
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

	/**
	 * @param array $messageSpec
	 *
	 * @return string An edit summary (as limited wikitext).
	 */
	private function generateComment( array $messageSpec ) {
		$key = $messageSpec['message'];
		$args = array();

		if ( isset( $messageSpec['sitelink']['oldlink'] ) ) {
			$link = $messageSpec['sitelink']['oldlink'];
			$args[] = $this->getSitelinkWikitext( $link['site'], $link['page'] );
		}

		if ( isset( $messageSpec['sitelink']['newlink'] ) ) {
			$link = $messageSpec['sitelink']['newlink'];
			$args[] = $this->getSitelinkWikitext( $link['site'], $link['page'] );
		}

		$msg = $this->msg( $key, $args );
		return $msg->text();
	}

}
