<?php

namespace Wikibase\Store;

use Title;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\Lib\Store\StorageException;

/**
 * Service interface for looking up EntityIds given local wiki pages.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 *
 */
interface EntityIdLookup {

	/**
	 * Returns the list of EntityIds of the entities associated with the
	 * given page titles. The resulting array uses the page IDs as keys.
	 *
	 * Implementations may omit non-existing entities from the result.
	 * If they are included, they should have negative array keys.
	 *
	 * @param Title[] $titles
	 *
	 * @throws StorageException
	 * @return EntityId[] Entity IDs, keyed by page IDs.
	 */
	public function getEntityIds( array $titles );

	/**
	 * Returns the ID of the entity stored on the page identified by $title.
	 *
	 * @note There is no guarantee that the EntityId returned by this method refers to
	 * an existing entity.
	 *
	 * @param Title $title
	 *
	 * @todo: Switch this to using TitleValue once we can easily get the content model and
	 * handler based on a TitleValue.
	 *
	 * @return EntityId|null
	 */
	public function getEntityIdForTitle( Title $title );

}
