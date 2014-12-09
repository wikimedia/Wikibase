<?php

namespace Wikibase\Client;

use Site;
use SiteList;
use SiteStore;

/**
 * Generates a list of sites that should be displayed in the "Other projects" sidebar.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Thomas Pellissier Tanon
 * @author Marius Hoch < hoo@online.de >
 */
class OtherProjectsSitesGenerator implements OtherProjectsSitesProvider {

	/**
	 * @param SiteStore
	 */
	private $siteStore;

	/**
	 * @var string
	 */
	private $localSiteId;

	/**
	 * @var string[]
	 */
	private $specialSiteGroups;

	/**
	 * @param SiteStore $siteStore
	 * @param string localSiteId
	 * @param string[] $specialSiteGroups
	 */
	public function __construct( SiteStore $siteStore, $localSiteId, array $specialSiteGroups ) {
		$this->siteStore = $siteStore;
		$this->localSiteId = $localSiteId;
		$this->specialSiteGroups = $specialSiteGroups;
	}

	/**
	 * Provides a list of sites to link to in the "other project" sidebar
	 *
	 * This list contains the wiki in the same language if it exists for each other site groups and the wikis alone in their
	 * sites groups (like commons)
	 *
	 * @param string[] $supportedSiteGroupIds
	 *
	 * @return SiteList
	 */
	public function getOtherProjectsSites( array $siteLinkGroups ) {
		$currentGroupId = $this->getLocalSite()->getGroup();
		$otherProjectsSites = new SiteList();

		$this->expandSpecialGroups( $siteLinkGroups );
		foreach ( $siteLinkGroups as $groupId ) {
			if ( $groupId === $currentGroupId ) {
				continue;
			}

			$siteToAdd = $this->getSiteForGroup( $groupId );
			if ( $siteToAdd ) {
				$otherProjectsSites[] = $siteToAdd;
			}
		}

		return $otherProjectsSites;
	}

	/**
	 * Get the site ids of other projects to use.
	 *
	 * @param array $siteLinkGroups
	 * @return string[]
	 */
	public function getOtherProjectsSiteIds( array $siteLinkGroups ) {
		$otherProjectsSites = $this->getOtherProjectsSites( $siteLinkGroups );

		$otherProjectsSiteIds = array();
		foreach ( $otherProjectsSites as $site ) {
			$otherProjectsSiteIds[] = $site->getGlobalId();
		}

		return $otherProjectsSiteIds;
	}

	/**
	 * Returns the site to link to for a given group or null
	 *
	 * If there is only one site in this group (like for commons) this site is returned else the site in the same language
	 * as the current site is returned
	 *
	 * @param string $groupId
	 *
	 * @return Site|null
	 */
	private function getSiteForGroup( $groupId ) {
		$siteGroupList = $this->siteStore->getSites()->getGroup( $groupId );
		if ( $siteGroupList->count() === 1 ) {
			return $siteGroupList[0];
		}

		$currentLanguageCode = $this->getLocalSite()->getLanguageCode();
		foreach ( $siteGroupList as $site ) {
			if ( $site->getLanguageCode() === $currentLanguageCode ) {
				return $site;
			}
		}

		return null;
	}

	/**
	 * @param array &$groups
	 */
	private function expandSpecialGroups( &$groups ) {
		if ( !in_array( 'special', $groups ) ) {
			return;
		}

		$groups = array_diff( $groups, array( 'special' ) );
		$groups = array_merge( $groups, $this->specialSiteGroups );
	}

	/**
	 * @return Site
	 */
	private function getLocalSite() {
		return $this->siteStore->getSite( $this->localSiteId );
	}
}
