<?php

namespace Wikibase;

/**
 * Contains methods for interaction with an entity store.
 *
 * @since 0.3
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 */
interface EntityLookup {

	/**
	 * Returns the entity with the provided id or null if there is no such
	 * entity. If a $revision is given, the requested revision of the entity is loaded.
	 * If that revision does not exist or does not belong to the given entity,
	 * an exception is thrown.
	 *
	 * @since 0.3
	 *
	 * @param EntityID $entityId
	 * @param int $revision The desired revision id, 0 means "current".
	 *
	 * @throw StorageException
	 * @return Entity|null
	 */
	public function getEntity( EntityID $entityId, $revision = 0 );

	/**
	 * Returns whether the given entity can bee looked up using
	 * getEntity(). This avoids loading and deserializing entity content
	 * just to check whether the entity exists.
	 *
	 * @since 0.4
	 *
	 * @param EntityID $entityId
	 *
	 * @throw StorageException
	 * @return bool
	 */
	public function hasEntity( EntityID $entityId );

}
