<?php
 /**
 *
 * Copyright © 02.07.13 by the authors listed below.
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
 * @license GPL 2+
 * @file
 *
 * @author Daniel Kinzler
 */


namespace Wikibase\Test;


use Site;
use SiteList;
use SiteStore;
use TestSites;

/**
 * Class MockSiteStore
 * @package Wikibase\Test
 */
class MockSiteStore implements SiteStore {

	/**
	 * @var Site[]
	 */
	private $sites;

	/**
	 * Returns a SiteStore object that contains TestSites::getSites().
	 * The SiteStore is not not be backed by an actual database.
	 *
	 * @return SiteStore
	 */
	public static function newFromTestSites() {
			$store = new MockSiteStore( TestSites::getSites() );
			return $store;
	}

	/**
	 * @param array $sites
	 */
	public function __construct( $sites = array() ) {
		$this->sites = $sites;
	}

	/**
	 * Saves the provided site.
	 *
	 * @since 1.21
	 *
	 * @param Site $site
	 *
	 * @return boolean Success indicator
	 */
	public function saveSite( Site $site ) {
		$this->sites[$site->getGlobalId()] = $site;
	}

	/**
	 * Saves the provided sites.
	 *
	 * @since 1.21
	 *
	 * @param Site[] $sites
	 *
	 * @return boolean Success indicator
	 */
	public function saveSites( array $sites ) {
		foreach ( $sites as $site ) {
			$this->saveSite( $site );
		}
	}

	/**
	 * Returns the site with provided global id, or null if there is no such site.
	 *
	 * @since 1.21
	 *
	 * @param string $globalId
	 * @param string $source either 'cache' or 'recache'.
	 *                       If 'cache', the values are allowed (but not obliged) to come from a cache.
	 *
	 * @return Site|null
	 */
	public function getSite( $globalId, $source = 'cache' ) {
		if ( isset( $this->sites[$globalId] ) ) {
			return $this->sites[$globalId];
		} else {
			return null;
		}
	}

	/**
	 * Returns a list of all sites. By default this site is
	 * fetched from the cache, which can be changed to loading
	 * the list from the database using the $useCache parameter.
	 *
	 * @since 1.21
	 *
	 * @param string $source either 'cache' or 'recache'.
	 *                       If 'cache', the values are allowed (but not obliged) to come from a cache.
	 *
	 * @return SiteList
	 */
	public function getSites( $source = 'cache' ) {
		return new SiteList( $this->sites );
	}

	/**
	 * Deletes all sites from the database. After calling clear(), getSites() will return an empty
	 * list and getSite() will return null until saveSite() or saveSites() is called.
	 */
	public function clear() {
		$this->sites = array();
	}
}