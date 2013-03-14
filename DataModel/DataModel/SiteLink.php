<?php

namespace Wikibase;
use Sites, Site, MWException;

/**
 * Class representing a link to another site, based upon the Sites class.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @since 0.1
 *
 * @file
 * @ingroup WikibaseDataModel
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class SiteLink {

	/**
	 * Creates a new SiteLink representing a link to the given page on the given site. The page title is normalized
	 * for the SiteLink object is created. If you already have a normalized page title, use the constructor directly.
	 *
	 * @note  : If $normalize is set, this may cause an API request to the remote site, so beware that this function may
	 *          be slow slow and depend on an external service.
	 *
	 * @deprecated since 0.4, use the constructor or Site::newForType
	 *
	 * @param String $globalSiteId     The site's global ID
	 * @param String $page       The target page's title
	 * @param bool   $normalize  Whether the page title should be normalized (default: false)
	 *
	 * @see \Wikibase\Site::normalizePageName()
	 *
	 * @return \Wikibase\SiteLink the new SiteLink
	 * @throws \MWException if the $siteID isn't known.
	 */
	public static function newFromText( $globalSiteId, $page, $normalize = false ) {
		wfProfileIn( __METHOD__ );

		$site = Sites::singleton()->getSite( $globalSiteId );

		if ( !$site ) {
			$site = new Site();
			$site->setGlobalId( $globalSiteId );
		}

		if ( $normalize ) {
			$normalized = $site->normalizePageName( $page );

			if ( $normalized === false ) {
				throw new MWException( "failed to normalize title: $page" );
			}

			$page = $normalized;
		}

		$link = new SiteLink( $site, $page );

		wfProfileOut( __METHOD__ );
		return $link;
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * @since 0.1
	 * @var String
	 */
	protected $page;

	/**
	 * @since 0.1
	 * @var Site
	 */
	protected $site;

	/**
	 * Constructor.
	 *
	 * @since 0.1
	 *
	 * @param Site   $site  The site the page link points to
	 * @param String $page  The target page's title. This is expected to already be normalized.
	 *
	 * @throws MWException
	 */
	public function __construct( Site $site, $page ) {
		if ( !is_string( $page ) ) {
			throw new MWException( '$page must be a string' );
		}

		$this->site = $site;
		$this->page = $page;
	}

	/**
	 * Returns the target page's title, as provided to the constructor.
	 *
	 * @since 0.1
	 *
	 * @return String
	 */
	public function getPage() {
		return $this->page;
	}

	/**
	 * Returns the target site's Site object.
	 *
	 * @since 0.1
	 *
	 * @return Site
	 */
	public function getSite() {
		return $this->site;
	}

	/**
	 * Returns the target's full URL.
	 *
	 * @since 0.1
	 *
	 * @return string The URL
	 */
	public function getUrl() {
		return $this->site->getPageUrl( $this->getPage() );
	}

	/**
	 * Returns a string representation of this site link
	 *
	 * @since 0.1
	 *
	 * @return string
	 */
	public function __toString() {
		return '[[' . $this->getSite()->getGlobalId() . ':' . $this->getPage() . ']]';
	}

	/**
	 * Returns the list of site IDs for a given list of site Links.
	 * Each site will only occur once in the result.
	 * The order of the site ids in the result is undefined.
	 *
	 * @param $siteLinks array a list of SiteLink objects
	 * @return array the list of site ids.
	 */
	public static function getSiteIDs( $siteLinks ) {
		$siteIds = array();

		/**
		 * @var SiteLink $link
		 */
		foreach ( $siteLinks as $link ) {
			$siteIds[] = $link->getSite()->getGlobalId();
		}

		return array_unique( $siteIds );
	}

	/**
	 * Converts a list of SiteLink objects to a structure of arrays.
	 *
	 * @since 0.1
	 *
	 * @param array $baseLinks a list of SiteLink objects
	 * @return array an associative array with on entry for each sitelink
	 */
	public static function siteLinksToArray( $baseLinks ) {
		$links = array();

		/**
		 * @var SiteLink $link
		 */
		foreach ( $baseLinks as $link ) {
			$links[ $link->getSite()->getGlobalId() ] = $link->getPage();
		}

		return $links;
	}

	/**
	 * Builds an array of SiteLinks from an array of arrays.
	 *
	 * @since 0.4
	 *
	 * @param array[] $linkSpecs array of arrays, in which each sub-array representa a link and
	 * contains the links's site ID and page name in the fields designated by $sideIdKey
	 * and $pageNameKey, respectively.
	 *
	 * @param string $siteIdKey the field in which to find the link's target site id.
	 * @param string $pageNameKey the field in which to find the link's target page name.
	 *
	 * @return SiteLink[]
	 */
	public static function siteLinksFromArray( array $linkSpecs,
			$siteIdKey = 'site', $pageNameKey = 'title' ) {
		$links = array();

		foreach ( $linkSpecs as $spec ) {
			$siteId = $spec[ $siteIdKey ];
			$pageName = $spec[ $pageNameKey ];

			$links[] = SiteLink::newFromText( $siteId, $pageName );
		}

		return $links;
	}

}
