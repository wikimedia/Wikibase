<?php

namespace Wikibase\Test;
use Language;
use Site;
use Sites;
use TestSites;
use Wikibase\DirectSqlStore;
use Wikibase\Settings;
use Wikibase\SettingsArray;

/**
 * Tests for the Wikibase\DirectSqlStore class.
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
 * @since 0.4
 *
 * @ingroup WikibaseClient
 * @ingroup Test
 *
 * @covers Wikibase\DirectSqlStore
 *
 * @group Database
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseClientStore
 *
 * @licence GNU GPL v2+
 * @author DanielKinzler
 */
class DirectSqlStoreTest extends \MediaWikiTestCase {

	protected function newStore() {
		$site = new Site( \MediaWikiSite::TYPE_MEDIAWIKI );
		$site->setGlobalId( 'DirectSqlStoreTestDummySite' );

		$lang = Language::factory( 'en' );

		$repoWiki = 'DirectSqlStoreTestDummyRepoId';

		$store = new DirectSqlStore( $lang, $site, $repoWiki );
		return $store;
	}

	public function testNewFromSettings() {
		$site = new Site( \MediaWikiSite::TYPE_MEDIAWIKI );
		$site->setGlobalId( 'DirectSqlStoreTestDummySite' );

		$sites = new MockSiteStore();
		$sites->saveSite( $site );

		$lang = Language::factory( 'en' );

		$settings = new SettingsArray();
		$settings->setSetting( 'repoDatabase', 'DirectSqlStoreTestDummyRepoId' );
		$settings->setSetting( 'siteGlobalID', 'DirectSqlStoreTestDummySite' );

		$store = DirectSqlStore::newFromSettings( $settings, $lang, $sites );
		$this->assertInstanceOf( 'Wikibase\DirectSqlStore', $store );
	}

	/**
	 * @dataProvider provideGetters
	 */
	public function testGetters( $getter, $expectedType ) {
		$store = $this->newStore();

		$obj = $store->$getter();

		$this->assertInstanceOf( $expectedType, $obj );
	}

	public static function provideGetters() {
		return array(
			array( 'getEntityUsageIndex', 'Wikibase\EntityUsageIndex' ),
			array( 'getSiteLinkTable', 'Wikibase\SiteLinkTable' ),
			array( 'getEntityLookup', 'Wikibase\EntityLookup' ),
			array( 'getTermIndex', 'Wikibase\TermIndex' ),
			array( 'getPropertyLabelResolver', 'Wikibase\PropertyLabelResolver' ),
			array( 'newChangesTable', 'Wikibase\ChangesTable' ),
			array( 'getPropertyInfoStore', 'Wikibase\PropertyInfoStore' ),
		);
	}

}
