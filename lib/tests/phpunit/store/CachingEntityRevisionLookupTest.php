<?php

namespace Wikibase\Test;

use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\EntityRevision;
use Wikibase\EntityLookup;
use Wikibase\store\CachingEntityRevisionLookup;

/**
 * @covers Wikibase\store\CachingEntityRevisionLookup
 *
 * @group WikibaseLib
 * @group WikibaseEntityLookup
 * @group Wikibase
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class CachingEntityRevisionLookupTest extends EntityRevisionLookupTest {

	/**
	 * @see EntityLookupTest::newEntityLoader(newEntityLookup
	 *
	 * @param EntityRevision[] $entityRevisions
	 *
	 * @return EntityLookup
	 */
	protected function newEntityRevisionLookup( array $entityRevisions ) {
		$mock = new MockRepository();

		foreach ( $entityRevisions as $rev => $entityRev ) {
			$mock->putEntity( $entityRev->getEntity(), $entityRev->getRevision() );
		}

		return new CachingEntityRevisionLookup( $mock, new \HashBagOStuff() );
	}

	public function testWithRevisionVerification() {
		$mock = new MockRepository();

		$id = new ItemId( 'Q123' );
		$item = Item::newEmpty();
		$item->setId( $id );

		$mock->putEntity( $item, 11 );

		$lookup = new CachingEntityRevisionLookup( $mock, new \HashBagOStuff() );
		$lookup->setVerifyRevision( true );

		// fetch first revision, so it gets cached
		$lookup->getEntityRevision( $id );

		// create new revision
		$mock->putEntity( $item, 12 );

		// make sure we get the new revision automatically
		$revId = $lookup->getLatestRevisionId( $id );
		$this->assertEquals( 12, $revId, 'new revision should be detected if verification is enabled' );

		$rev = $lookup->getEntityRevision( $id );
		$this->assertEquals( 12, $rev->getRevision(), 'new revision should be detected if verification is enabled' );

		// remove the item
		$mock->removeEntity( $id );

		// try to fetch it again
		$revId = $lookup->getLatestRevisionId( $id );
		$this->assertFalse( $revId, 'deletion should be detected if verification is enabled' );

		$rev = $lookup->getEntityRevision( $id );
		$this->assertNull( $rev, 'deletion should be detected if verification is enabled' );
	}

	public function testWithoutRevisionVerification() {
		$mock = new MockRepository();

		$id = new ItemId( 'Q123' );
		$item = Item::newEmpty();
		$item->setId( $id );

		$mock->putEntity( $item, 11 );

		$lookup = new CachingEntityRevisionLookup( $mock, new \HashBagOStuff() );
		$lookup->setVerifyRevision( false );

		// fetch first revision, so it gets cached
		$lookup->getEntityRevision( $id );

		// create new revision
		$mock->putEntity( $item, 12 );

		// check that we are still getting the old revision
		$revId = $lookup->getLatestRevisionId( $id );
		$this->assertEquals( 11, $revId, 'new revision should be ignored if verification is disabled' );

		$rev = $lookup->getEntityRevision( $id );
		$this->assertEquals( 11, $rev->getRevision(), 'new revision should be ignored if verification is disabled' );

		// remove the item
		$mock->removeEntity( $id );

		// try to fetch it again - should still be cached
		$revId = $lookup->getLatestRevisionId( $id );
		$this->assertEquals( 11, $revId, 'deletion should be ignored if verification is disabled' );

		$rev = $lookup->getEntityRevision( $id );
		$this->assertEquals( 11, $rev->getRevision(), 'deletion should be ignored if verification is disabled' );
	}

	public function testEntityUpdated() {
		$mock = new MockRepository();

		$id = new ItemId( 'Q123' );
		$item = Item::newEmpty();
		$item->setId( $id );

		$mock->putEntity( $item, 11 );

		$lookup = new CachingEntityRevisionLookup( $mock, new \HashBagOStuff() );
		$lookup->setVerifyRevision( false );

		// fetch first revision, so it gets cached
		$lookup->getEntityRevision( $id );

		// create new revision
		$rev12 = $mock->putEntity( $item, 12 );

		// now, notify the cache
		$lookup->entityUpdated( $rev12 );

		// make sure we get the new revision now
		$revId = $lookup->getLatestRevisionId( $id );
		$this->assertEquals( 12, $revId, 'new revision should be detected after notification' );

		$rev = $lookup->getEntityRevision( $id );
		$this->assertEquals( 12, $rev->getRevision(), 'new revision should be detected after notification' );
	}

	public function testEntityDeleted() {
		$mock = new MockRepository();

		$id = new ItemId( 'Q123' );
		$item = Item::newEmpty();
		$item->setId( $id );

		$mock->putEntity( $item, 11 );

		$lookup = new CachingEntityRevisionLookup( $mock, new \HashBagOStuff() );
		$lookup->setVerifyRevision( false );

		// fetch first revision, so it gets cached
		$lookup->getEntityRevision( $id );

		// remove entity
		$mock->removeEntity( $id );

		// now, notify the cache
		$lookup->entityDeleted( $id );

		// make sure we get the new revision now
		$revId = $lookup->getLatestRevisionId( $id );
		$this->assertFalse( $revId, 'deletion should be detected after notification' );

		$rev = $lookup->getEntityRevision( $id );
		$this->assertNull( $rev, 'deletion should be detected after notification' );
	}
}
