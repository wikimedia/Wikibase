<?php

namespace Wikibase\Client\Hooks;

use Title;
use Interwiki;
use SiteStore;
use Wikibase\SiteLinkLookup;
use Wikibase\EntityLookup;
use Wikibase\DataModel\SiteLink;
use Wikibase\DataModel\Entity\ItemId;

/**
 * Provides access to the badges of the connected sitelinks of a page.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class LanguageLinkBadgeHandler {

	/**
	 * @var string
	 */
	protected $localSiteId;

	/**
	 * @var SiteLinkLookup
	 */
	protected $siteLinkLookup;

	/**
	 * @var EntityLookup
	 */
	protected $entityLookup;

	/**
	 * @var SiteStore
	 */
	protected $sites;

	/**
	 * @var array
	 */
	protected $displayBadges;

	/**
	 * @var string
	 */
	protected $langCode;

	/**
	 * @var Site[]
	 */
	protected $sitesByNavigationId = null;

	/**
	 * @param string $localSiteId Global id of the client wiki
	 * @param SiteLinkLookup $siteLinkLookup
	 * @param EntityLookup $entityLookup
	 * @param SiteStore $sites
	 * @param array $displayBadges
	 * @param string $langCode
	 */
	public function __construct( $localSiteId, SiteLinkLookup $siteLinkLookup,
			EntityLookup $entityLookup, SiteStore $sites, array $displayBadges, $langCode ) {
		$this->localSiteId = $localSiteId;
		$this->siteLinkLookup = $siteLinkLookup;
		$this->entityLookup = $entityLookup;
		$this->sites = $sites;
		$this->displayBadges = $displayBadges;
		$this->langCode = $langCode;
	}

	/**
	 * Looks up the item of the given title and assigns the badges of the sitelink
	 * associated with the given language link title to the passed array.
	 *
	 * @since 0.5
	 *
	 * @param Title $title
	 * @param Title $languageLinkTitle
	 * @param array &$languageLink
	 */
	public function assignBadges( Title $title, Title $languageLinkTitle, array &$languageLink ) {
		$site = $this->getSiteByNavigationId( $languageLinkTitle->getInterwiki() );
		if ( !$site ) {
			return array();
		}

		$siteLink = $this->getSiteLink( $title, $site->getGlobalId() );
		if ( !$siteLink ) {
			return array();
		}

		if ( !isset( $languageLink['class'] ) ) {
			$languageLink['class'] = '';
		}

		$linkBadges = array();
		foreach ( $siteLink->getBadges() as $badgeObject ) {
			$badge = $badgeObject->getSerialization();
			$languageLink['class'] .= " badge-$badge";
			$linkBadges[] = $badge;
		}
		
		foreach ( $this->displayBadges as $badge ) {
			if ( in_array( $badge, $linkBadges ) ) {
				$title = $this->getTitle( $badge );
				if ( $title !== null ) {
					// if a badge comes later in the config,
					// this will override the title as documented.
					$languageLink['itemtitle'] = $title;
				}
			}
		}

		// delete possible empty fields
		$languageLink = array_filter( $languageLink );
	}

	/**
	 * Finds the corresponding item on the repository and
	 * returns the item's site link for the given site id.
	 *
	 * @since 0.5
	 *
	 * @param Title $title
	 * @param string $languageLinkSiteId
	 *
	 * @return SiteLink|null
	 */
	protected function getSiteLink( Title $title, $languageLinkSiteId ) {
		$siteLink = new SiteLink( $this->localSiteId, $title->getText() );
		$itemId = $this->siteLinkLookup->getEntityIdForSiteLink( $siteLink );

		if ( $itemId === null ) {
			return null;
		}

		$item = $this->entityLookup->getEntity( $itemId );
		if ( !$item->hasLinkToSite( $languageLinkSiteId ) ) {
			return null;
		}
		return $item->getSiteLink( $languageLinkSiteId );
	}

	/**
	 * Returns the title for the given badge.
	 *
	 * @since 0.5
	 *
	 * @param string $badge
	 *
	 * @return string|null
	 */
	protected function getTitle( $badge ) {
		$entity = $this->entityLookup->getEntity( new ItemId( $badge ) );
		if ( !$entity ) {
			return null;
		}

		$description = $entity->getDescription( $this->langCode );
		if ( !$description ) {
			return null;
		}
		return $description;
	}

	/**
	 * Returns a Site object for the given navigational ID (alias inter-language prefix).
	 *
	 * @todo: move this functionality into Sites/SiteList/SiteArray!
	 *        This snippet has been copied from LangLinkHandler until we have it in core.
	 *
	 * @param string $id The navigation ID to find a site for.
	 *
	 * @return bool|Site The site with the given navigational ID, or false if not found.
	 */
	protected function getSiteByNavigationId( $id ) {
		wfProfileIn( __METHOD__ );

		//FIXME: this needs to be moved into core, into SiteList resp. SiteArray!
		if ( $this->sitesByNavigationId === null ) {
			$this->sitesByNavigationId = array();

			/* @var Site $site */
			foreach ( $this->sites->getSites() as $site ) {
				$ids = $site->getNavigationIds();

				foreach ( $ids as $navId ) {
					$this->sitesByNavigationId[$navId] = $site;
				}
			}
		}

		wfProfileOut( __METHOD__ );
		return isset( $this->sitesByNavigationId[$id] ) ? $this->sitesByNavigationId[$id] : false;
	}

}
