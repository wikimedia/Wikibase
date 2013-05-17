<?php

namespace Wikibase\Database\Tests;

use Wikibase\Database\SelectFailedException;

/**
 * @covers Wikibase\Database\SelectFailedException class.
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
 * @ingroup WikibaseDatabaseTest
 *
 * @group Wikibase
 * @group WikibaseDatabase
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class SelectFailedExceptionTest extends \PHPUnit_Framework_TestCase {

	public function testConstructorWithOnlyRequiredArguments() {
		$tableName = 'nyancats';
		$fields = array( 'bar', 'baz', 'bah' );
		$conditions = array( 'foo' => 42, 'awesome > 9000' );

		$exception = new SelectFailedException( $tableName, $fields, $conditions );

		$this->assertEquals( $tableName, $exception->getTableName() );
		$this->assertEquals( $fields, $exception->getFields() );
		$this->assertEquals( $conditions, $exception->getConditions() );
	}

	public function testConstructorWithAllArguments() {
		$tableName = 'users';
		$fields = array( 'bar' );
		$conditions = array( 'foo' => 42 );
		$message = 'NyanData all the way accross the sky!';
		$previous = new \Exception( 'Onoez!' );

		$exception = new SelectFailedException( $tableName, $fields, $conditions, $message, $previous );

		$this->assertEquals( $tableName, $exception->getTableName() );
		$this->assertEquals( $fields, $exception->getFields() );
		$this->assertEquals( $conditions, $exception->getConditions() );
		$this->assertEquals( $message, $exception->getMessage() );
		$this->assertEquals( $previous, $exception->getPrevious() );
	}

}
