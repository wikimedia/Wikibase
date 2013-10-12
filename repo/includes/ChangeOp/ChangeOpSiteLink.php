<?php

namespace Wikibase\ChangeOp;

use Site;
use InvalidArgumentException;
use Wikibase\DataModel\SimpleSiteLink;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Entity;
use Wikibase\Item;
use Wikibase\Summary;

/**
 * Class for sitelink change operation
 *
 * @since 0.4
 * @licence GNU GPL v2+
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 * @author Michał Łazowik
 */
class ChangeOpSiteLink extends ChangeOpBase {

	/**
	 * @since 0.4
	 *
	 * @var string
	 */
	protected $siteId;

	/**
	 * @since 0.4
	 *
	 * @var string|null
	 */
	protected $pageName;

	/**
	 * @since 0.5
	 *
	 * @var ItemId[]|null
	 */
	 protected $badges;

	/**
	 * @since 0.4
	 *
	 * @param string $siteId
	 * @param string|null $pageName Null in case the link with the provided siteId should be removed
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( $siteId, $pageName, $badges = null ) {
		if ( !is_string( $siteId ) ) {
			throw new InvalidArgumentException( '$siteId needs to be a string' );
		}

		if ( !is_string( $pageName ) && $pageName !== null ) {
			throw new InvalidArgumentException( '$linkPage needs to be a string or null' );
		}

		if ( !is_array( $badges ) && $badges !== null ) {
			throw new InvalidArgumentException( '$badges need to be an array of ItemIds or null' );
		}

		if ( $badges !== null ) {
			foreach ( $badges as $badge ) {
				if ( !( $badge instanceof ItemId ) ) {
					throw new InvalidArgumentException( '$badges need to be an array of ItemIds or null' );
				}
			}
		}

		$this->siteId = $siteId;
		$this->pageName = $pageName;
		$this->badges = $badges;
	}

	/**
	 * @see ChangeOp::apply()
	 */
	public function apply( Entity $entity, Summary $summary = null ) {
		if ( !( $entity instanceof Item ) ) {
			throw new InvalidArgumentException( 'ChangeOpSiteLink can only be applied to Item instances' );
		}

		if ( $this->pageName === null ) {
			if ( $entity->hasLinkToSite( $this->siteId ) ) {
				$this->updateSummary( $summary, 'remove', $this->siteId, $entity->getSimpleSiteLink( $this->siteId )->getPageName() );
				$entity->removeSiteLink( $this->siteId );
			} else {
				//TODO: throw error, or ignore silently?
			}
		} else {
			if ( $this->badges === null ) {
				// If badges are not set in change make sure that they remain intact
				if ( $entity->hasLinkToSite( $this->siteId ) ) {
					$badges = $entity->getSimpleSiteLink( $this->siteId )->getBadges();
				} else {
					$badges = array();
				}
			} else {
				$badges = $this->badges;
			}

			$action = $entity->hasLinkToSite( $this->siteId ) ? 'set' : 'add';

			$commentArgs = array( $this->pageName );

			if ( $this->badges !== null ) {
				//FIXME: summaries need a rewrite, for now only one autocomment can be highlighted
				$commentArgs[] = wfMessage( 'wikibase-item-summary-wbsetsitelink-badges' )->escaped();
				$commentArgs = array_merge(
					$commentArgs,
					$this->badges
				);
			}

			$this->updateSummary( $summary, $action, $this->siteId, $commentArgs );

			$entity->addSimpleSiteLink( new SimpleSiteLink( $this->siteId, $this->pageName, $badges ) );
		}

		return true;
	}
}
