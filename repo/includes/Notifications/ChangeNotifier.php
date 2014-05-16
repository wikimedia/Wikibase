<?php

namespace Wikibase\Repo\Notifications;

use InvalidArgumentException;
use Revision;
use User;
use Wikibase\EntityChange;
use Wikibase\EntityContent;
use Wikibase\Lib\Changes\EntityChangeFactory;

/**
 * Class for generating and submitting change notifications in different situations.
 * This is a helper intended for use in hook handler functions.
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class ChangeNotifier {

	/**
	 * @var EntityChangeFactory
	 */
	private $changeFactory;

	/**
	 * @var ChangeNotificationChannel
	 */
	private $notificationChannel;

	public function __construct( EntityChangeFactory $changeFactory, ChangeNotificationChannel $notificationChannel ) {
		$this->changeFactory = $changeFactory;
		$this->notificationChannel = $notificationChannel;
	}

	/**
	 * This method constructs and sends the appropriate notifications (if any)
	 * when a wiki page containing an EntityContent was deleted.
	 *
	 * @param EntityContent $content The ID of the deleted entity
	 * @param User $user
	 * @param string $timestamp timestamp in TS_MW format.
	 *
	 * @throws InvalidArgumentException
	 * @throws ChangeNotificationException
	 *
	 * @return EntityChange|null
	 */
	public function notifyOnPageDeleted( EntityContent $content, User $user, $timestamp ) {
		wfProfileIn( __METHOD__ );

		if ( $content->isRedirect() ) {
			// TODO: notify the client about changes to redirects!
			return null;
		}

		$entity = $content->getEntity();

		$change = $this->changeFactory->newFromUpdate( EntityChange::REMOVE, $entity, null, array(
			'revision_id' => 0, // there's no current revision
			'user_id' => $user->getId(),
			'object_id' => $entity->getId()->getPrefixedId(),
			'time' => $timestamp,
		) );

		$change->setMetadataFromUser( $user );

		$this->notificationChannel->sendChangeNotification( $change );

		wfProfileOut( __METHOD__ );
		return $change;
	}

	/**
	 * This method constructs and sends the appropriate notifications (if any)
	 * when a wiki page containing an EntityContent was undeleted.
	 *
	 * @param EntityContent $content
	 * @param User $user
	 * @param int $revisionId
	 * @param string $timestamp timestamp in TS_MW format.
	 *
	 * @throws InvalidArgumentException
	 * @throws ChangeNotificationException
	 *
	 * @return EntityChange|null
	 */
	public function notifyOnPageUndeleted( EntityContent $content, User $user, $revisionId, $timestamp ) {
		wfProfileIn( __METHOD__ );

		if ( $content->isRedirect() ) {
			// TODO: notify the client about changes to redirects!
			return null;
		}

		$entity = $content->getEntity();

		$change = $this->changeFactory->newFromUpdate( EntityChange::RESTORE, null, $entity, array(
			// TODO: Use timestamp of log entry, but needs core change.
			// This hook is called before the log entry is created.
			'revision_id' => $revisionId,
			'user_id' => $user->getId(),
			'object_id' => $entity->getId()->getPrefixedId(),
			'time' => $timestamp
		) );

		$change->setMetadataFromUser( $user );

		$this->notificationChannel->sendChangeNotification( $change );

		wfProfileOut( __METHOD__ );
		return $change;
	}

	/**
	 * This method constructs and sends the appropriate notifications (if any)
	 * when a wiki page containing an EntityContent was created.
	 *
	 * @param Revision $revision
	 *
	 * @throws InvalidArgumentException
	 * @throws ChangeNotificationException
	 *
	 * @return EntityChange|null
	 */
	public function notifyOnPageCreated( Revision $revision ) {
		wfProfileIn( __METHOD__ );

		/* @var EntityContent $newContent */
		$newContent = $revision->getContent();

		if ( $newContent->isRedirect() ) {
			// Clients currently don't care about redirected being created.
			// TODO: notify the client about changes to redirects!
			return null;
		}

		$newEntity = $newContent->getEntity();

		$change = $this->changeFactory->newFromUpdate( EntityChange::ADD, null, $newEntity );

		$change->setFields( array(
			'revision_id' => $revision->getId(),
			'user_id' => $revision->getUser(),
			'object_id' => $newContent->getEntityId()->getSerialization(),
			'time' => $revision->getTimestamp(),
		) );

		// FIXME: RepoHooks::onRecentChangeSave currently adds to the change later!
		$this->notificationChannel->sendChangeNotification( $change );

		wfProfileOut( __METHOD__ );
		return $change;
	}

	/**
	 * This method constructs and sends the appropriate notifications (if any)
	 * when a wiki page containing an EntityContent was modified.
	 *
	 * @param Revision $current
	 * @param Revision $parent
	 *
	 * @throws InvalidArgumentException
	 * @throws ChangeNotificationException
	 *
	 * @return EntityChange|null
	 */
	public function notifyOnPageModified( Revision $current, Revision $parent ) {
		wfProfileIn( __METHOD__ );

		if ( $current->getParentId() !== $parent->getId() ) {
			throw new InvalidArgumentException( '$parent->getId() must be the same as $current->getParentId()!' );
		}

		/* @var EntityContent $newContent */
		$newContent = $current->getContent();

		/* @var EntityContent $oldContent */
		$oldContent = $parent->getContent();

		$change = $this->getChangeForModification( $oldContent, $newContent );

		if ( !$change ) {
			// nothing to do
			return null;
		}

		$change->setFields( array(
			'revision_id' => $current->getId(),
			'user_id' => $current->getUser(),
			'object_id' => $newContent->getEntityId()->getPrefixedId(),
			'time' => $current->getTimestamp(),
		) );

		// FIXME: RepoHooks::onRecentChangeSave currently adds to the change later!
		$this->notificationChannel->sendChangeNotification( $change );

		wfProfileOut( __METHOD__ );
		return $change;
	}

	/**
	 * Returns a EntityChange based on the old and new content object, taking
	 * redirects into consideration.
	 *
	 * @todo: Notify the client about changes to redirects explicitly.
	 *
	 * @param EntityContent $oldContent
	 * @param EntityContent $newContent
	 *
	 * @return EntityChange|null
	 */
	private function getChangeForModification( EntityContent $oldContent, EntityContent $newContent ) {
		$newEntity = null;
		$oldEntity = null;

		if ( $newContent->isRedirect() ) {
			if ( $oldContent->isRedirect() ) {
				// Noting to do, since the new content is a redirect, and the
				// old content was a redirect too (or the page was just created).
				$action = null;
			} else {
				// An entity page was turned into a redirect. Currently handled like a deletion.
				// Note that we already know that $oldContent exists and isn't a redirect.
				$action = EntityChange::REMOVE;
				$oldEntity = $oldContent->getEntity();
			}
		} else { // !$newContent->isRedirect()
			$newEntity = $newContent->getEntity();

			if ( $oldContent->isRedirect() ) {
				// A redirect page was turned (back) into an entity
				$action = EntityChange::RESTORE;
			} else {
				// An entity page was updated
				$action = EntityChange::UPDATE;
				$oldEntity = $oldContent->getEntity();
			}
		}

		if ( $action === null ) {
			return null;
		} else {
			$change = $this->changeFactory->newFromUpdate( $action, $oldEntity, $newEntity );
			return $change;
		}
	}

}
