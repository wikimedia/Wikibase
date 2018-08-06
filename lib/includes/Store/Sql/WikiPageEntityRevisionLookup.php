<?php

namespace Wikibase\Lib\Store\Sql;

use DBAccessBase;
use MediaWiki\Storage\BlobAccessException;
use MediaWiki\Storage\BlobStore;
use MediaWiki\Storage\RevisionAccessException;
use MediaWiki\Storage\RevisionRecord;
use MediaWiki\Storage\RevisionStore;
use MWContentSerializationException;
use stdClass;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\Lib\Store\BadRevisionException;
use Wikibase\Lib\Store\EntityContentDataCodec;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\RevisionedUnresolvedRedirectException;
use Wikibase\Lib\Store\EntityRevision;
use Wikibase\Lib\Store\StorageException;
use Wikimedia\Assert\Assert;

/**
 * Implements an entity repo based on blobs stored in wiki pages on a locally reachable
 * database server. This class also supports memcached (or accelerator) based caching
 * of entities.
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class WikiPageEntityRevisionLookup extends DBAccessBase implements EntityRevisionLookup {

	/**
	 * @var EntityContentDataCodec
	 */
	private $contentCodec;

	/**
	 * @var WikiPageEntityMetaDataAccessor
	 */
	private $entityMetaDataAccessor;

	/**
	 * @var RevisionStore
	 */
	private $revisionStore;

	/**
	 * @var BlobStore
	 *
	 * @todo remove this once we no longer need to be compatible to the pre-MCR database schema
	 */
	private $blobStore;

	/**
	 * @param EntityContentDataCodec $contentCodec
	 * @param WikiPageEntityMetaDataAccessor $entityMetaDataAccessor
	 * @param RevisionStore $revisionStore
	 * @param BlobStore $blobStore
	 * @param string|bool $wiki The name of the wiki database to use (use false for the local wiki)
	 */
	public function __construct(
		EntityContentDataCodec $contentCodec,
		WikiPageEntityMetaDataAccessor $entityMetaDataAccessor,
		RevisionStore $revisionStore,
		BlobStore $blobStore,
		$wiki = false
	) {
		parent::__construct( $wiki );

		$this->contentCodec = $contentCodec;

		$this->entityMetaDataAccessor = $entityMetaDataAccessor;
		$this->revisionStore = $revisionStore;
		$this->blobStore = $blobStore;
	}

	/**
	 * @see   EntityRevisionLookup::getEntityRevision
	 *
	 * @param EntityId $entityId
	 * @param int $revisionId The desired revision id, or 0 for the latest revision.
	 * @param string $mode LATEST_FROM_REPLICA, LATEST_FROM_REPLICA_WITH_FALLBACK or
	 *        LATEST_FROM_MASTER.
	 *
	 * @throws RevisionedUnresolvedRedirectException
	 * @throws StorageException
	 * @return EntityRevision|null
	 */
	public function getEntityRevision(
		EntityId $entityId,
		$revisionId = 0,
		$mode = self::LATEST_FROM_REPLICA
	) {
		Assert::parameterType( 'integer', $revisionId, '$revisionId' );
		Assert::parameterType( 'string', $mode, '$mode' );

		wfDebugLog( __CLASS__, __FUNCTION__ . ': Looking up entity ' . $entityId
			. " (revision $revisionId)." );

		/** @var EntityRevision $entityRevision */
		$entityRevision = null;

		if ( $revisionId > 0 ) {
			$row = $this->entityMetaDataAccessor->loadRevisionInformationByRevisionId( $entityId, $revisionId, $mode );
		} else {
			$rows = $this->entityMetaDataAccessor->loadRevisionInformation( [ $entityId ], $mode );
			$row = $rows[$entityId->getSerialization()];
		}

		if ( $row ) {
			/** @var EntityRedirect $redirect */
			try {
				list( $entityRevision, $redirect ) = $this->loadEntity( $row );
			} catch ( MWContentSerializationException $ex ) {
				throw new StorageException( 'Failed to unserialize the content object.', 0, $ex );
			}

			if ( $redirect !== null ) {
				throw new RevisionedUnresolvedRedirectException(
					$entityId,
					$redirect->getTargetId(),
					(int)$row->rev_id,
					$row->rev_timestamp
				);
			}

			if ( $entityRevision === null ) {
				// This happens when there is a problem with the external store or if access is forbidden
				wfLogWarning( __METHOD__ . ': Entity not loaded for ' . $entityId );
			}
		}

		if ( $entityRevision !== null && !$entityRevision->getEntity()->getId()->equals( $entityId ) ) {
			// This can happen when giving a revision ID that doesn't belong to the given entity,
			// or some meta data is incorrect.
			$actualEntityId = $entityRevision->getEntity()->getId()->getSerialization();

			// Get the revision id we actually loaded, if none was passed explicitly
			$revisionId = $revisionId ?: $entityRevision->getRevisionId();
			throw new BadRevisionException( "Revision $revisionId belongs to $actualEntityId instead of expected $entityId" );
		}

		if ( $revisionId > 0 && $entityRevision === null ) {
			// If a revision ID was specified, but that revision doesn't exist:
			throw new BadRevisionException( "No such revision found for $entityId: $revisionId" );
		}

		return $entityRevision;
	}

	/**
	 * @see EntityRevisionLookup::getLatestRevisionId
	 *
	 * @param EntityId $entityId
	 * @param string $mode
	 *
	 * @return int|false
	 */
	public function getLatestRevisionId( EntityId $entityId, $mode = self::LATEST_FROM_REPLICA ) {
		$rows = $this->entityMetaDataAccessor->loadRevisionInformation( [ $entityId ], $mode );
		$row = $rows[$entityId->getSerialization()];

		if ( $row && $row->page_latest && !$row->page_is_redirect ) {
			return (int)$row->page_latest;
		}

		return false;
	}

	/**
	 * Construct an EntityRevision object from a database row from the revision and text tables.
	 *
	 * @param stdClass $row a row object as expected Revision::getRevisionText(). That is, it
	 *        should contain the relevant fields from the revision and/or text table.
	 *
	 * @throws StorageException
	 * @return object[] list( EntityRevision|null $entityRevision, EntityRedirect|null $entityRedirect )
	 * with either $entityRevision or $entityRedirect or both being null (but not both being non-null).
	 */
	private function loadEntity( $row ) {
		// TODO: WikiPageEntityMetaDataLookup should use RevisionStore::getQueryInfo,
		// then we could use RevisionStore::newRevisionFromRow here!
		$revision = $this->revisionStore->getRevisionById( $row->rev_id );
		$slotRole = $row->role_name ?? 'main';

		// NOTE: Support for cross-wiki content access in RevisionStore is incomplete when,
		// reading from the pre-MCR database schema, see T201194.
		// For that reason, we have to load and decode the content blob directly,
		// instead of using RevisionRecord::getContent() or SlotRecord::getContent().
		// TODO Once we can rely on the new MCR enabled DB schema, use getContent() directly!

		try {
			$slot = $revision->getSlot( $slotRole );
		} catch ( RevisionAccessException $e ) {
			throw new StorageException( 'Failed to load slot', 0, $e );
		}

		// WARNING: This will make it look like suppressed revisions don't exist at all.
		// Wikibase should handle old revisions with suppressed content gracefully.
		// @see https://phabricator.wikimedia.org/T198467
		if ( !$revision->audienceCan( RevisionRecord::DELETED_TEXT, RevisionRecord::FOR_PUBLIC ) ) {
			return [ null, null ];
		}

		try {
			$blob = $this->blobStore->getBlob( $slot->getAddress() );
		} catch ( BlobAccessException $e ) {
			throw new StorageException( 'Filed to load blob', 0, $e );
		}

		$entity = $this->contentCodec->decodeEntity( $blob, $slot->getFormat() );

		if ( $entity ) {
			$entityRevision = new EntityRevision(
				$entity,
				$revision->getId(),
				$revision->getTimestamp()
			);

			return [ $entityRevision, null ];
		} else {
			$redirect = $this->contentCodec->decodeRedirect( $blob, $slot->getFormat() );

			if ( !$redirect ) {
				throw new StorageException(
					'The serialized data of revision ' . $revision->getId()
					. ' contains neither an Entity nor an EntityRedirect!'
				);
			}

			return [ null, $redirect ];
		}
	}

}
