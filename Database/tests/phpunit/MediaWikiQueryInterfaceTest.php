<?php

namespace Wikibase\Test\Database;

use DatabaseBase;
use Wikibase\Database\MediaWikiQueryInterface;
use Wikibase\Database\QueryInterface;
use Wikibase\Database\TableDefinition;
use Wikibase\Database\FieldDefinition;
use Wikibase\Repo\DBConnectionProvider;

/**
 * @covers Wikibase\Database\MediaWikiQueryInterface
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
 * @since wd.db
 *
 * @ingroup WikibaseDatabaseTest
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseDatabase
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class MediaWikiQueryInterfaceTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @return QueryInterface
	 */
	protected function newInstance() {
		$connection = $this->getMock( 'DatabaseMysql' );

		$connectionProvider = new DirectConnectionProvider( $connection );

		return new MediaWikiQueryInterface(
			$connectionProvider,
			$this->getMock( '\Wikibase\Database\MWDB\ExtendedMySQLAbstraction' )
		);
	}

	/**
	 * @dataProvider tableNameProvider
	 *
	 * @param string $tableName
	 */
	public function testTableExists( $tableName ) {
		$connection = $this->getMock( 'DatabaseMysql' );
		$extendedAbstraction = $this->getMockBuilder( '\Wikibase\Database\MWDB\ExtendedMySQLAbstraction' )
			->disableOriginalConstructor()->getMock();

		$queryInterface = new MediaWikiQueryInterface(
			new DirectConnectionProvider( $connection ),
			$extendedAbstraction
		);

		$connection->expects( $this->once() )
			->method( 'tableExists' )
			->with( $this->equalTo( $tableName ) );

		$queryInterface->tableExists( $tableName );
	}

	public function tableNameProvider() {
		$argLists = array();

		$argLists[] = array( 'user' );
		$argLists[] = array( 'xdgxftjhreyetfytj' );
		$argLists[] = array( 'a' );
		$argLists[] = array( 'foo_bar_baz_bah' );

		return $argLists;
	}

	/**
	 * @dataProvider tableProvider
	 *
	 * @param TableDefinition $table
	 */
	public function testCreateTable( TableDefinition $table ) {
		$connection = $this->getMock( 'DatabaseMysql' );
		$extendedAbstraction = $this->getMockBuilder( '\Wikibase\Database\MWDB\ExtendedMySQLAbstraction' )
			->disableOriginalConstructor()->getMock();

		$queryInterface = new MediaWikiQueryInterface(
			new DirectConnectionProvider( $connection ),
			$extendedAbstraction
		);

		$extendedAbstraction->expects( $this->once() )
			->method( 'createTable' )
			->with( $this->equalTo( $table ) );

		$queryInterface->createTable( $table );
	}

	/**
	 * @dataProvider tableProvider
	 *
	 * @param TableDefinition $table
	 */
	public function testDropTable( TableDefinition $table ) {
		$connection = $this->getMock( 'DatabaseMysql' );
		$extendedAbstraction = $this->getMockBuilder( '\Wikibase\Database\MWDB\ExtendedMySQLAbstraction' )
			->disableOriginalConstructor()->getMock();

		$queryInterface = new MediaWikiQueryInterface(
			new DirectConnectionProvider( $connection ),
			$extendedAbstraction
		);

		$connection->expects( $this->once() )
			->method( 'dropTable' )
			->with( $this->equalTo( $table ) );

		$queryInterface->dropTable( $table );
	}

	public function tableProvider() {
		$tables = array();

		$tables[] = new TableDefinition( 'differentfieldtypes', array(
			new FieldDefinition( 'intfield', FieldDefinition::TYPE_INTEGER ),
			new FieldDefinition( 'floatfield', FieldDefinition::TYPE_FLOAT ),
			new FieldDefinition( 'textfield', FieldDefinition::TYPE_TEXT ),
			new FieldDefinition( 'boolfield', FieldDefinition::TYPE_BOOLEAN ),
		) );

		$tables[] = new TableDefinition( 'defaultfieldvalues', array(
			new FieldDefinition( 'intfield', FieldDefinition::TYPE_INTEGER, true, 42 ),
		) );

		$tables[] = new TableDefinition( 'notnullfields', array(
			new FieldDefinition( 'intfield', FieldDefinition::TYPE_INTEGER, false ),
			new FieldDefinition( 'textfield', FieldDefinition::TYPE_TEXT, false ),
		) );

		$argLists = array();

		foreach ( $tables as $table ) {
			$argLists[] = array( $table );
		}

		return $argLists;
	}

	/**
	 * @dataProvider insertProvider
	 */
	public function testInsert( $tableName, array $fieldValues ) {
		$connection = $this->getMock( 'DatabaseMysql' );
		$extendedAbstraction = $this->getMockBuilder( '\Wikibase\Database\MWDB\ExtendedMySQLAbstraction' )
			->disableOriginalConstructor()->getMock();

		$queryInterface = new MediaWikiQueryInterface(
			new DirectConnectionProvider( $connection ),
			$extendedAbstraction
		);

		$connection->expects( $this->once() )
			->method( 'insert' )
			->with(
				$this->equalTo( $tableName ),
				$this->equalTo( $fieldValues )
			);

		$queryInterface->insert(
			$tableName,
			$fieldValues
		);
	}

	public function insertProvider() {
		$argLists = array();

		$argLists[] = array( 'foo', array() );

		$argLists[] = array( 'bar', array(
			'intfield' => 42,
		) );

		$argLists[] = array( 'baz', array(
			'intfield' => 42,
			'textfield' => '~=[,,_,,]:3',
		) );

		return $argLists;
	}

	/**
	 * @dataProvider updateProvider
	 */
	public function testUpdate( $tableName, array $newValues, array $conditions ) {
		$connection = $this->getMock( 'DatabaseMysql' );
		$extendedAbstraction = $this->getMockBuilder( '\Wikibase\Database\MWDB\ExtendedMySQLAbstraction' )
			->disableOriginalConstructor()->getMock();

		$queryInterface = new MediaWikiQueryInterface(
			new DirectConnectionProvider( $connection ),
			$extendedAbstraction
		);

		$connection->expects( $this->once() )
			->method( 'update' )
			->with(
				$this->equalTo( $tableName ),
				$this->equalTo( $newValues ),
				$this->equalTo( $conditions )
			);

		$queryInterface->update(
			$tableName,
			$newValues,
			$conditions
		);
	}

	public function updateProvider() {
		$argLists = array();

		$argLists[] = array(
			'foo',
			array(
				'intfield' => 42,
				'textfield' => 'foobar baz',
			),
			array(
			)
		);

		$argLists[] = array(
			'foo',
			array(
				'textfield' => '~=[,,_,,]:3',
			),
			array(
				'intfield' => 0
			)
		);

		$argLists[] = array(
			'foo',
			array(
				'textfield' => '~=[,,_,,]:3',
				'intfield' => 0,
				'floatfield' => 4.2,
			),
			array(
				'textfield' => '~[,,_,,]:3',
				'floatfield' => 9000.1,
			)
		);

		return $argLists;
	}

	/**
	 * @dataProvider deleteProvider
	 */
	public function testDelete( $tableName, array $conditions ) {
		$connection = $this->getMock( 'DatabaseMysql' );
		$extendedAbstraction = $this->getMockBuilder( '\Wikibase\Database\MWDB\ExtendedMySQLAbstraction' )
			->disableOriginalConstructor()->getMock();

		$queryInterface = new MediaWikiQueryInterface(
			new DirectConnectionProvider( $connection ),
			$extendedAbstraction
		);

		$connection->expects( $this->once() )
			->method( 'delete' )
			->with(
				$this->equalTo( $tableName ),
				$this->equalTo( $conditions )
			);

		$queryInterface->delete( $tableName, $conditions );
	}

	public function deleteProvider() {
		$argLists = array();

		$argLists[] = array( 'foo', array() );

		$argLists[] = array( 'bar', array(
			'intfield' => 42,
		) );

		$argLists[] = array( 'baz', array(
			'intfield' => 42,
			'textfield' => '~=[,,_,,]:3',
		) );

		return $argLists;
	}

	public function testGetInsertId() {
		$connection = $this->getMock( 'DatabaseMysql' );
		$extendedAbstraction = $this->getMockBuilder( '\Wikibase\Database\MWDB\ExtendedMySQLAbstraction' )
			->disableOriginalConstructor()->getMock();

		$queryInterface = new MediaWikiQueryInterface(
			new DirectConnectionProvider( $connection ),
			$extendedAbstraction
		);

		$connection->expects( $this->once() )
			->method( 'insertId' )
			->will( $this->returnValue( 42 ) );

		$this->assertEquals( 42, $queryInterface->getInsertId() );
	}

}

class DirectConnectionProvider implements DBConnectionProvider {

	protected $connection;

	public function __construct( DatabaseBase $connection ) {
		$this->connection = $connection;
	}

	/**
	 * @see DBConnectionProvider::getConnection
	 *
	 * @since 0.4
	 *
	 * @return DatabaseBase
	 */
	public function getConnection() {
		return $this->connection;
	}

	/**
	 * @see DBConnectionProvider::releaseConnection
	 *
	 * @since 0.4
	 */
	public function releaseConnection() {

	}

}
