<?php

namespace Wikibase\Test;

use Wikibase\Change;
use Wikibase\ChangeRow;
use Wikibase\ChangesTable;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Entity\Diff\EntityDiffer;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\SiteLink;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Term\FingerprintProvider;
use Wikibase\DiffChange;
use Wikibase\EntityChange;
use Wikibase\EntityFactory;
use Wikibase\Lib\Changes\EntityChangeFactory;

/**
 * Test change data for ChangeRowTest
 *
 * @since 0.2
 *
 * @group Wikibase
 * @group WikibaseLib
 * @group WikibaseChange
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Daniel Kinzler
 */
final class TestChanges {

	protected static function getItem() {
		$item = new Item();

		$fingerprint = $item->getFingerprint();
		$fingerprint->setLabel( 'en', 'Venezuela' );
		$fingerprint->setDescription( 'en', 'a country' );
		$fingerprint->setAliasGroup( 'en', array( 'Bolivarian Republic of Venezuela' ) );

		$siteLinks = $item->getSiteLinkList();
		$siteLinks->addNewSiteLink( 'enwiki', 'Venezuela' );
		$siteLinks->addNewSiteLink( 'jawiki', 'ベネズエラ' );
		$siteLinks->addNewSiteLink( 'cawiki', 'Veneçuela' );

		return $item;
	}

	public static function getChange() {
		$changes = self::getInstances();

		return $changes['set-de-label']->toArray();
	}

	/**
	 * @return EntityChangeFactory
	 */
	public static function getEntityChangeFactory() {
		// NOTE: always use a local changes table for testing!
		$changesDatabase = false;

		$entityClasses = array(
			Item::ENTITY_TYPE => 'Wikibase\DataModel\Entity\Item',
			Property::ENTITY_TYPE => 'Wikibase\DataModel\Entity\Property',
		);

		$changeClasses = array(
			Item::ENTITY_TYPE => 'Wikibase\ItemChange',
		);

		$factory = new EntityChangeFactory(
			new ChangesTable( $changesDatabase ),
			new EntityFactory( $entityClasses ),
			new EntityDiffer(),
			$changeClasses
		);

		return $factory;
	}

	private static function getInstances() {
		/** @var EntityChange[] $changes */
		static $changes = array();

		$changeFactory = self::getEntityChangeFactory();

		if ( empty( $changes ) ) {
			$empty = Property::newFromType( 'string' );
			$empty->setId( new PropertyId( 'p100' ) );

			$changes['property-creation'] = $changeFactory->newFromUpdate( EntityChange::ADD, null, $empty );
			$changes['property-deletion'] = $changeFactory->newFromUpdate( EntityChange::REMOVE, $empty, null );

			// -----
			$old = Property::newFromType( 'string' );
			$old->setId( new PropertyId( 'p100' ) );
			$new = $old->copy();

			$new->setLabel( "de", "dummy" );
			$changes['property-set-label'] = $changeFactory->newFromUpdate( EntityChange::UPDATE, $old, $new );

			// -----
			$old = new Item( new ItemId( 'Q100' ) );

			/* @var Item $new */
			$new = $old->copy();

			$changes['item-creation'] = $changeFactory->newFromUpdate( EntityChange::ADD, null, $new );
			$changes['item-deletion'] = $changeFactory->newFromUpdate( EntityChange::REMOVE, $old, null );

			// -----

			//FIXME: $changeFactory->newFromUpdate causes Item::getSiteLinks to be called,
			//       which uses SiteLink::newFromText, which in turn uses the Sites singleton
			//       which relies on the database. This is inconsistent with the Site objects
			//       generated here, or elsewhere in test cases.

			$link = new SiteLink( 'dewiki', "Dummy" );
			$new->addSiteLink( $link, 'add' );
			$changes['set-dewiki-sitelink'] = $changeFactory->newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			$link = new SiteLink( 'enwiki', "Emmy" );
			$new->addSiteLink( $link, 'add' );
			$changes['set-enwiki-sitelink'] = $changeFactory->newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			// -----
			$new->getSiteLinkList()->removeLinkWithSiteId( 'enwiki' );
			$new->getSiteLinkList()->removeLinkWithSiteId( 'dewiki' );

			$link = new SiteLink( 'enwiki', "Emmy" );
			$new->addSiteLink( $link, 'add' );

			$link = new SiteLink( 'dewiki', "Dummy" );
			$new->addSiteLink( $link, 'add' );

			$changes['change-sitelink-order'] = $changeFactory->newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			// -----
			$link = new SiteLink( 'dewiki', "Dummy2" );
			$new->addSiteLink( $link, 'set' );
			$changes['change-dewiki-sitelink'] = $changeFactory->newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			$link = new SiteLink( 'enwiki', "Emmy2" );
			$new->addSiteLink( $link, 'set' );
			$changes['change-enwiki-sitelink'] = $changeFactory->newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			$link = new SiteLink( 'enwiki', "Emmy2", array( new ItemId( 'Q17' ) ) );
			$new->addSiteLink( $link, 'set' );
			$changes['change-enwiki-sitelink-badges'] = $changeFactory->newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			$new->getSiteLinkList()->removeLinkWithSiteId( 'dewiki' );
			$changes['remove-dewiki-sitelink'] = $changeFactory->newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			// -----
			$new->setLabel( "de", "dummy" );
			$changes['set-de-label'] = $changeFactory->newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			$new->setLabel( "en", "emmy" );
			$changes['set-en-label'] = $changeFactory->newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			$new->setAliases( "en", array( "foo", "bar" ) );
			$changes['set-en-aliases'] = $changeFactory->newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			// -----
			$propertyId = new PropertyId( 'p23' );
			$snak = new PropertyNoValueSnak( $propertyId );
			$statement = new Statement( $snak );
			$statement->setGuid( 'TEST$test-guid' );

			$statements = new StatementList( array( $statement ) );
			$new->setStatements( $statements );
			$changes['add-claim'] = $changeFactory->newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			$statements = new StatementList();
			$new->setStatements( $statements );
			$changes['remove-claim'] = $changeFactory->newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			// -----
			$changes['item-deletion-linked'] = $changeFactory->newFromUpdate( EntityChange::REMOVE, $old, null );

			// -----
			$new->getSiteLinkList()->removeLinkWithSiteId( 'enwiki' );
			$changes['remove-enwiki-sitelink'] = $changeFactory->newFromUpdate( EntityChange::UPDATE, $old, $new );

			// apply all the defaults ----------
			$defaults = array(
				'user_id' => 0,
				'time' => '20130101000000',
				'type' => 'test',
			);

			$rev = 1000;

			foreach ( $changes as $key => $change ) {
				$change->setComment( "$key:1|" );

				$meta = array(
					'comment' => '',
					'page_id' => 23,
					'bot' => false,
					'rev_id' => $rev,
					'parent_id' => $rev -1,
					'user_text' => 'Some User',
					'time' => wfTimestamp( TS_MW ),
				);

				$change->setMetadata( $meta );
				self::applyDefaults( $change, $defaults );

				$rev += 1;
			}
		}

		$clones = array();

		foreach ( $changes as $key => $change ) {
			$clones[$key] = unserialize( serialize( $change ) );
		}

		return $clones;
	}

	/**
	 * Returns a list of Change objects for testing. Instances are not cloned.
	 *
	 * @param string[]|null $changeFilter The changes to include, as a list if change names
	 *
	 * @param string[]|null $infoFilter The info to include in each change, as
	 *        a list of keys to the info array.
	 *
	 * @return ChangeRow[] a list of changes
	 */
	public static function getChanges( $changeFilter = null, $infoFilter = null ) {
		$changes = self::getInstances();

		// filter changes by key
		if ( $changeFilter!== null ) {
			$changes = array_intersect_key( $changes, array_flip( $changeFilter ) );
		}

		// filter info field by key
		if ( $infoFilter !== null ) {
			$infoFilter = array_flip( $infoFilter );
			$filteredChanges = array();

			/* @var ChangeRow $change */
			foreach ( $changes as $change ) {
				if ( $change->hasField( 'info' ) ) {
					$info = $change->getField( 'info' );

					$info = array_intersect_key( $info, $infoFilter );

					$change->setField( 'info', $info );
				}

				$filteredChanges[] = $change;
			}

			$changes = $filteredChanges;
		}

		return $changes;
	}

	private static function applyDefaults( EntityChange $change, array $defaults ) {
		foreach ( $defaults as $name => $value ) {
			if ( !$change->hasField( $name ) ) {
				$change->setField( $name, $value );
			}
		}
	}

	public static function getDiffs() {
		$changes = self::getChanges();
		$diffs = array();

		foreach ( $changes as $change ) {
			if ( $change instanceof DiffChange
				&& $change->hasDiff() ) {
				$diffs[] = $change->getDiff();
			}
		}

		return $diffs;
	}

	public static function getEntities() {
		$entityList = array();

		/** @var FingerprintProvider[] $entities */
		$entities = array(
			new Item( new ItemId( 'Q112' ) ),
			new Property( new PropertyId( 'P112' ), null, 'string' ),
		);

		foreach ( $entities as $entity ) {
			$entityList[] = $entity;

			$entity->getFingerprint()->setLabel( 'ja', '\u30d3\u30fc\u30eb' );

			$entityList[] = $entity;
		}

		return $entityList;
	}

}
