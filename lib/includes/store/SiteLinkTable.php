<?php

namespace Wikibase;
use MWException;

/**
 * Represents a lookup database table for sitelinks.
 * It should have these fields: ips_item_id, ips_site_id, ips_site_page.
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
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 */
class SiteLinkTable extends \DBAccessBase implements SiteLinkCache {

	/**
	 * @since 0.1
	 *
	 * @var string
	 */
	protected $table;

	/**
	 * @since 0.3
	 *
	 * @var bool
	 */
	protected $readonly;

	/**
	 * @since 0.1
	 *
	 * @param string $table The table to use for the sitelinks
	 * @param string|bool $wiki The wiki's database to connect to.
	 *        Must be a value LBFactory understands. Defaults to false, which is the local wiki.
	 * @param bool|null $readonly Whether the table can be modified. Defaults to $wiki, because
	 *        only the local wiki's database should normally be modified.
	 */
	public function __construct( $table, $wiki = false, $readonly = null ) {
		$this->table = $table;
		$this->wiki = $wiki;
		$this->readonly = $readonly === null ? $wiki === false : $readonly;

		assert( is_bool( $this->readonly ) );
	}

	/**
	 * @see SiteLinkCache::saveLinksOfItem
	 *
	 * @since 0.1
	 *
	 * @param Item $item
	 * @param string|null $function
	 *
	 * @return boolean Success indicator
	 */
	public function saveLinksOfItem( Item $item, $function = null ) {
		$function = is_null( $function ) ? __METHOD__ : $function;

		if ( is_null( $item->getId() ) ) {
			return false;
		}

		$dbw = $this->getConnection( DB_MASTER );
	
		$success = $this->deleteLinksOfItem( $item->getId(), $function );

		if ( !$success ) {
			$this->releaseConnection( $dbw );
			return false;
		}

		$siteLinks = $item->getSiteLinks();

		if ( empty( $siteLinks ) ) {
			$this->releaseConnection( $dbw );
			return true;
		}

		$transactionLevel = $dbw->trxLevel();

		if ( !$transactionLevel ) {
			$dbw->begin( __METHOD__ );
		}

		/**
		 * @var SiteLink $siteLink
		 */
		foreach ( $siteLinks as $siteLink ) {
			$success = $dbw->insert(
				$this->table,
				array_merge(
					array( 'ips_item_id' => $item->getId()->getNumericId() ),
					array(
						'ips_site_id' => $siteLink->getSite()->getGlobalId(),
						'ips_site_page' => $siteLink->getPage(),
					)
				),
				$function
			) && $success;
		}

		if ( !$transactionLevel ) {
			$dbw->commit( __METHOD__ );
		}

		$this->releaseConnection( $dbw );
		return $success;
	}

	/**
	 * @see SiteLinkCache::deleteLinksOfItem
	 *
	 * @since 0.1
	 *
	 * @param EntityId $itemId
	 * @param string|null $function
	 *
	 * @return boolean Success indicator
	 */
	public function deleteLinksOfItem( EntityId $itemId, $function = null ) {
		$dbw = wfGetDB( DB_MASTER );

		$ok = $dbw->delete(
			$this->table,
			array( 'ips_item_id' => $itemId->getNumericId() ),
			is_null( $function ) ? __METHOD__ : $function
		);

		$this->releaseConnection( $dbw );
		return $ok;
	}

	/**
	 * @see SiteLinkLookup::getItemIdForLink
	 *
	 * @since 0.1
	 *
	 * @param string $globalSiteId
	 * @param string $pageTitle
	 *
	 * @return integer|boolean
	 */
	public function getItemIdForLink( $globalSiteId, $pageTitle ) {
		$db = $this->getConnection( DB_SLAVE );

		$result = $db->selectRow(
			$this->table,
			array( 'ips_item_id' ),
			array(
				'ips_site_id' => $globalSiteId,
				'ips_site_page' => $pageTitle,
			)
		);

		$this->releaseConnection( $db );
		return $result === false ? false : (int)$result->ips_item_id;
	}

	/**
	 * @see SiteLinkLookup::getConflictsForItem
	 *
	 * @since 0.1
	 *
	 * @param Item $item
	 *
	 * @return array of array
	 */
	public function getConflictsForItem( Item $item ) {
		$links = $item->getSiteLinks();

		if ( $links === array() ) {
			return array();
		}

		$dbr = $this->getConnection( DB_SLAVE );

		$anyOfTheLinks = '';

		/**
		 * @var SiteLink $siteLink
		 */
		foreach ( $links as $siteLink ) {
			if ( $anyOfTheLinks !== '' ) {
				$anyOfTheLinks .= "\nOR ";
			}

			$anyOfTheLinks .= '(';
			$anyOfTheLinks .= 'ips_site_id=' . $dbr->addQuotes( $siteLink->getSite()->getGlobalId() );
			$anyOfTheLinks .= ' AND ';
			$anyOfTheLinks .= 'ips_site_page=' . $dbr->addQuotes( $siteLink->getPage() );
			$anyOfTheLinks .= ')';
		}

		//TODO: $anyOfTheLinks might get very large and hit some size limit imposed by the database.
		//      We could chop it up of we know that size limit. For MySQL, it's select @@max_allowed_packet.

		$conflictingLinks = $dbr->select(
			$this->table,
			array(
				'ips_site_id',
				'ips_site_page',
				'ips_item_id',
			),
			"($anyOfTheLinks) AND ips_item_id != " . $item->getId()->getNumericId(),
			__METHOD__
		);

		$conflicts = array();

		foreach ( $conflictingLinks as $link ) {
			$conflicts[] = array(
				'siteId' => $link->ips_site_id,
				'itemId' => (int)$link->ips_item_id,
				'sitePage' => $link->ips_site_page,
			);
		}

		$this->releaseConnection( $dbr );
		return $conflicts;
	}

	/**
	 * @see SiteLinkCache::clear
	 *
	 * @since 0.2
	 *
	 * @return boolean Success indicator
	 */
	public function clear() {
		$dbw = $this->getConnection( DB_MASTER );

		$ok = $dbw->delete( $this->table, '*', __METHOD__ );

		$this->releaseConnection( $dbw );
		return $ok;
	}

	/**
	 * @see SiteLinkLookup::countLinks
	 *
	 * @since 0.3
	 *
	 * @param array $itemIds
	 * @param array $siteIds
	 * @param array $pageNames
	 *
	 * @return integer
	 */
	public function countLinks( array $itemIds, array $siteIds = array(), array $pageNames = array() ) {
		$dbr = $this->getConnection( DB_SLAVE );

		$conditions = array();

		if ( $itemIds !== array() ) {
			$conditions['ips_item_id'] = $itemIds;
		}

		if ( $siteIds !== array() ) {
			$conditions['ips_site_id'] = $siteIds;
		}

		if ( $pageNames !== array() ) {
			$conditions['ips_site_page'] = $pageNames;
		}

		$res = $dbr->selectRow(
			$this->table,
			array( 'COUNT(*) AS rowcount' ),
			$conditions,
			__METHOD__
		)->rowcount;

		$this->releaseConnection( $dbr );
		return $res;
	}

	/**
	 * @see SiteLinkLookup::getLinks
	 *
	 * @since 0.3
	 *
	 * @param array $itemIds
	 * @param array $siteIds
	 * @param array $pageNames
	 *
	 * @return array[]
	 */
	public function getLinks( array $itemIds, array $siteIds = array(), array $pageNames = array() ) {
		$dbr = $this->getConnection( DB_SLAVE );

		$conditions = array();

		if ( $itemIds !== array() ) {
			$conditions['ips_item_id'] = $itemIds;
		}

		if ( $siteIds !== array() ) {
			$conditions['ips_site_id'] = $siteIds;
		}

		if ( $pageNames !== array() ) {
			$conditions['ips_site_page'] = $pageNames;
		}

		$links = $dbr->select(
			$this->table,
			array(
				'ips_site_id',
				'ips_site_page',
				'ips_item_id',
			),
			$conditions,
			__METHOD__
		);

		$siteLinks = array();

		foreach ( $links as $link ) {
			$siteLinks[] = array(
				$link->ips_site_id,
				$link->ips_site_page,
				$link->ips_item_id,
			);
		}

		$this->releaseConnection( $dbr );
		return $siteLinks;
	}

}
