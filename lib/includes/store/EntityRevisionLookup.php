<?php

namespace Wikibase;

/**
 * Contains methods for interaction with an entity store.
 *
 * @since 0.4
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
interface EntityRevisionLookup extends EntityLookup {

	/**
	 * Returns the entity revision with the provided id or null if there is no such
	 * entity. If a $revision is given, the requested revision of the entity is loaded.
	 * If that revision does not exist or does not belong to the given entity,
	 * an exception is thrown.
	 *
	 * @since 0.4
	 *
	 * @param EntityId $entityId
	 * @param int $revision The desired revision id, 0 means "current".
	 *
	 * @return EntityRevision|null
	 * @throw StorageException
	 */
	public function getEntityRevision( EntityId $entityId, $revision = 0 );

	/**
	 * Returns the id of the latest revision of the given entity, or false if there is no such entity.
	 *
	 * @param EntityId $entityId
	 *
	 * @return int|false
	 */
	public function getLatestRevisionId( EntityId $entityId );
}
