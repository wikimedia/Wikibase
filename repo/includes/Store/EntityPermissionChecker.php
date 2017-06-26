<?php

namespace Wikibase\Repo\Store;

use InvalidArgumentException;
use Status;
use User;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;

/**
 * Service interface for checking a user's permissions on a given entity.
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
interface EntityPermissionChecker {

	// TODO: Rename to ACTION_...
	const PERMISSION_READ = 'read';

	const PERMISSION_EDIT = 'edit';

	const PERMISSION_CREATE = 'create';

	const PERMISSION_EDIT_TERMS = 'term';

	const PERMISSION_MERGE = 'merge';

	const PERMISSION_REDIRECT = 'redirect';

	/**
	 * Check whether the given user has the given permission on an entity.
	 * This will perform a check based on the entity's ID if the entity has an ID set
	 * (that is, the entity "exists"), or based merely on the entity type, in case
	 * the entity does not exist.
	 *
	 * @param User $user
	 * @param string $permission
	 * @param EntityDocument $entity
	 * @param string $quick Flag for allowing quick permission checking. If set to
	 * 'quick', implementations may return inaccurate results if determining the accurate result
	 * would be slow (e.g. checking for cascading protection).
	 * This is intended as an optimization for non-critical checks,
	 * e.g. for showing or hiding UI elements.
	 *
	 * @throws InvalidArgumentException if unknown permission is requested
	 *
	 * @return Status a status object representing the check's result.
	 */
	public function getPermissionStatusForEntity( User $user, $permission, EntityDocument $entity, $quick = '' );

	/**
	 * Check whether the given user has the given permission on an entity.
	 * This requires the ID of an existing entity.
	 *
	 * @param User $user
	 * @param string $permission
	 * @param EntityId $entityId
	 * @param string $quick Flag for allowing quick permission checking. If set to
	 * 'quick', implementations may return inaccurate results if determining the accurate result
	 * would be slow (e.g. checking for cascading protection).
	 * This is intended as an optimization for non-critical checks,
	 * e.g. for showing or hiding UI elements.
	 *
	 * @throws InvalidArgumentException if unknown permission is requested
	 *
	 * @return Status a status object representing the check's result.
	 */
	public function getPermissionStatusForEntityId( User $user, $permission, EntityId $entityId, $quick = '' );

	/**
	 * Check whether the given user has the given permission on a given entity type.
	 * This does not require an entity to exist.
	 *
	 * Useful especially for checking whether the user is allowed to create an entity
	 * of a given type.
	 *
	 * @param User $user
	 * @param string $permission
	 * @param string $type
	 * @param string $quick Flag for allowing quick permission checking. If set to
	 * 'quick', implementations may return inaccurate results if determining the accurate result
	 * would be slow (e.g. checking for cascading protection).
	 * This is intended as an optimization for non-critical checks,
	 * e.g. for showing or hiding UI elements.
	 *
	 * @throws InvalidArgumentException if unknown permission is requested
	 *
	 * @return Status a status object representing the check's result.
	 */
	public function getPermissionStatusForEntityType( User $user, $permission, $type, $quick = '' );

}
