<?php

namespace Wikibase;

/**
 * Class representing a link to another site, based upon the Sites class.
 *
 * @since 0.1
 *
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class SiteLink {

	/**
	 * Creates a new SiteLink representing a link to the given page on the given site. The page title is normalized
	 * for the SiteLink object is created. If you already have a normalized page title, use the constructor directly.
	 *
	 * @note  : If $normalize is set, this may cause an API request to the remote site, so beware that this function may
	 *          be slow slow and depend on an external service.
	 *
	 * @param String $siteID     The site's global ID, to be used with Sites::singleton()->getSiteByGlobalId().
	 * @param String $page       The target page's title
	 * @param bool   $normalize  Whether the page title should be normalized (default: false)
	 *
	 * @see \Wikibase\Site::normalizePageName()
	 *
	 * @return \Wikibase\SiteLink the new SiteLink
	 * @throws \MWException if the $siteID isn't known.
	 */
	public static function newFromText( $siteID, $page, $normalize = false ) {
		$site = Sites::singleton()->getSiteByGlobalId( $siteID );

		if ( $site === false ) {
			$site = Sites::newSite( array( 'global_key' => $siteID ) );
		}

		if ( $normalize ) {
			$normalized = $site->normalizePageName( $page );

			if ( $normalized === false ) {
				throw new \MWException( "failed to normalize title: $page" );
			}

			$page = $normalized;
		}

		return new SiteLink( $site, $page );
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
	 * @param Site   $site  The site the page link points to
	 * @param String $page  The target page's title. This is expected to already be normalized.
	 */
	public function __construct( Site $site, $page ) {
		if ( !is_string( $page ) ) {
			throw new \MWException( '$page must be a string' );
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
	 * Returns the database form of the target page's title, to be used in MediaWiki URLs.
	 *
	 * @since 0.1
	 *
	 * @return String
	 */
	public function getDBKey() {
		return self::toDBKey( $this->page );
	}

	/**
	 * Returns the target site's global ID.
	 *
	 * @since 0.1
	 *
	 * @return String
	 */
	public function getSiteID() {
		return $this->site->getGlobalId();
	}

	/**
	 * Returns the target site's Site object
	 *
	 * @since 0.1
	 *
	 * @return Site
	 */
	public function getSite() {
		return $this->site;
	}

	/**
	 * Returns the target pages's full URL.
	 * Note that depending on the SiteTable, the resulting URL may be protocol relative (i.e. start with //).
	 *
	 * @since 0.1
	 *
	 * @return String|bool The URL of the page, or false if the target site is not known to the Sites class.
	 */
	public function getUrl() {
		if ( $this->site->getUrl() === null
			|| $this->site->getUrl() === false
			|| $this->site->getUrl() === '' ) {

			return false;
		}

		return $this->site->getPagePath( $this->getDBKey() );
	}

	/**
	 * Returns a string representation of this site link
	 *
	 * @since 0.1
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->getSiteID() . ':' . $this->getDBKey();
	}

	/**
	 * Returns the database form of the given title.
	 *
	 * @since 0.1
	 *
	 * @param String $title the target page's title, in normalized form.
	 *
	 * @return String
	 */
	public static function toDBKey( $title ) {
		return str_replace( ' ', '_', $title );
	}
}