<?php

namespace Wikibase;

use Deserializers\Deserializer;
use Diff\DiffOp\DiffOp;
use Diff\DiffOpFactory;
use MWException;
use RecentChange;
use Revision;
use RuntimeException;
use Serializers\Serializer;
use User;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Services\Diff\EntityTypeAwareDiffOpFactory;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\WikibaseRepo;

/**
 * Represents a change for an entity; to be extended by various change subtypes
 *
 * @license GPL-2.0+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Daniel Kinzler
 */
class EntityChange extends DiffChange {

	const UPDATE = 'update';
	const ADD = 'add';
	const REMOVE = 'remove';
	const RESTORE = 'restore';

	/**
	 * @var EntityId|null
	 */
	private $entityId = null;

	/**
	 * @return string
	 */
	public function getType() {
		return $this->getField( 'type' );
	}

	/**
	 * @return EntityId
	 */
	public function getEntityId() {
		if ( !$this->entityId && $this->hasField( 'object_id' ) ) {
			// FIXME: this should not happen
			wfWarn( "object_id set in EntityChange, but not entityId" );
			$idParser = new BasicEntityIdParser();
			$this->entityId = $idParser->parse( $this->getObjectId() );
		}

		return $this->entityId;
	}

	/**
	 * Set the Change's entity id (as returned by getEntityId) and the object_id field
	 * @param EntityId $entityId
	 */
	public function setEntityId( EntityId $entityId ) {
		$this->entityId = $entityId;
		$this->setField( 'object_id', $entityId->getSerialization() );
	}

	/**
	 * @return string
	 */
	public function getAction() {
		list( , $action ) = explode( '~', $this->getType(), 2 );

		return $action;
	}

	/**
	 * @param string $cache set to 'cache' to cache the unserialized diff.
	 *
	 * @return array false if no meta data could be found in the info array
	 */
	public function getMetadata( $cache = 'no' ) {
		$info = $this->getInfo( $cache );

		if ( array_key_exists( 'metadata', $info ) ) {
			return $info['metadata'];
		}

		return array();
	}

	/**
	 * Sets metadata fields. Unknown fields are ignored. New metadata is merged into
	 * the current metadata array.
	 *
	 * @param array $metadata
	 */
	public function setMetadata( array $metadata ) {
		$validKeys = array(
			'page_id',
			'bot',
			'rev_id',
			'parent_id',
			'user_text',
			'comment'
		);

		// strip extra fields from metadata
		$metadata = array_intersect_key( $metadata, array_flip( $validKeys ) );

		// merge new metadata into current metadata
		$metadata = array_merge( $this->getMetadata(), $metadata );

		// make sure the comment field is set
		if ( !isset( $metadata['comment'] ) ) {
			$metadata['comment'] = $this->getComment();
		}

		$info = $this->getInfo();
		$info['metadata'] = $metadata;
		$this->setField( 'info', $info );
	}

	/**
	 * @return string
	 */
	public function getComment() {
		$metadata = $this->getMetadata();

		// TODO: get rid of this awkward fallback and messages. Comments and messages
		// should come from the revision, not be invented here.
		if ( !isset( $metadata['comment'] ) ) {
			// Messages: wikibase-comment-add, wikibase-comment-remove, wikibase-comment-linked,
			// wikibase-comment-unlink, wikibase-comment-restore, wikibase-comment-update
			$metadata['comment'] = 'wikibase-comment-' . $this->getAction();
		}

		return $metadata['comment'];
	}

	/**
	 * @param RecentChange $rc
	 *
	 * @todo rename to setRecentChangeInfo
	 */
	public function setMetadataFromRC( RecentChange $rc ) {
		$this->setFields( array(
			'revision_id' => $rc->getAttribute( 'rc_this_oldid' ),
			'user_id' => $rc->getAttribute( 'rc_user' ),
			'time' => $rc->getAttribute( 'rc_timestamp' ),
		) );

		$this->setMetadata( array(
			'user_text' => $rc->getAttribute( 'rc_user_text' ),
			'bot' => $rc->getAttribute( 'rc_bot' ),
			'page_id' => $rc->getAttribute( 'rc_cur_id' ),
			'rev_id' => $rc->getAttribute( 'rc_this_oldid' ),
			'parent_id' => $rc->getAttribute( 'rc_last_oldid' ),
			'comment' => $rc->getAttribute( 'rc_comment' ),
		) );
	}

	/**
	 * @param User $user
	 *
	 * @todo rename to setUserInfo
	 */
	public function setMetadataFromUser( User $user ) {
		$this->setFields( array(
			'user_id' => $user->getId(),
		) );

		// TODO: init page_id etc in getMetadata, not here!
		$metadata = array_merge( array(
				'page_id' => 0,
				'rev_id' => 0,
				'parent_id' => 0,
			),
			$this->getMetadata()
		);

		$metadata['user_text'] = $user->getName();

		$this->setMetadata( $metadata );
	}

	/**
	 * @param Revision $revision
	 */
	public function setRevisionInfo( Revision $revision ) {
		$this->setFields( array(
			'revision_id' => $revision->getId(),
			'user_id' => $revision->getUser(),
			'time' => $revision->getTimestamp(),
		) );

		if ( !$this->hasField( 'object_id' ) ) {
			/* @var EntityContent $content */
			$content = $revision->getContent(); // potentially expensive!
			$entityId = $content->getEntityId();

			$this->setFields( array(
				'object_id' => $entityId->getSerialization(),
			) );
		}

		$this->setMetadata( array(
			'user_text' => $revision->getUserText(),
			'page_id' => $revision->getPage(),
			'parent_id' => $revision->getParentId(),
			'comment' => $revision->getComment(),
			'rev_id' => $revision->getId(),
		) );
	}

	/**
	 * @param string $timestamp Timestamp in TS_MW format
	 */
	public function setTimestamp( $timestamp ) {
		$this->setField( 'time', $timestamp );
	}

	/**
	 * @see ChangeRow::getSerializedInfo
	 *
	 * @return string JSON
	 */
	public function getSerializedInfo() {
		$info = $this->getInfo();

		if ( isset( $info['diff'] ) ) {
			$diff = $info['diff'];

			if ( $diff instanceof DiffOp ) {
				$info['diff'] = $diff->toArray( function ( $data ) {
					if ( !( $data instanceof Statement ) ) {
						return $data;
					}

					$array = $this->getStatementSerializer()->serialize( $data );
					$array['_claimclass_'] = get_class( $data );

					return $array;
				} );
			}
		}

		// Make sure we never serialize objects.
		// This is a lot of overhead, so we only do it during testing.
		if ( defined( 'MW_PHPUNIT_TEST' ) ) {
			array_walk_recursive(
				$info,
				function ( $v ) {
					if ( is_object( $v ) ) {
						throw new MWException( "Refusing to serialize PHP object of type "
							. get_class( $v ) );
					}
				}
			);
		}

		//XXX: we could JSON_UNESCAPED_UNICODE here, perhaps.
		return json_encode( $info );
	}

	/**
	 * @throws RuntimeException
	 * @return Serializer
	 */
	private function getStatementSerializer() {
		// FIXME: the change row system needs to be reworked to either allow for sane injection
		// or to avoid this kind of configuration dependent tasks.
		if ( defined( 'WB_VERSION' ) ) {
			return WikibaseRepo::getDefaultInstance()->getStatementSerializer();
		} elseif ( defined( 'WBC_VERSION' ) ) {
			throw new RuntimeException( 'Cannot serialize statements on the client' );
		} else {
			throw new RuntimeException( 'Need either client or repo loaded' );
		}
	}

	/**
	 * @throws RuntimeException
	 * @return Deserializer
	 */
	private function getStatementDeserializer() {
		// FIXME: the change row system needs to be reworked to either allow for sane injection
		// or to avoid this kind of configuration dependent tasks.
		if ( defined( 'WB_VERSION' ) ) {
			return WikibaseRepo::getDefaultInstance()->getInternalFormatStatementDeserializer();
		} elseif ( defined( 'WBC_VERSION' ) ) {
			return WikibaseClient::getDefaultInstance()->getInternalFormatStatementDeserializer();
		} else {
			throw new RuntimeException( 'Need either client or repo loaded' );
		}
	}

	/**
	 * @see ChangeRow::unserializeInfo
	 *
	 * Overwritten to use the array representation of the diff.
	 *
	 * @param string $serialization
	 * @return array the info array
	 */
	protected function unserializeInfo( $serialization ) {
		static $factory = null;

		$info = parent::unserializeInfo( $serialization );

		if ( isset( $info['diff'] ) && is_array( $info['diff'] ) ) {
			if ( $factory === null ) {
				$factory = $this->newDiffOpFactory();
			}

			$info['diff'] = $factory->newFromArray( $info['diff'] );
		}

		return $info;
	}

	/**
	 * @return DiffOpFactory
	 */
	private function newDiffOpFactory() {
		return new EntityTypeAwareDiffOpFactory( function ( array $data ) {
			if ( is_array( $data ) && isset( $data['_claimclass_'] ) ) {
				$class = $data['_claimclass_'];

				if ( $class === Statement::class
					|| is_subclass_of( $class, Statement::class )
				) {
					unset( $data['_claimclass_'] );
					return $this->getStatementDeserializer()->deserialize( $data );
				}
			}

			return $data;
		} );
	}

}
