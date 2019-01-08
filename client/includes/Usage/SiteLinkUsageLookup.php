<?php

namespace Wikibase\Client\Usage;

use ArrayIterator;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Traversable;
use Wikibase\Client\Store\TitleFactory;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Store\SiteLinkLookup;

/**
 * UsageLookup implementation based on a SiteLinkLookup.
 * This tracks the usage of directly connected items as EntityUsage::SITELINK_USAGE.
 * Other types of usage are not tracked.
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class SiteLinkUsageLookup implements UsageLookup {

	/**
	 * @var string
	 */
	protected $clientSiteId;

	/**
	 * @var SiteLinkLookup
	 */
	protected $siteLinkLookup;

	/**
	 * @var TitleFactory
	 */
	private $titleFactory;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @param string $clientSiteId The local wiki's global site id
	 * @param SiteLinkLookup $siteLinkLookup
	 * @param TitleFactory $titleFactory
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( $clientSiteId, SiteLinkLookup $siteLinkLookup, TitleFactory $titleFactory ) {
		if ( !is_string( $clientSiteId ) ) {
			throw new InvalidArgumentException( '$clientSiteId must be a string' );
		}

		$this->clientSiteId = $clientSiteId;
		$this->siteLinkLookup = $siteLinkLookup;
		$this->titleFactory = $titleFactory;

		// TODO: Inject
		$this->logger = WikibaseClient::getDefaultInstance()->getLogger();
	}

	/**
	 * @see UsageLookup::getUsagesForPage
	 *
	 * @param int $pageId
	 *
	 * @return EntityUsage[]
	 * @throws UsageTrackerException
	 */
	public function getUsagesForPage( $pageId ) {
		$usages = [];

		$title = $this->titleFactory->newFromID( $pageId );
		$id = $this->siteLinkLookup->getItemIdForLink( $this->clientSiteId, $title->getPrefixedText() );

		if ( $id !== null ) {
			$usages[] = new EntityUsage( $id, EntityUsage::SITELINK_USAGE );
		}

		return $usages;
	}

	/**
	 * @see UsageLookup::getPagesUsing
	 *
	 * @param EntityId[] $entityIds
	 * @param string[] $aspects Which aspects to consider (if omitted, all aspects are considered).
	 * Use the EntityUsage::XXX_USAGE constants to represent aspects.
	 *
	 * @return Traversable of PageEntityUsages
	 * @throws UsageTrackerException
	 */
	public function getPagesUsing( array $entityIds, array $aspects = [] ) {
		if ( empty( $entityIds ) ) {
			return new ArrayIterator();
		}

		$numericItemIds = $this->getNumericItemIds( $entityIds );
		$rows = $this->siteLinkLookup->getLinks( $numericItemIds, [ $this->clientSiteId ] );

		$pageIds = $this->getPageEntityUsagesFromSiteLinkRows( $rows );
		return new ArrayIterator( $pageIds );
	}

	/**
	 * Extracts numeric IDs from ItemIds; Other EntityIds are ignored.
	 *
	 * @param EntityId[] $ids
	 *
	 * @return int[]
	 */
	private function getNumericItemIds( array $ids ) {
		$ids = array_filter( $ids, function ( EntityId $id ) {
			return $id instanceof ItemId;
		} );

		return array_map(
			function ( ItemId $id ) {
				return $id->getNumericId();
			},
			$ids
		);
	}

	/**
	 * @param array[] $rows Rows as returned by SiteLinkLookup::getLinks
	 *
	 * @return PageEntityUsages[]
	 */
	private function getPageEntityUsagesFromSiteLinkRows( array $rows ) {
		$titleFactory = $this->titleFactory;
		$pageEntityUsages = array_map(
			function ( array $row ) use ( $titleFactory ) {
				// $row = [ $siteId, $pageName, $numericItemId ];
				$itemId = ItemId::newFromNumber( $row[2] );
				$title = $titleFactory->newFromText( $row[1] );

				if ( !$title ) {
					return null;
				}

				// NOTE: since we don't know how the item is used on the linked page, assume "all" usage.
				$usage = new EntityUsage( $itemId, EntityUsage::ALL_USAGE );
				$pageId = $title->getArticleID();

				if ( $pageId === 0 ) {
					$this->logger->debug(
						'{method}: Article ID for {titleText} is 0.',
						[
							'method' => __METHOD__,
							'titleText' => $title->getFullText()
						]
					);

					return null;
				}

				return new PageEntityUsages( $pageId, [ $usage ] );
			},
			$rows
		);

		$pageEntityUsages = array_filter( $pageEntityUsages );
		return $pageEntityUsages;
	}

	/**
	 * @param array[] $rows Rows as returned by SiteLinkLookup::getLinks
	 *
	 * @return int[]
	 */
	private function getItemIdsFromSiteLinkRows( array $rows ) {
		$itemIds = array_map(
			function ( array $row ) {
				return (int)$row[2];
			},
			$rows
		);

		array_unique( $itemIds );
		return $itemIds;
	}

	/**
	 * @param int[] $numericIds
	 *
	 * @return ItemId[]
	 */
	private function makeItemIds( array $numericIds ) {
		return array_map(
			function ( $numericId ) {
				return ItemId::newFromNumber( $numericId );
			},
			$numericIds
		);
	}

	/**
	 * @see UsageLookup::getUnusedEntities
	 *
	 * @param EntityId[] $entityIds
	 *
	 * @return EntityId[] A list of elements of $entities that are unused.
	 */
	public function getUnusedEntities( array $entityIds ) {
		if ( empty( $entityIds ) ) {
			return [];
		}

		// Non-item entities are always considered unused by this implementation.
		$nonItemIds = array_filter( $entityIds, function ( EntityId $id ) {
			return !( $id instanceof ItemId );
		} );

		$numericItemIds = $this->getNumericItemIds( $entityIds );

		$rows = $this->siteLinkLookup->getLinks( $numericItemIds, [ $this->clientSiteId ] );

		$used = $this->getItemIdsFromSiteLinkRows( $rows );
		$unusedIds = array_diff( $numericItemIds, $used );

		return array_merge(
			$nonItemIds,
			$this->makeItemIds( $unusedIds )
		);
	}

}
