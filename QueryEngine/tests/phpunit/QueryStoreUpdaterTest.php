<?php

namespace Wikibase\QueryEngine\Tests;

use Wikibase\Item;
use Wikibase\QueryEngine\QueryStoreWriter;

/**
 * Base test class for Wikibase\QueryEngine\QueryStoreUpdater implementing classes.
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
 * @since 0.1
 *
 * @ingroup WikibaseQueryEngineTest
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
abstract class QueryStoreUpdaterTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @since 0.1
	 *
	 * @return QueryStoreWriter[]
	 */
	protected abstract function getInstances();

	/**
	 * @since 0.1
	 *
	 * @return QueryStoreWriter[][]
	 */
	public function instanceProvider() {
		$argLists = array();

		foreach ( $this->getInstances() as $instance ) {
			$argLists[] = array( $instance );
		}

		return $argLists;
	}

	/**
	 * @dataProvider instanceProvider
	 *
	 * @param QueryStoreWriter $updater
	 */
	public function testInsertEntityDoesNotFatal( QueryStoreWriter $updater ) {
		$item = Item::newEmpty();
		$item->setId( 42 );

		$updater->insertEntity( $item );

		$this->assertTrue( true );
	}

	/**
	 * @dataProvider instanceProvider
	 *
	 * @param QueryStoreWriter $updater
	 */
	public function testUpdateEntityDoesNotFatal( QueryStoreWriter $updater ) {
		$item = Item::newEmpty();
		$item->setId( 42 );

		$updater->updateEntity( $item );

		$this->assertTrue( true );
	}

	/**
	 * @dataProvider instanceProvider
	 *
	 * @param QueryStoreWriter $updater
	 */
	public function testDeleteEntityDoesNotFatal( QueryStoreWriter $updater ) {
		$item = Item::newEmpty();
		$item->setId( 42 );

		$updater->deleteEntity( $item );

		$this->assertTrue( true );
	}

}
