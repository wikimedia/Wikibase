<?php

namespace Wikibase\Repo\Tests\Store\Sql;

use InvalidArgumentException;
use MediaWiki\MediaWikiServices;
use MediaWikiTestCase;
use RawMessage;
use Revision;
use Status;
use User;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\EntityStoreWatcher;
use Wikibase\Lib\Store\Sql\WikiPageEntityMetaDataLookup;
use Wikibase\Lib\Store\StorageException;
use Wikibase\Lib\Store\Sql\WikiPageEntityRevisionLookup;
use Wikibase\Repo\Content\EntityContentFactory;
use Wikibase\Repo\Content\EntityHandler;
use Wikibase\Repo\Store\WikiPageEntityStore;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\SqlIdGenerator;

/**
 * @covers Wikibase\Repo\Store\WikiPageEntityStore
 *
 * @group Database
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class WikiPageEntityStoreTest extends MediaWikiTestCase {

	/**
	 * @return EntityHandler
	 */
	private function newCustomEntityHandler() {
		$handler = $this->getMockBuilder( EntityHandler::class )
			->disableOriginalConstructor()
			->getMock();

		$handler->expects( $this->any() )
			->method( 'canCreateWithCustomId' )
			->will( $this->returnValue( true ) );

		return $handler;
	}

	/**
	 * @param string $idString
	 *
	 * @return EntityId
	 */
	private function newCustomEntityId( $idString ) {
		$id = $this->getMockBuilder( EntityId::class )
			->setConstructorArgs( [ $idString ] )
			->setMethods( [ 'getEntityType', 'serialize', 'unserialize' ] )
			->getMock();

		$id->expects( $this->any() )
			->method( 'getEntityType' )
			->will( $this->returnValue( 'custom-type' ) );

		return $id;
	}

	/**
	 * @see EntityLookupTest::newEntityLoader()
	 *
	 * @return array [ EntityStore, EntityLookup ]
	 */
	protected function createStoreAndLookup() {
		// make sure the term index is empty to avoid conflicts.
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$wikibaseRepo->getStore()->getTermIndex()->clear();

		//NOTE: we want to test integration of WikiPageEntityRevisionLookup and WikiPageEntityStore here!
		$contentCodec = $wikibaseRepo->getEntityContentDataCodec();

		$lookup = new WikiPageEntityRevisionLookup(
			$contentCodec,
			new WikiPageEntityMetaDataLookup( $wikibaseRepo->getEntityNamespaceLookup(), false ),
			false
		);

		$store = new WikiPageEntityStore(
			new EntityContentFactory(
				[
					'item' => CONTENT_MODEL_WIKIBASE_ITEM,
					'property' => CONTENT_MODEL_WIKIBASE_PROPERTY,
					'custom-type' => 'wikibase-custom-type',
				],
				[
					'item' => function() use ( $wikibaseRepo ) {
						return $wikibaseRepo->newItemHandler();
					},
					'property' => function() use ( $wikibaseRepo ) {
						return $wikibaseRepo->newPropertyHandler();
					},
					'custom-type' => function() use ( $wikibaseRepo ) {
						return $this->newCustomEntityHandler();
					},
				]
			),
			new SqlIdGenerator( MediaWikiServices::getInstance()->getDBLoadBalancer() ),
			$wikibaseRepo->getEntityIdComposer()
		);

		return [ $store, $lookup ];
	}

	public function simpleEntityParameterProvider() {
		$item = new Item();
		$item->setLabel( 'en', 'Item' );
		$item->setDescription( 'en', 'Item description' );

		$property = Property::newFromType( 'string' );
		$property->setLabel( 'en', 'Property' );
		$property->setDescription( 'en', 'Property description' );

		return [
			[ $item, new Item() ],
			[ $property, Property::newFromType( 'string' ) ],
		];
	}

	/**
	 * @dataProvider simpleEntityParameterProvider()
	 */
	public function testSaveEntity( EntityDocument $entity, EntityDocument $empty ) {
		/**
		 * @var WikiPageEntityStore $store
		 * @var EntityRevisionLookup $lookup
		 */
		list( $store, $lookup ) = $this->createStoreAndLookup();
		$user = $GLOBALS['wgUser'];

		// register mock watcher
		$watcher = $this->getMock( EntityStoreWatcher::class );
		$watcher->expects( $this->exactly( 2 ) )
			->method( 'entityUpdated' );
		$watcher->expects( $this->never() )
			->method( 'redirectUpdated' );

		$store->registerWatcher( $watcher );

		// save entity
		$r1 = $store->saveEntity( $entity, 'create one', $user, EDIT_NEW );
		$entityId = $r1->getEntity()->getId();

		$r1actual = $lookup->getEntityRevision( $entityId );
		$this->assertEquals( $r1->getRevisionId(), $r1actual->getRevisionId(), 'revid' );
		$this->assertEquals( $r1->getTimestamp(), $r1actual->getTimestamp(), 'timestamp' );
		$this->assertEquals( $r1->getEntity()->getId(), $r1actual->getEntity()->getId(), 'entity id' );

		// TODO: check notifications in wb_changes table!

		// update entity
		$empty->setId( $entityId );
		$empty->getFingerprint()->setLabel( 'en', 'UPDATED' );

		$r2 = $store->saveEntity( $empty, 'update one', $user, EDIT_UPDATE );
		$this->assertNotEquals( $r1->getRevisionId(), $r2->getRevisionId(), 'expected new revision id' );

		$r2actual = $lookup->getEntityRevision( $entityId );
		$this->assertEquals( $r2->getRevisionId(), $r2actual->getRevisionId(), 'revid' );
		$this->assertEquals( $r2->getTimestamp(), $r2actual->getTimestamp(), 'timestamp' );
		$this->assertEquals( $r2->getEntity()->getId(), $r2actual->getEntity()->getId(), 'entity id' );

		// check that the term index got updated (via a DataUpdate).
		$termIndex = WikibaseRepo::getDefaultInstance()->getStore()->getTermIndex();
		$this->assertNotEmpty( $termIndex->getTermsOfEntity( $entityId ), 'getTermsOfEntity()' );
	}

	public function provideSaveEntityError() {
		$firstItem = new Item();
		$firstItem->setLabel( 'en', 'one' );

		$secondItem = new Item( new ItemId( 'Q768476834' ) );
		$secondItem->setLabel( 'en', 'Bwahahaha' );
		$secondItem->setLabel( 'de', 'Kähähähä' );

		return [
			'not fresh' => [
				'entity' => $firstItem,
				'flags' => EDIT_NEW,
				'baseRevid' => false,
				'error' => StorageException::class
			],

			'not exists' => [
				'entity' => $secondItem,
				'flags' => EDIT_UPDATE,
				'baseRevid' => false,
				'error' => StorageException::class
			],
		];
	}

	/**
	 * @dataProvider provideSaveEntityError
	 */
	public function testSaveEntityError( EntityDocument $entity, $flags, $baseRevId, $error ) {
		/** @var WikiPageEntityStore $store */
		list( $store, ) = $this->createStoreAndLookup();
		$user = $GLOBALS['wgUser'];

		// setup target item
		$one = new Item();
		$one->setLabel( 'en', 'one' );
		$r1 = $store->saveEntity( $one, 'create one', $user, EDIT_NEW );

		// inject ids
		if ( is_int( $baseRevId ) ) {
			// use target item's revision as an offset
			$baseRevId += $r1->getRevisionId();
		}

		if ( $entity->getId() === null ) {
			// use target item's id
			$entity->setId( $r1->getEntity()->getId() );
		}

		// check for error
		$this->setExpectedException( $error );
		$store->saveEntity( $entity, '', $GLOBALS['wgUser'], $flags, $baseRevId );
	}

	public function testSaveRedirect() {
		/** @var WikiPageEntityStore $store */
		list( $store, ) = $this->createStoreAndLookup();
		$user = $GLOBALS['wgUser'];

		// register mock watcher
		$watcher = $this->getMock( EntityStoreWatcher::class );
		$watcher->expects( $this->exactly( 1 ) )
			->method( 'redirectUpdated' );
		$watcher->expects( $this->never() )
			->method( 'entityDeleted' );

		$store->registerWatcher( $watcher );

		// create one
		$one = new Item();
		$one->setLabel( 'en', 'one' );

		$r1 = $store->saveEntity( $one, 'create one', $user, EDIT_NEW );
		$oneId = $r1->getEntity()->getId();

		// redirect one to Q33
		$q33 = new ItemId( 'Q33' );
		$redirect = new EntityRedirect( $oneId, $q33 );

		$redirectRevId = $store->saveRedirect( $redirect, 'redirect one', $user, EDIT_UPDATE );

		// FIXME: use the $lookup to check this, once EntityLookup supports redirects.
		$revision = Revision::newFromId( $redirectRevId );

		$this->assertTrue( $revision->getTitle()->isRedirect(), 'Title::isRedirect' );
		$this->assertTrue( $revision->getContent()->isRedirect(), 'EntityContent::isRedirect()' );
		$this->assertTrue( $revision->getContent()->getEntityRedirect()->equals( $redirect ), 'getEntityRedirect()' );

		$this->assertRedirectPerPage( $q33, $oneId );

		// check that the term index got updated (via a DataUpdate).
		$termIndex = WikibaseRepo::getDefaultInstance()->getStore()->getTermIndex();
		$this->assertEmpty( $termIndex->getTermsOfEntity( $oneId ), 'getTermsOfEntity' );

		// TODO: check notifications in wb_changes table!

		// Revert to original content
		$r1 = $store->saveEntity( $one, 'restore one', $user, EDIT_UPDATE );
		$revision = Revision::newFromId( $r1->getRevisionId() );

		$this->assertFalse( $revision->getTitle()->isRedirect(), 'Title::isRedirect' );
		$this->assertFalse( $revision->getContent()->isRedirect(), 'EntityContent::isRedirect()' );
	}

	private function assertRedirectPerPage( EntityId $expected, EntityId $entityId ) {
		$entityRedirectLookup = WikibaseRepo::getDefaultInstance()->getStore()->getEntityRedirectLookup();

		$targetId = $entityRedirectLookup->getRedirectForEntityId( $entityId );

		$this->assertEquals( $expected, $targetId );
	}

	public function unsupportedRedirectProvider() {
		$p1 = new PropertyId( 'P1' );
		$p2 = new PropertyId( 'P2' );

		return [
			'P1 -> P2' => [ new EntityRedirect( $p1, $p2 ) ],
		];
	}

	/**
	 * @dataProvider unsupportedRedirectProvider
	 */
	public function testSaveRedirectFailure( EntityRedirect $redirect ) {
		/** @var WikiPageEntityStore $store */
		list( $store, ) = $this->createStoreAndLookup();
		$user = $GLOBALS['wgUser'];

		$this->setExpectedException( StorageException::class );
		$store->saveRedirect( $redirect, 'redirect one', $user, EDIT_UPDATE );
	}

	public function testUserWasLastToEdit() {
		/**
		 * @var WikiPageEntityStore $store
		 * @var EntityRevisionLookup $lookup
		 */
		list( $store, $lookup ) = $this->createStoreAndLookup();

		$anonUser = User::newFromId( 0 );
		$anonUser->setName( '127.0.0.1' );
		$user = User::newFromName( "EditEntityTestUser" );
		$item = new Item();

		// check for default values, last revision by anon --------------------
		$item->setLabel( 'en', "Test Anon default" );
		$store->saveEntity( $item, 'testing', $anonUser, EDIT_NEW );
		$itemId = $item->getId();

		$res = $store->userWasLastToEdit( $anonUser, $itemId, false );
		$this->assertFalse( $res );

		// check for default values, last revision by sysop --------------------
		$item->setLabel( 'en', "Test SysOp default" );
		$store->saveEntity( $item, 'Test SysOp default', $user, EDIT_UPDATE );
		$res = $store->userWasLastToEdit( $anonUser, $itemId, false );
		$this->assertFalse( $res );

		// check for default values, last revision by anon --------------------
		$item->setLabel( 'en', "Test Anon with user" );
		$store->saveEntity( $item, 'Test Anon with user', $anonUser, EDIT_UPDATE );
		$res = $store->userWasLastToEdit( $anonUser, $itemId, false );
		$this->assertFalse( $res );

		// check for default values, last revision by sysop --------------------
		$item->setLabel( 'en', "Test SysOp with user" );
		$store->saveEntity( $item, 'Test SysOp with user', $user, EDIT_UPDATE );
		$res = $store->userWasLastToEdit( $user, $itemId, false );
		$this->assertFalse( $res );

		// create an edit and check if the anon user is last to edit --------------------
		$lastRevId = $lookup->getLatestRevisionId( $itemId, EntityRevisionLookup::LATEST_FROM_MASTER );
		$item->setLabel( 'en', "Test Anon" );
		$store->saveEntity( $item, 'Test Anon', $anonUser, EDIT_UPDATE );
		$res = $store->userWasLastToEdit( $anonUser, $itemId, $lastRevId );
		$this->assertTrue( $res );
		// also check that there is a failure if we use the sysop user
		$res = $store->userWasLastToEdit( $user, $itemId, $lastRevId );
		$this->assertFalse( $res );

		// create an edit and check if the sysop user is last to edit --------------------
		$lastRevId = $lookup->getLatestRevisionId( $itemId, EntityRevisionLookup::LATEST_FROM_MASTER );
		$item->setLabel( 'en', "Test SysOp" );
		$store->saveEntity( $item, 'Test SysOp', $user, EDIT_UPDATE );
		$res = $store->userWasLastToEdit( $user, $itemId, $lastRevId );
		$this->assertTrue( $res );

		// also check that there is a failure if we use the anon user
		$res = $store->userWasLastToEdit( $anonUser, $itemId, $lastRevId );
		$this->assertFalse( $res );
	}

	public function testUpdateWatchlist() {
		/** @var WikiPageEntityStore $store */
		list( $store, ) = $this->createStoreAndLookup();

		$user = User::newFromName( "WikiPageEntityStoreTestUser2" );

		if ( $user->getId() === 0 ) {
			$user->addToDatabase();
		}

		$item = new Item();
		$store->saveEntity( $item, 'testing', $user, EDIT_NEW );

		$itemId = $item->getId();

		$store->updateWatchlist( $user, $itemId, true );
		$this->assertTrue( $store->isWatching( $user, $itemId ) );

		$store->updateWatchlist( $user, $itemId, false );
		$this->assertFalse( $store->isWatching( $user, $itemId ) );
	}

	protected function newEntity() {
		$item = new Item();
		return $item;
	}

	/**
	 * Convenience wrapper offering the legacy Status based interface for saving
	 * Entities.
	 *
	 * @todo: rewrite the tests using this
	 *
	 * @param WikiPageEntityStore $store
	 * @param EntityDocument $entity
	 * @param string $summary
	 * @param User|null $user
	 * @param int $flags
	 * @param int|bool $baseRevId
	 *
	 * @return Status
	 */
	protected function saveEntity(
		WikiPageEntityStore $store,
		EntityDocument $entity,
		$summary = '',
		User $user = null,
		$flags = 0,
		$baseRevId = false
	) {
		if ( $user === null ) {
			$user = $GLOBALS['wgUser'];
		}

		try {
			$rev = $store->saveEntity( $entity, $summary, $user, $flags, $baseRevId );
			$status = Status::newGood( Revision::newFromId( $rev->getRevisionId() ) );
		} catch ( StorageException $ex ) {
			$status = $ex->getStatus();

			if ( !$status ) {
				$status = Status::newFatal( new RawMessage( $ex->getMessage() ) );
			}
		}

		return $status;
	}

	private function getStatusLine( Status $status ) {
		if ( $status->isGood() ) {
			return '';
		} elseif ( $status->isOK() ) {
			$warnings = $status->getErrorsByType( 'warning' );
			return "\nStatus (OK): Warnings: " . var_export( $warnings );
		} else {
			return "\n" . $status->getWikiText();
		}
	}

	public function testSaveFlags() {
		/** @var WikiPageEntityStore $store */
		list( $store, ) = $this->createStoreAndLookup();

		$entity = $this->newEntity();
		$prefix = get_class( $this ) . '/';

		// try to create without flags
		$entity->setLabel( 'en', $prefix . 'one' );
		$status = $this->saveEntity( $store, $entity, 'create item' );
		$this->assertFalse( $status->isOK(), "save should have failed" );
		$this->assertTrue(
			$status->hasMessage( 'edit-gone-missing' ),
			'try to create without flags, edit gone missing'
		);

		// try to create with EDIT_UPDATE flag
		$entity->setLabel( 'en', $prefix . 'two' );
		$status = $this->saveEntity( $store, $entity, 'create item', null, EDIT_UPDATE );
		$this->assertFalse( $status->isOK(), "save should have failed" );
		$this->assertTrue(
			$status->hasMessage( 'edit-gone-missing' ),
			'edit gone missing, try to create with EDIT_UPDATE'
		);

		// try to create with EDIT_NEW flag
		$entity->setLabel( 'en', $prefix . 'three' );
		$status = $this->saveEntity( $store, $entity, 'create item', null, EDIT_NEW );
		$this->assertTrue(
			$status->isOK(),
			'create with EDIT_NEW flag for ' . $entity->getId() .
			$this->getStatusLine( $status )
		);
		$this->assertNotNull( $entity->getId(), 'getEntityId() after save' );

		// ok, the item exists now in the database.

		// try to save with EDIT_NEW flag
		$entity->setLabel( 'en', $prefix . 'four' );
		$status = $this->saveEntity( $store, $entity, 'create item', null, EDIT_NEW );
		$this->assertFalse( $status->isOK(), "save should have failed" );
		$this->assertTrue(
			$status->hasMessage( 'edit-already-exists' ),
			'try to save with EDIT_NEW flag, edit already exists'
		);

		// try to save with EDIT_UPDATE flag
		$entity->setLabel( 'en', $prefix . 'five' );
		$status = $this->saveEntity( $store, $entity, 'create item', null, EDIT_UPDATE );
		$this->assertTrue(
			$status->isOK(),
			'try to save with EDIT_UPDATE flag, save failed' . $this->getStatusLine( $status )
		);

		// try to save without flags
		$entity->setLabel( 'en', $prefix . 'six' );
		$status = $this->saveEntity( $store, $entity, 'create item' );
		$this->assertTrue(
			$status->isOK(),
			'try to save without flags, save failed' . $this->getStatusLine( $status )
		);
	}

	public function testRepeatedSave() {
		/** @var WikiPageEntityStore $store */
		list( $store, ) = $this->createStoreAndLookup();

		$entity = $this->newEntity();
		$prefix = get_class( $this ) . '/';

		// create
		$entity->setLabel( 'en', $prefix . "First" );
		$status = $this->saveEntity( $store, $entity, 'create item', null, EDIT_NEW );
		$this->assertTrue(
			$status->isOK(),
			'create, save failed, status ok' . $this->getStatusLine( $status )
		);
		$this->assertTrue( $status->isGood(), 'create, status is good' . $this->getStatusLine( $status ) );

		// change
		$prev_id = $store->getWikiPageForEntity( $entity->getId() )->getLatest();
		$entity->setLabel( 'en', $prefix . "Second" );
		$status = $this->saveEntity( $store, $entity, 'modify item', null, EDIT_UPDATE );
		$this->assertTrue( $status->isOK(), 'change, status ok' . $this->getStatusLine( $status ) );
		$this->assertTrue( $status->isGood(), 'change, status good' . $this->getStatusLine( $status ) );

		$rev_id = $store->getWikiPageForEntity( $entity->getId() )->getLatest();
		$this->assertNotEquals( $prev_id, $rev_id, "revision ID should change on edit" );

		// change again
		$prev_id = $store->getWikiPageForEntity( $entity->getId() )->getLatest();
		$entity->setLabel( 'en', $prefix . "Third" );
		$status = $this->saveEntity( $store, $entity, 'modify item again', null, EDIT_UPDATE );
		$this->assertTrue( $status->isOK(), 'change again, status ok' . $this->getStatusLine( $status ) );
		$this->assertTrue( $status->isGood(), 'change again, status good' );

		$rev_id = $store->getWikiPageForEntity( $entity->getId() )->getLatest();
		$this->assertNotEquals( $prev_id, $rev_id, "revision ID should change on edit" );

		// save unchanged
		$prev_id = $store->getWikiPageForEntity( $entity->getId() )->getLatest();
		$status = $this->saveEntity( $store, $entity, 'save unmodified', null, EDIT_UPDATE );
		$this->assertTrue(
			$status->isOK(),
			'save unchanged, save failed, status ok'
			. $this->getStatusLine( $status )
		);

		$rev_id = $store->getWikiPageForEntity( $entity->getId() )->getLatest();
		$this->assertEquals( $prev_id, $rev_id, "revision ID should stay the same if no change was made" );
	}

	/**
	 * @dataProvider simpleEntityParameterProvider
	 */
	public function testDeleteEntity( EntityDocument $entity ) {
		/**
		 * @var WikiPageEntityStore $store
		 * @var EntityRevisionLookup $lookup
		 */
		list( $store, $lookup ) = $this->createStoreAndLookup();
		$user = $GLOBALS['wgUser'];

		// register mock watcher
		$watcher = $this->getMock( EntityStoreWatcher::class );
		$watcher->expects( $this->exactly( 1 ) )
			->method( 'entityDeleted' );

		$store->registerWatcher( $watcher );

		// save entity
		$r1 = $store->saveEntity( $entity, 'create one', $user, EDIT_NEW );
		$entityId = $r1->getEntity()->getId();

		// sanity check
		$this->assertNotNull( $lookup->getEntityRevision( $entityId ) );

		// delete entity
		$store->deleteEntity( $entityId, 'testing', $user );

		// check that it's gone
		$this->assertFalse(
			$lookup->getLatestRevisionId( $entityId, EntityRevisionLookup::LATEST_FROM_MASTER ),
			'getLatestRevisionId'
		);
		$this->assertNull( $lookup->getEntityRevision( $entityId ), 'getEntityRevision' );

		// check that the term index got updated (via a DataUpdate).
		$termIndex = WikibaseRepo::getDefaultInstance()->getStore()->getTermIndex();
		$this->assertEmpty( $termIndex->getTermsOfEntity( $entityId ), 'getTermsOfEntity' );

		// TODO: check notifications in wb_changes table!
	}

	public function provideCanCreateWithCustomId() {
		return [
			'no custom id allowed' => [ new ItemId( 'Q7' ), false ],
			'custom id allowed' => [ $this->newCustomEntityId( 'F7' ), true ],
			'no foreign id allowed' => [ $this->newCustomEntityId( 'foo:F7' ), false ],
		];
	}

	/**
	 * @dataProvider provideCanCreateWithCustomId
	 * @covers \Wikibase\Repo\Store\WikiPageEntityStore::canCreateWithCustomId
	 */
	public function testCanCreateWithCustomId( EntityId $id, $expected ) {
		/** @var WikiPageEntityStore $store */
		list( $store, ) = $this->createStoreAndLookup();

		$this->assertSame( $expected, $store->canCreateWithCustomId( $id ), $id->getSerialization() );
	}

	public function testGetWikiPageForEntityFails_GivenForeignEntityId() {
		/** @var WikiPageEntityStore $store */
		list( $store, ) = $this->createStoreAndLookup();
		$this->setExpectedException( InvalidArgumentException::class );

		$store->getWikiPageForEntity( new ItemId( 'foo:Q42' ) );
	}

	public function testSaveEntityFails_GivenForeignEntityId() {
		/** @var WikiPageEntityStore $store */
		list( $store, ) = $this->createStoreAndLookup();
		$this->setExpectedException( InvalidArgumentException::class );

		$store->saveEntity( new Item( new ItemId( 'foo:Q123' ) ), 'testing', $GLOBALS['wgUser'], EDIT_NEW );
	}

	public function testDeleteEntityFails_GivenForeignEntityId() {
		/** @var WikiPageEntityStore $store */
		list( $store, ) = $this->createStoreAndLookup();
		$this->setExpectedException( InvalidArgumentException::class );

		$store->deleteEntity( new ItemId( 'foo:Q123' ), 'testing', $GLOBALS['wgUser'] );
	}

	public function testUserWasLastToEditFails_GivenForeignEntityId() {
		/** @var WikiPageEntityStore $store */
		list( $store, ) = $this->createStoreAndLookup();
		$this->setExpectedException( InvalidArgumentException::class );

		$store->userWasLastToEdit( $GLOBALS['wgUser'], new ItemId( 'foo:Q123' ), false );
	}

	/**
	 * @dataProvider foreignRedirectServiceProvider
	 */
	public function testSaveRedirectFails_GivenForeignEntityId( EntityId $source, EntityId $target ) {
		/** @var WikiPageEntityStore $store */
		list( $store, ) = $this->createStoreAndLookup();
		$this->setExpectedException( InvalidArgumentException::class );

		$store->saveRedirect(
			new EntityRedirect( $source, $target ),
			'testing',
			$GLOBALS['wgUser']
		);
	}

	public function foreignRedirectServiceProvider() {
		return [
			[ new ItemId( 'foo:Q123' ), new ItemId( 'Q42' ) ],
			[ new ItemId( 'Q42' ), new ItemId( 'foo:Q123' ) ],
		];
	}

	public function testUpdateWatchListFails_GivenForeignEntityId() {
		/** @var WikiPageEntityStore $store */
		list( $store, ) = $this->createStoreAndLookup();
		$this->setExpectedException( InvalidArgumentException::class );

		$store->updateWatchlist( $GLOBALS['wgUser'], new ItemId( 'foo:Q123' ), false );
	}

	public function testIsWatchingFails_GivenForeignEntityId() {
		/** @var WikiPageEntityStore $store */
		list( $store, ) = $this->createStoreAndLookup();
		$this->setExpectedException( InvalidArgumentException::class );

		$store->isWatching( $GLOBALS['wgUser'], new ItemId( 'foo:Q123' ) );
	}

}
