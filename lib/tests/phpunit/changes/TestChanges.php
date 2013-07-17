<?php

namespace Wikibase\Test;
use Wikibase\DataModel\SimpleSiteLink;
use \Wikibase\Item;
use \Wikibase\Property;
use \Wikibase\EntityChange;
use \Wikibase\DiffChange;
use \Wikibase\EntityId;

/**
 * Test change data for ChangeRowTest
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @since 0.2
 *
 * @ingroup WikibaseLib
 * @ingroup Test
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
		$item = Item::newEmpty();
		$item->setLabel( 'en', 'Venezuela' );
		$item->setDescription( 'en', 'a country' );
		$item->addAliases( 'en', array( 'Bolivarian Republic of Venezuela' ) );

		$item->addSimpleSiteLink( new SimpleSiteLink( 'enwiki', 'Venezuela' )  );
		$item->addSimpleSiteLink( new SimpleSiteLink( 'jawiki', 'ベネズエラ' )  );
		$item->addSimpleSiteLink( new SimpleSiteLink( 'cawiki', 'Veneçuela' )  );

		return $item;
	}

	public static function getChange() {
		$changes = self::getInstances();

		return $changes['set-de-label']->toArray();
	}

	protected static function getInstances() {
		static $changes = array();

		if ( empty( $changes ) ) {
			$empty = Property::newEmpty();
			$empty->setId( new \Wikibase\EntityId( Property::ENTITY_TYPE, 100 ) );

			$changes['property-creation'] = EntityChange::newFromUpdate( EntityChange::ADD, null, $empty );
			$changes['property-deletion'] = EntityChange::newFromUpdate( EntityChange::REMOVE, $empty, null );

			// -----
			$old = Property::newEmpty();
			$old->setId( new \Wikibase\EntityId( Property::ENTITY_TYPE, 100 ) );
			$new = $old->copy();

			$new->setLabel( "de", "dummy" );
			$changes['property-set-label'] = EntityChange::newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			// -----
			$old = Item::newEmpty();
			$old->setId( new \Wikibase\EntityId( Item::ENTITY_TYPE, 100 ) );

			/* @var Item $new */
			$new = $old->copy();

			$changes['item-creation'] = EntityChange::newFromUpdate( EntityChange::ADD, null, $new );
			$changes['item-deletion'] = EntityChange::newFromUpdate( EntityChange::REMOVE, $old, null );

			// -----

			//FIXME: EntityChange::newFromUpdate causes Item::getSiteLinks to be called,
			//       which uses SiteLink::newFromText, which in turn uses the Sites singleton
			//       which relies on the database. This is inconsistent with the Site objects
			//       generated here, or elsewhere in test cases.

			$link = new SimpleSiteLink( 'dewiki', "Dummy" );
			$new->addSimpleSiteLink( $link, 'add' );
			$changes['set-dewiki-sitelink'] = EntityChange::newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			$link = new SimpleSiteLink( 'enwiki', "Emmy" );
			$new->addSimpleSiteLink( $link, 'add' );
			$changes['set-enwiki-sitelink'] = EntityChange::newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			// -----
			$new->removeSiteLink( 'enwiki' );
			$new->removeSiteLink( 'dewiki' );

			$link = new SimpleSiteLink( 'enwiki', "Emmy" );
			$new->addSimpleSiteLink( $link, 'add' );

			$link = new SimpleSiteLink( 'dewiki', "Dummy" );
			$new->addSimpleSiteLink( $link, 'add' );

			$changes['change-sitelink-order'] = EntityChange::newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			// -----
			$link = new SimpleSiteLink( 'dewiki', "Dummy2" );
			$new->addSimpleSiteLink( $link, 'set' );
			$changes['change-dewiki-sitelink'] = EntityChange::newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			$link = new SimpleSiteLink( 'enwiki', "Emmy2" );
			$new->addSimpleSiteLink( $link, 'set' );
			$changes['change-enwiki-sitelink'] = EntityChange::newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			$new->removeSiteLink( 'dewiki', false );
			$changes['remove-dewiki-sitelink'] = EntityChange::newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			// -----
			$new->setLabel( "de", "dummy" );
			$changes['set-de-label'] = EntityChange::newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			$new->setLabel( "en", "emmy" );
			$changes['set-en-label'] = EntityChange::newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			$new->setAliases( "en", array( "foo", "bar" ) );
			$changes['set-en-aliases'] = EntityChange::newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			// -----
			$propertyId = new EntityId( \Wikibase\Property::ENTITY_TYPE, 23 );
			$snak = new \Wikibase\PropertyNoValueSnak( $propertyId );
			$claim = new \Wikibase\Claim( $snak );

			$claims = new \Wikibase\Claims( array( $claim ) );
			$new->setClaims( $claims );
			$changes['add-claim'] = EntityChange::newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			$claims = new \Wikibase\Claims();
			$new->setClaims( $claims );
			$changes['remove-claim'] = EntityChange::newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			// -----
			$changes['item-deletion-linked'] = EntityChange::newFromUpdate( EntityChange::REMOVE, $old, null );

			// -----
			$new->removeSiteLink( 'enwiki', false );
			$changes['remove-enwiki-sitelink'] = EntityChange::newFromUpdate( EntityChange::UPDATE, $old, $new );
			$old = $new->copy();

			// apply all the defaults ----------
			$defaults = array(
				'user_id' => 0,
				'time' => '20130101000000',
				'type' => 'test',
			);

			$rev = 1000;

			/* @var EntityChange $change */
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

		/* @var EntityChange $change */
		foreach ( $changes as $key => $change ) {
			$clones[$key] = clone $change;
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
	 * @return \Wikibase\ChangeRow[] a list of changes
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

			/* @var \Wikibase\ChangeRow $change */
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

	protected static function applyDefaults( \Wikibase\Change $change, array $defaults ) {
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
			if ( $change instanceof \Wikibase\DiffChange
				&& $change->hasDiff() ) {
				$diffs[] = $change->getDiff();
			}
		}

		return $diffs;
	}

	public static function getEntities() {
		$entityList = array();

		$entities = array(
			Item::newEmpty(),
			\Wikibase\Property::newEmpty(),
		);

		/**
		 * @var \Wikibase\Entity $entity
		 */
		foreach( $entities as $entity ) {
			$entityList[] = $entity;

			$entity->setId( 112 );
			$entity->stub();
			$entity->setLabel( 'ja', '\u30d3\u30fc\u30eb' );

			$entityList[] = $entity;
		}


		return $entityList;
	}
}
