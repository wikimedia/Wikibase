<?php

namespace Wikibase\Lib\Store;

use Wikibase\DataModel\Entity\EntityId;

/**
 * A service interface for prefetching entities or data about them in order
 * to make subsequent loading of them faster.
 *
 * @since 0.5
 *
 * @license GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 */
interface EntityPrefetcher {

	/**
	 * Prefetches data for a list of entity ids.
	 *
	 * @param EntityId[] $entityIds
	 */
	public function prefetch( array $entityIds );

	/**
	 * Purges prefetched data about a given entity or all data.
	 *
	 * @param EntityId|null $entityId Null to purge all data
	 */
	public function purge( EntityId $entityId = null );

}
