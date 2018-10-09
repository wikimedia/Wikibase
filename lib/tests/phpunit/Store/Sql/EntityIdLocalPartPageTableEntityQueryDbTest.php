<?php

namespace Wikibase\Lib\Tests\Store\Sql;

use MediaWiki\Storage\NameTableStore;
use MediaWikiTestCase;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Lib\Store\Sql\EntityIdLocalPartPageTableEntityQuery;

/**
 * @group Wikibase
 * @group WikibaseLib
 * @group Database
 *
 * @covers \Wikibase\Lib\Store\Sql\EntityIdLocalPartPageTableEntityQuery
 * @covers \Wikibase\Lib\Store\Sql\PageTableEntityQueryBase
 *
 * @license GPL-2.0-or-later
 */
class EntityIdLocalPartPageTableEntityQueryDbTest extends MediaWikiTestCase {

	public function setUp() {
		parent::setUp();
		$this->tablesUsed[] = 'page';
		$this->tablesUsed[] = 'slots';
		$this->tablesUsed[] = 'slot_roles';
		$this->db->insert( 'page', [
			'page_title' => 'localPartOne',
			'page_namespace' => 1,
			'page_restrictions' => '',
			'page_random' => 1,
			'page_latest' => 1,
			'page_len' => 1,
		] );
		$this->db->insert( 'page', [
			'page_title' => 'localPartTwo',
			'page_namespace' => 2,
			'page_restrictions' => '',
			'page_random' => 2,
			'page_latest' => 221,
			'page_len' => 2,
		] );
		$this->db->insert( 'slots', [
			'slot_revision_id' => 221,
			'slot_role_id' => 22,
			'slot_content_id' => 223,
			'slot_origin' => 224,
		] );
		$this->db->insert( 'slot_roles', [
			'role_id' => 22,
			'role_name' => 'second',
		] );
	}

	private function getQuery() {
		$slotRoleStore = $this->prophesize( NameTableStore::class );
		$slotRoleStore->getId( 'main' )->wilLReturn( 0 );
		$slotRoleStore->getId( 'second' )->willReturn( 22 );

		return new EntityIdLocalPartPageTableEntityQuery(
			new EntityNamespaceLookup( [ 'entityTypeOne' => 1, 'entityTypeTwo' => 2 ], [ 'entityTypeTwo' => 'second' ] ),
			$slotRoleStore->reveal()
		);
	}

	/**
	 * @param string $type
	 * @param string $localPart
	 * @return EntityId
	 */
	private function getMockEntityId( $type, $localPart ) {
		$id = $this->prophesize( EntityId::class );
		$id->getLocalPart()->willReturn( $localPart );
		$id->getEntityType()->willReturn( $type );

		return $id->reveal();
	}

	public function provideSelectRows() {
		return [
			[
				[], [], [ $this->getMockEntityId( 'entityTypeOne', 'localPartOne' ) ],
				[ 'localPartOne' => (object)[ 'page_title' => 'localPartOne' ] ]
			],
			[
				[ 'page_namespace' ], [], [ $this->getMockEntityId( 'entityTypeOne', 'localPartOne' ) ],
				[ 'localPartOne' => (object)[ 'page_title' => 'localPartOne', 'page_namespace' => 1 ] ]
			],
			[
				[ 'page_namespace' ], [], [ $this->getMockEntityId( 'entityTypeTwo', 'localPartTwo' ) ],
				[ 'localPartTwo' => (object)[ 'page_title' => 'localPartTwo', 'page_namespace' => 2 ] ]
			],
		];
	}

	/**
	 * @dataProvider provideSelectRows
	 */
	public function testSelectRows( $fields, $joins, $entityIds, $expected ) {
		$query = $this->getQuery();
		$rows = $query->selectRows(
			$fields,
			$joins,
			$entityIds,
			$this->db
		);
		$this->assertEquals( $expected, $rows );
	}

}
