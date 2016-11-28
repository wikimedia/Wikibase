<?php

namespace Wikibase\Repo\Tests\Store\Sql;

use MediaWikiTestCase;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Repo\Store\EntityIdPager;
use Wikibase\Repo\Store\Sql\SqlEntityIdPager;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers Wikibase\Repo\Store\Sql\SqlEntityIdPager
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseStore
 *
 * @group Database
 *
 * @group medium
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 * @author Marius Hoch
 */
class SqlEntityIdPagerTest extends MediaWikiTestCase {

	public function setUp() {
		parent::setUp();
		$this->tablesUsed[] = 'page';
		$this->tablesUsed[] = 'redirect';
	}

	public function addDBDataOnce() {
		// We need to initially empty the table
		wfGetDB( DB_MASTER )->delete( 'page', '*', __METHOD__ );
		wfGetDB( DB_MASTER )->delete( 'redirect', '*', __METHOD__ );
	}

	/**
	 * @param EntityDocument[] $entities
	 * @param EntityRedirect[] $redirects
	 */
	private function insertEntities( array $entities = [], array $redirects = [] ) {
		$pageRows = [];
		foreach ( $entities as $entity ) {
			$pageRows[] = $this->getPageRow( $entity->getId(), false );
		}

		foreach ( $redirects as $redirect ) {
			$pageRows[] = $this->getPageRow( $redirect->getEntityId(), true );
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->insert(
			'page',
			$pageRows,
			__METHOD__
		);

		if ( !$redirects ) {
			return;
		}

		$redirectRows = [];
		foreach ( $redirects as $redirect ) {
			$redirectRows[] = $this->getRedirectRow( $redirect );
		}

		$dbw->insert(
			'redirect',
			$redirectRows,
			__METHOD__
		);
	}

	private function getPageRow( EntityId $entityId, $isRedirect ) {
		$entityNamespaceLookup = WikibaseRepo::getDefaultInstance()->getEntityNamespaceLookup();

		return [
			'page_namespace' => $entityNamespaceLookup->getEntityNamespace( $entityId->getEntityType() ),
			'page_title' => $entityId->getSerialization(),
			'page_restrictions' => '',
			'page_random' => 0,
			'page_latest' => 0,
			'page_len' => 1,
			'page_is_redirect' => $isRedirect ? 1 : 0
		];
	}

	private function getRedirectRow( EntityRedirect $redirect ) {
		$entityTitleLookup = WikibaseRepo::getDefaultInstance()->getEntityTitleLookup();

		$redirectTitle = $entityTitleLookup->getTitleForId( $redirect->getEntityId() );
		return [
			'rd_from' => $redirectTitle->getArticleID(),
			'rd_namespace' => $redirectTitle->getNamespace(),
			'rd_title' => $redirectTitle->getDBkey()
		];
	}

	private function getIdStrings( array $entities ) {
		$ids = array_map( function ( $entity ) {
			if ( $entity instanceof EntityDocument ) {
				$entity = $entity->getId();
			} elseif ( $entity instanceof EntityRedirect ) {
				$entity = $entity->getEntityId();
			}

			return $entity->getSerialization();
		}, $entities );

		return $ids;
	}

	private function assertEqualIds( array $expected, array $actual, $msg = null ) {
		$expectedIds = $this->getIdStrings( $expected );
		$actualIds = $this->getIdStrings( $actual );

		$this->assertArrayEquals( $expectedIds, $actualIds, true );
	}

	/**
	 * @dataProvider listEntitiesProvider
	 *
	 * @param string|null $entityType
	 * @param int $limit
	 * @param int $calls
	 * @param string $redirectMode
	 * @param array[] $expectedChunks
	 * @param EntityDocument[] $entitiesToInsert
	 * @param EntityRedirect[] $redirectsToInsert
	 */
	public function testFetchIds(
		$entityType,
		$limit,
		$calls,
		$redirectMode,
		array $expectedChunks,
		array $entitiesToInsert = [],
		array $redirectsToInsert = []
	) {
		$this->insertEntities( $entitiesToInsert, $redirectsToInsert );

		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$pager = new SqlEntityIdPager(
			$wikibaseRepo->getEntityNamespaceLookup(),
			$wikibaseRepo->getEntityIdParser(),
			$entityType,
			$redirectMode
		);

		for ( $i = 0; $i < $calls; $i++ ) {
			$actual = $pager->fetchIds( $limit );
			$this->assertEqualIds( $expectedChunks[$i], $actual );
		}
	}

	public function listEntitiesProvider() {
		$property = new Property( new PropertyId( 'P1' ), null, 'string' );
		$item = new Item( new ItemId( 'Q5' ) );
		$redirect = new EntityRedirect( new ItemId( 'Q55' ), new ItemId( 'Q5' ) );

		return [
			'empty' => [
				null,
				100,
				1,
				EntityIdPager::NO_REDIRECTS,
				[ [] ]
			],
			'no matches' => [
				Item::ENTITY_TYPE,
				100,
				1,
				EntityIdPager::NO_REDIRECTS,
				[ [] ],
				[ $property ],
				[ $redirect ]
			],
			'some entities' => [
				null,
				100,
				1,
				EntityIdPager::NO_REDIRECTS,
				[ [ $property, $item ] ],
				[ $property, $item ],
				[ $redirect ]
			],
			'two chunks' => [
				null,
				1,
				2,
				EntityIdPager::NO_REDIRECTS,
				[ [ $property ], [ $item ] ],
				[ $property, $item ],
				[ $redirect ]
			],
			'chunks with limit > 1' => [
				null,
				2,
				3,
				EntityIdPager::INCLUDE_REDIRECTS,
				[ [ $property, $item ], [ $redirect ], [] ],
				[ $property, $item ],
				[ $redirect ]
			],
			'four chunks (two empty)' => [
				null,
				1,
				4,
				EntityIdPager::NO_REDIRECTS,
				[ [ $property ], [ $item ], [], [] ],
				[ $property, $item ],
				[ $redirect ]
			],
			'include redirects' => [
				null,
				100,
				1,
				EntityIdPager::INCLUDE_REDIRECTS,
				[ [ $property, $item, $redirect ] ],
				[ $property, $item ],
				[ $redirect ]
			],
			'only redirects' => [
				null,
				100,
				1,
				EntityIdPager::ONLY_REDIRECTS,
				[ [ $redirect ] ],
				[ $property, $item ],
				[ $redirect ]
			],
			'just properties' => [
				Property::ENTITY_TYPE,
				100,
				1,
				EntityIdPager::NO_REDIRECTS,
				[ [ $property ] ],
				[ $property, $item ],
				[ $redirect ]
			],
			'limit' => [
				Property::ENTITY_TYPE,
				1,
				1,
				EntityIdPager::NO_REDIRECTS,
				[ [ $property ] ],
				[ $property, $item ],
				[ $redirect ]
			]
		];
	}

}
