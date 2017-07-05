<?php

namespace Wikibase\Repo\Store;

use Status;
use Title;
use User;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Lib\Store\EntityTitleLookup;

/**
 * Checks permissions to do actions on the entity based on MediaWiki page permissions.
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

	public function __construct( EntityNamespaceLookup $namespaceLookup, EntityTitleLookup $titleLookup ) {
		$this->namespaceLookup = $namespaceLookup;
		$this->titleLookup = $titleLookup;
	}

	/**
	 * @see EntityPermissionChecker::getPermissionStatusForEntity
	 *
	 * @param User $user
	 * @param string $permission
	 * @param EntityDocument $entity
	 * @param string $quick
	 *
	 * @return Status
	 */
	public function getPermissionStatusForEntity( User $user, $permission, EntityDocument $entity, $quick = '' ) {
		$id = $entity->getId();
		$status = null;

		if ( $id === null ) {
			$entityType = $entity->getType();

			if ( $permission === EntityPermissionChecker::PERMISSION_EDIT ) {
				// for editing a non-existing page, check the create permission
				return $this->getPermissionStatusForEntityType( $user, EntityPermissionChecker::PERMISSION_CREATE, $entityType, $quick );
			}

			return $this->getPermissionStatusForEntityType( $user, $permission, $entityType, $quick );
		}

		return $this->getPermissionStatusForEntityId( $user, $permission, $id, $quick );
	}

	/**
	 * @see EntityPermissionChecker::getPermissionStatusForEntityId
	 *
	 * @param User $user
	 * @param string $permission
	 * @param EntityId $entityId
	 * @param string $quick
	 *
	 * @return Status
	 */
	public function getPermissionStatusForEntityId( User $user, $permission, EntityId $entityId, $quick = '' ) {
		$title = $this->titleLookup->getTitleForId( $entityId );

		if ( $title === null || !$title->exists() ) {
			if ( $permission === EntityPermissionChecker::PERMISSION_EDIT ) {
				return $this->getPermissionStatusForEntityType(
					$user,
					EntityPermissionChecker::PERMISSION_CREATE,
					$entityId->getEntityType(),
					$quick
				);
			}

			return $this->getPermissionStatusForEntityType(
				$user,
				$permission,
				$entityId->getEntityType(),
				$quick
			);
		}

		return $this->checkPermission( $user, $permission, $title, $entityId->getEntityType(), $quick );
	}

	/**
	 * @see EntityPermissionChecker::getPermissionStatusForEntityType
	 *
	 * @param User $user
	 * @param string $permission
	 * @param string $type
	 * @param string $quick
	 *
	 * @return Status
	 */
	public function getPermissionStatusForEntityType( User $user, $permission, $type, $quick = '' ) {
		$title = $this->getPageTitleInEntityNamespace( $type );

		if ( $permission === EntityPermissionChecker::PERMISSION_EDIT ) {
			// Note: No entity ID given, assuming creating new entity, i.e. create permissions will be checked
			return $this->checkPermission(
				$user,
				EntityPermissionChecker::PERMISSION_CREATE,
				$title,
				$type,
				$quick
			);
		}

		return $this->checkPermission( $user, $permission, $title, $type, $quick );
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

	private function checkPermission( User $user, $permission, Title $title, $entityType, $quick ='' ) {
		$status = Status::newGood();

		$permissions = $this->getMediaWikiPermissionsToCheck( $permission, $entityType );

		foreach ( $permissions as $permission ) {
			$partialStatus = $this->getPermissionStatus( $user, $permission, $title, $quick );
			$status->merge( $partialStatus );
		}

		return $status;
	}

	private function getMediaWikiPermissionsToCheck( $permission, $entityType ) {
		global $wgAvailableRights;

		if ( $permission === EntityPermissionChecker::PERMISSION_CREATE ) {
			$hasEntityTypeCreateRight = in_array( $entityType . '-create', $wgAvailableRights );

			$permissions = [ 'edit', 'createpage' ];
			if ( $hasEntityTypeCreateRight ) {
				$permissions[] = $entityType . '-create';
			}
			return $permissions;
		}

		if ( $permission === EntityPermissionChecker::PERMISSION_EDIT_TERMS ) {
			$hasEntityTypeEditTermsRight = in_array( $entityType . '-term', $wgAvailableRights );

			$permissions = [ 'edit' ];
			if ( $hasEntityTypeEditTermsRight ) {
				$permissions[] = $entityType . '-term';
			}
			return $permissions;
		}

		if ( $permission === EntityPermissionChecker::PERMISSION_MERGE ) {
			$hasEntityTypeMergeRight = in_array( $entityType . '-merge', $wgAvailableRights );

			$permissions = [ 'edit' ];
			if ( $hasEntityTypeMergeRight ) {
				$permissions[] = $entityType . '-merge';
			}
			return $permissions;
		}

		if ( $permission === EntityPermissionChecker::PERMISSION_REDIRECT ) {
			$hasEntityTypeRedirectRight = in_array( $entityType . '-redirect', $wgAvailableRights );

			$permissions = [ 'edit' ];
			if ( $hasEntityTypeRedirectRight ) {
				$permissions[] = $entityType . '-redirect';
			}
			return $permissions;
		}

		if ( $permission === EntityPermissionChecker::PERMISSION_EDIT ) {
			return [ 'edit' ];
		}

		if ( $permission === EntityPermissionChecker::PERMISSION_READ ) {
			return [ 'read' ];
		}

		// TODO: what to do when some unexpected permission gets passed in?
		return [ $permission ];
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
