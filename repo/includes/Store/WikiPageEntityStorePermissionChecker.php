<?php

namespace Wikibase\Repo\Store;

use InvalidArgumentException;
use Status;
use Title;
use User;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Lib\Store\EntityTitleLookup;

/**
 * Checks permissions to perform actions on the entity based on MediaWiki page permissions.
 *
 * @license GPL-2.0+
 */
class WikiPageEntityStorePermissionChecker implements EntityPermissionChecker {

	/**
	 * @var EntityNamespaceLookup
	 */
	private $namespaceLookup;

	/**
	 * @var EntityTitleLookup
	 */
	private $titleLookup;

	/**
	 * @var string[]
	 */
	private $availableRights;

	/**
	 * @param EntityNamespaceLookup $namespaceLookup
	 * @param EntityTitleLookup $titleLookup
	 * @param string[] $availableRights
	 */
	public function __construct(
		EntityNamespaceLookup $namespaceLookup,
		EntityTitleLookup $titleLookup,
		array $availableRights
	) {
		$this->namespaceLookup = $namespaceLookup;
		$this->titleLookup = $titleLookup;
		$this->availableRights = $availableRights;
	}

	/**
	 * @see EntityPermissionChecker::getPermissionStatusForEntity
	 *
	 * @param User $user
	 * @param string $action
	 * @param EntityDocument $entity
	 * @param string $quick
	 *
	 * @throws InvalidArgumentException if unknown permission is requested
	 *
	 * @return Status
	 */
	public function getPermissionStatusForEntity( User $user, $action, EntityDocument $entity, $quick = '' ) {
		$id = $entity->getId();

		if ( $id === null ) {
			$entityType = $entity->getType();

			if ( $action === EntityPermissionChecker::ACTION_EDIT ) {
				// for editing a non-existing page, check the create permission
				return $this->getPermissionStatusForEntityType( $user, EntityPermissionChecker::ACTION_CREATE, $entityType, $quick );
			}

			return $this->getPermissionStatusForEntityType( $user, $action, $entityType, $quick );
		}

		return $this->getPermissionStatusForEntityId( $user, $action, $id, $quick );
	}

	/**
	 * @see EntityPermissionChecker::getPermissionStatusForEntityId
	 *
	 * @param User $user
	 * @param string $action
	 * @param EntityId $entityId
	 * @param string $quick
	 *
	 * @throws InvalidArgumentException if unknown permission is requested
	 *
	 * @return Status
	 */
	public function getPermissionStatusForEntityId( User $user, $action, EntityId $entityId, $quick = '' ) {
		$title = $this->titleLookup->getTitleForId( $entityId );

		if ( $title === null || !$title->exists() ) {
			if ( $action === EntityPermissionChecker::ACTION_EDIT ) {
				return $this->getPermissionStatusForEntityType(
					$user,
					EntityPermissionChecker::ACTION_CREATE,
					$entityId->getEntityType(),
					$quick
				);
			}

			return $this->getPermissionStatusForEntityType(
				$user,
				$action,
				$entityId->getEntityType(),
				$quick
			);
		}

		return $this->checkPermissionsForAction( $user, $action, $title, $entityId->getEntityType(), $quick );
	}

	/**
	 * @see EntityPermissionChecker::getPermissionStatusForEntityType
	 *
	 * @param User $user
	 * @param string $action
	 * @param string $type
	 * @param string $quick
	 *
	 * @throws InvalidArgumentException if unknown permission is requested
	 *
	 * @return Status
	 */
	public function getPermissionStatusForEntityType( User $user, $action, $type, $quick = '' ) {
		$title = $this->getPageTitleInEntityNamespace( $type );

		if ( $action === EntityPermissionChecker::ACTION_EDIT ) {
			// Note: No entity ID given, assuming creating new entity, i.e. create permissions will be checked
			return $this->checkPermissionsForAction(
				$user,
				EntityPermissionChecker::ACTION_CREATE,
				$title,
				$type,
				$quick
			);
		}

		return $this->checkPermissionsForAction( $user, $action, $title, $type, $quick );
	}

	/**
	 * @param string $entityType
	 *
	 * @return Title
	 */
	private function getPageTitleInEntityNamespace( $entityType ) {
		$namespace = $this->namespaceLookup->getEntityNamespace( $entityType ); // TODO: can be null!

		return Title::makeTitle( $namespace, '/' );
	}

	private function checkPermissionsForAction( User $user, $action, Title $title, $entityType, $quick ='' ) {
		$status = Status::newGood();

		$mediaWikiPermissions = $this->getMediaWikiPermissionsToCheck( $action, $entityType );

		foreach ( $mediaWikiPermissions as $mwPermission ) {
			$partialStatus = $this->getPermissionStatus( $user, $mwPermission, $title, $quick );
			$status->merge( $partialStatus );
		}

		return $status;
	}

	private function getMediaWikiPermissionsToCheck( $action, $entityType ) {
		if ( $action === EntityPermissionChecker::ACTION_CREATE ) {
			$entityTypeSpecificCreatePermission = $entityType . '-create';

			$permissions = [ 'read', 'edit', 'createpage' ];
			if ( $this->mediawikiPermissionExists( $entityTypeSpecificCreatePermission ) ) {
				$permissions[] = $entityTypeSpecificCreatePermission;
			}
			return $permissions;
		}

		if ( $action === EntityPermissionChecker::ACTION_EDIT_TERMS ) {
			$entityTypeSpecificEditTermsPermission = $entityType . '-term';

			$permissions = [ 'read', 'edit' ];
			if ( $this->mediawikiPermissionExists( $entityTypeSpecificEditTermsPermission ) ) {
				$permissions[] = $entityTypeSpecificEditTermsPermission;
			}
			return $permissions;
		}

		if ( $action === EntityPermissionChecker::ACTION_MERGE ) {
			$entityTypeSpecificMergePermission = $entityType . '-merge';

			$permissions = [ 'read', 'edit' ];
			if ( $this->mediawikiPermissionExists( $entityTypeSpecificMergePermission ) ) {
				$permissions[] = $entityTypeSpecificMergePermission;
			}
			return $permissions;
		}

		if ( $action === EntityPermissionChecker::ACTION_REDIRECT ) {
			$entityTypeSpecificRedirectPermission = $entityType . '-redirect';

			$permissions = [ 'read', 'edit' ];
			if ( $this->mediawikiPermissionExists( $entityTypeSpecificRedirectPermission ) ) {
				$permissions[] = $entityTypeSpecificRedirectPermission;
			}
			return $permissions;
		}

		if ( $action === EntityPermissionChecker::ACTION_EDIT ) {
			return [ 'read', 'edit' ];
		}

		if ( $action === EntityPermissionChecker::ACTION_READ ) {
			return [ 'read' ];
		}

		throw new InvalidArgumentException( 'Unknown action to check permissions for: ' . $action );
	}

	private function mediawikiPermissionExists( $permission ) {
		return in_array( $permission, $this->availableRights );
	}

	private function getPermissionStatus( User $user, $permission, Title $title, $quick = '' ) {
		$status = Status::newGood();

		$errors = $title->getUserPermissionsErrors( $permission, $user, $quick !== 'quick' );

		if ( $errors ) {
			$status->setResult( false );
			foreach ( $errors as $error ) {
				call_user_func_array( [ $status, 'fatal' ], $error );
			}
		}

		return $status;
	}

}
