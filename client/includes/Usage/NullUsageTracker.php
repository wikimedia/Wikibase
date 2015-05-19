<?php

namespace Wikibase\Client\Usage;

use ArrayIterator;
use Iterator;
use Wikibase\DataModel\Entity\EntityId;

/**
 * No-op implementation of the UsageTracker and UsageLookup interfaces.
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class NullUsageTracker implements UsageTracker, UsageLookup {

	/**
	 * @see UsageTracker::trackUsedEntities
	 *
	 * @param int $pageId
	 * @param EntityUsage[] $usages
	 * @param string $touched
	 */
	public function trackUsedEntities( $pageId, array $usages, $touched ) {
		// no-op
	}

	/**
	 * @see UsageTracker::pruneStaleUsages
	 *
	 * @param int $pageId
	 * @param string $lastUpdatedBefore
	 *
	 * @return EntityUsage[]
	 */
	public function pruneStaleUsages( $pageId, $lastUpdatedBefore ) {
		return array();
	}

	/**
	 * @see UsageTracker::removeEntities
	 *
	 * @param EntityId[] $entityIds
	 */
	public function removeEntities( array $entityIds ) {
		// no-op
	}

	/**
	 * @see UsageTracker::getUsagesForPage
	 *
	 * @param int $pageId
	 *
	 * @return EntityUsage[]
	 */
	public function getUsagesForPage( $pageId ) {
		return array();
	}

	/**
	 * @see UsageLookup::getUnusedEntities
	 *
	 * @param EntityId[] $entityIds
	 *
	 * @return EntityId[]
	 */
	public function getUnusedEntities( array $entityIds ) {
		return array();
	}

	/**
	 * @see UsageLookup::getPagesUsing
	 *
	 * @param EntityId[] $entities
	 * @param string[] $aspects
	 *
	 * @return Iterator<PageEntityUsages>
	 */
	public function getPagesUsing( array $entities, array $aspects = array() ) {
		return new ArrayIterator( array() );
	}

}
