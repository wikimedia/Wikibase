<?php

namespace Wikibase\Repo\Tests\Store;

use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\BlobStore;
use MediaWiki\Storage\MutableRevisionRecord;
use MediaWiki\Storage\RevisionStore;
use MediaWiki\Storage\SlotRecord;
use Title;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\Lib\Store\EntityRevision;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Lib\Store\Sql\WikiPageEntityMetaDataLookup;
use Wikibase\Lib\Store\Sql\WikiPageEntityRevisionLookup;
use Wikibase\Lib\Tests\EntityRevisionLookupTestCase;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers \Wikibase\Lib\Store\Sql\WikiPageEntityRevisionLookup
 *
 * @group Database
 * @group Wikibase
 * @group WikibaseEntityLookup
 * @group medium
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class WikiPageEntityRevisionLookupTest extends EntityRevisionLookupTestCase {

	/**
	 * @var EntityRevision[]
	 */
	private static $testEntities = [];

	public function setUp() {
		parent::setUp();

		$this->tablesUsed[] = 'page';
	}

	protected static function storeTestEntity( EntityDocument $entity ) {
		global $wgUser;

		$store = WikibaseRepo::getDefaultInstance()->getEntityStore();
		$revision = $store->saveEntity( $entity, "storeTestEntity", $wgUser );

		return $revision;
	}

	protected static function storeTestRedirect( EntityRedirect $redirect ) {
		global $wgUser;

		$store = WikibaseRepo::getDefaultInstance()->getEntityStore();
		$revision = $store->saveRedirect( $redirect, "storeTestEntity", $wgUser );

		return $revision;
	}

	/**
	 * @see EntityRevisionLookupTestCase::newEntityRevisionLookup
	 *
	 * @param EntityRevision[] $entityRevisions
	 * @param EntityRedirect[] $entityRedirects
	 *
	 * @return EntityLookup
	 */
	protected function newEntityRevisionLookup( array $entityRevisions, array $entityRedirects ) {
		// make sure all test entities are in the database.

		foreach ( $entityRevisions as $entityRev ) {
			$logicalRev = $entityRev->getRevisionId();

			if ( !isset( self::$testEntities[$logicalRev] ) ) {
				$rev = self::storeTestEntity( $entityRev->getEntity() );
				self::$testEntities[$logicalRev] = $rev;
			}
		}

		foreach ( $entityRedirects as $entityRedir ) {
			self::storeTestRedirect( $entityRedir );
		}

		return new WikiPageEntityRevisionLookup(
			WikibaseRepo::getDefaultInstance()->getEntityContentDataCodec(),
			new WikiPageEntityMetaDataLookup( $this->getEntityNamespaceLookup() ),
			MediaWikiServices::getInstance()->getRevisionStore(),
			MediaWikiServices::getInstance()->getBlobStore(),
			false
		);
	}

	/**
	 * @return EntityNamespaceLookup
	 */
	private function getEntityNamespaceLookup() {
		$entityNamespaceLookup = WikibaseRepo::getDefaultInstance()->getEntityNamespaceLookup();

		return $entityNamespaceLookup;
	}

	protected function resolveLogicalRevision( $revision ) {
		if ( is_int( $revision ) && isset( self::$testEntities[$revision] ) ) {
			$revision = self::$testEntities[$revision]->getRevisionId();
		}

		return $revision;
	}

	public function testGetEntityRevision_byRevisionIdWithMode() {
		// Needed to fill the database.
		$this->newEntityRevisionLookup( $this->getTestRevisions(), [] );

		$testEntityRevision = reset( self::$testEntities );
		$entityId = $testEntityRevision->getEntity()->getId();
		$revisionId = $testEntityRevision->getRevisionId();

		$realMetaDataLookup = new WikiPageEntityMetaDataLookup( $this->getEntityNamespaceLookup() );
		$metaDataLookup = $this->getMockBuilder( WikiPageEntityMetaDataLookup::class )
			->disableOriginalConstructor()
			->getMock();

		$metaDataLookup->expects( $this->once() )
			->method( 'loadRevisionInformationByRevisionId' )
			->with( $entityId, $revisionId, 'load-mode' )
			->will( $this->returnValue(
				$realMetaDataLookup->loadRevisionInformationByRevisionId( $entityId, $revisionId )
			) );

		$lookup = new WikiPageEntityRevisionLookup(
			WikibaseRepo::getDefaultInstance()->getEntityContentDataCodec(),
			$metaDataLookup,
			MediaWikiServices::getInstance()->getRevisionStore(),
			MediaWikiServices::getInstance()->getBlobStore(),
			false
		);

		$entityRevision = $lookup->getEntityRevision( $entityId, $revisionId, 'load-mode' );

		$this->assertSame( $revisionId, $entityRevision->getRevisionId() );
	}

	public function testGetEntityRevision_fromAlternativeSlot() {
		$entity = new Item( new ItemId( 'Q765' ) );
		$entityId = $entity->getId();
		$revisionId = 117;

		$slot = new SlotRecord( (object)[
			'slot_revision_id' => $revisionId,
			'slot_content_id' => 1234567,
			'slot_origin' => 77,
			'content_address' => 'xx:blob',
			'content_format' => CONTENT_FORMAT_JSON,

			// Currently, the model must be ignored. That may change in the future!
			'model_name' => 'WRONG',
			'role_name' => 'kittens',
		], function() {
			// This doesn#t work cross-wiki yet, so make sure we don't try.
			$this->fail( 'Content should not be constructed by the RevisionStore' );
		} );

		$revision = new MutableRevisionRecord( Title::newFromText( $entityId->getSerialization() ) );
		$revision->setId( $revisionId );
		$revision->setTimestamp( wfTimestampNow() );
		$revision->setSlot( $slot );

		$metaDataLookup = $this->getMockBuilder( WikiPageEntityMetaDataLookup::class )
			->disableOriginalConstructor()
			->getMock();

		$metaDataLookup->expects( $this->once() )
			->method( 'loadRevisionInformationByRevisionId' )
			->with( $entityId, $revisionId )
			->will( $this->returnValue(
				(object)[ 'rev_id' => $revisionId, 'role_name' => 'kittens' ]
			) );

		$revisionStore = $this->getMockBuilder( RevisionStore::class )
			->disableOriginalConstructor()
			->getMock();

		$revisionStore->expects( $this->once() )
			->method( 'getRevisionById' )
			->with( $revisionId )
			->will( $this->returnValue(
				$revision
			) );

		$codec = WikibaseRepo::getDefaultInstance()->getEntityContentDataCodec();

		$blobStore = $this->getMock( BlobStore::class );
		$blobStore->expects( $this->once() )
			->method( 'getBlob' )
			->with( 'xx:blob' )
			->will( $this->returnValue(
				$codec->encodeEntity( $entity, CONTENT_FORMAT_JSON )
			) );

		$lookup = new WikiPageEntityRevisionLookup(
			$codec,
			$metaDataLookup,
			$revisionStore,
			$blobStore,
			false
		);

		$entityRevision = $lookup->getEntityRevision( $entityId, $revisionId );

		$this->assertSame( $revisionId, $entityRevision->getRevisionId() );
	}

	public function testGetLatestRevisionId_Redirect_ReturnsRedirectResultWithCorrectData() {
		$entityId = new ItemId( 'Q1' );
		$redirectsTo = new ItemId( 'Q2' );
		$entityRedirect = new EntityRedirect( $entityId, $redirectsTo );

		$redirectRevisionId = self::storeTestRedirect( $entityRedirect );

		$lookup = new WikiPageEntityRevisionLookup(
			WikibaseRepo::getDefaultInstance()->getEntityContentDataCodec(),
			new WikiPageEntityMetaDataLookup( $this->getEntityNamespaceLookup() ),
			MediaWikiServices::getInstance()->getRevisionStore(),
			MediaWikiServices::getInstance()->getBlobStore(),
			false
		);

		$shouldFail = function () {
			$this->fail( 'Expecting redirect revision result' );
		};

		$latestRevisionIdResult = $lookup->getLatestRevisionId( $entityId );
		$gotRevisionId = $latestRevisionIdResult->onConcreteRevision( $shouldFail )
			->onNonexistentEntity( $shouldFail )
			->onRedirect(
				function ( $revisionId, $gotRedirectsTo ) use ( $redirectsTo ) {
					$this->assertEquals( $redirectsTo, $gotRedirectsTo );
					return $revisionId;
				}
			)
			->map();

		$this->assertEquals( $redirectRevisionId, $gotRevisionId );
	}

}
