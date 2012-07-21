<?php

namespace Wikibase;

/**
 * Interface for objects that represent a single Wikibase item.
 * See https://meta.wikimedia.org/wiki/Wikidata/Data_model#Items
 *
 * @since 0.1
 *
 * @file
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
interface Item extends Entity {

	/**
	 * Adds a site link.
	 *
	 * @since 0.1
	 *
	 * @param SiteLink $link the link to the target page
	 * @param string $updateType
	 *
	 * @return array|false Returns array on success, or false on failure
	 */
	public function addSiteLink( SiteLink $link, $updateType = 'add' );

	/**
	 * Removes a site link.
	 *
	 * @since 0.1
	 *
	 * @param string $siteId
	 * @param string $pageName
	 *
	 * @return bool Success indicator
	 */
	public function removeSiteLink( $siteId, $pageName = false );

	/**
	 * Returns the site links in an associative array with the following format:
	 * site id (str) => SiteLink
	 *
	 * @since 0.1
	 *
	 * @return array an array of SiteLink objects
	 */
	public function getSiteLinks();

	/**
	 * Returns the site link for the given site id, or null.
	 *
	 * @since 0.1
	 *
	 * @param String $siteId the id of the site to which to get the lin
	 *
	 * @return SiteLink the site link
	 */
	public function getSiteLink( $siteId );

	/**
	 * Returns a deep copy of the item.
	 *
	 * @since 0.1
	 *
	 * @return Item
	 */
	public function copy();

}
