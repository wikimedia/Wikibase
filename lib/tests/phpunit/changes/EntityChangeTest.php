<?php

namespace Wikibase\Test;

use Wikibase\DataModel\Entity\Entity;
use Wikibase\EntityChange;

/**
 * @covers Wikibase\EntityChange
 *
 * @since 0.3
 *
 * @group Database
 * @group Wikibase
 * @group WikibaseLib
 * @group WikibaseChange
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Daniel Kinzler
 */
class EntityChangeTest extends DiffChangeTest {

	public function __construct( $name = null, $data = array(), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );

		// don't include entity data, it's skipped during serialization!
		// $this->allowedInfoKeys[] = 'entity';

		$this->allowedChangeKeys = array( // see TestChanges::getChanges()
			'property-creation',
			'property-deletion',
			'property-set-label',
			'item-creation',
			'item-deletion',
			'set-dewiki-sitelink',
			'set-enwiki-sitelink',
			'change-dewiki-sitelink',
			'change-enwiki-sitelink',
			'remove-dewiki-sitelink',
			'set-de-label',
			'set-en-label',
			'set-en-aliases',
			'add-claim',
			'remove-claim',
			'item-deletion-linked',
			'remove-enwiki-sitelink',
		);
	}

	/**
	 * @see ORMRowTest::getRowClass
	 * @since 0.4
	 * @return string
	 */
	protected function getRowClass() {
		return 'Wikibase\EntityChange';
	}

	/**
	 * Returns the name of the class of the entities under test.
	 *
	 * @since 0.4
	 * @return string
	 */
	protected function getEntityClass() {
		return 'Wikibase\Entity';
	}


	public function entityProvider() {
		$entityClass = $this->getEntityClass(); // PHP fail

		$entities = array_filter(
			TestChanges::getEntities(),
			function( Entity $entity ) use ( $entityClass ) {
				return is_a( $entity, $entityClass );
			}
		);

		$cases = array_map(
			function( Entity $entity ) {
				return array( $entity );
			},
			$entities
		);

		return $cases;
	}

	public function changeProvider() {
		$rowClass = $this->getRowClass(); // PHP fail

		$changes = array_filter(
			TestChanges::getChanges(),
			function( EntityChange $change ) use ( $rowClass ) {
				return is_a( $change, $rowClass );
			}
		);

		$cases = array_map(
			function( EntityChange $change ) {
				return array( $change );
			},
			$changes );

		return $cases;
	}

	/**
	 * @dataProvider changeProvider
	 *
	 * @param EntityChange $entityChange
	 */
	public function testGetType( EntityChange $entityChange ) {
		$this->assertInternalType( 'string', $entityChange->getType() );
	}

	/**
	 * @dataProvider entityProvider
	 *
	 * @param Entity $entity
	 */
	public function testSetAndGetEntity( Entity $entity ) {
		$changeFactory = TestChanges::getEntityChangeFactory();
		$entityChange = $changeFactory->newForEntity( EntityChange::UPDATE, $entity->getId() );

		$entityChange->setEntity( $entity );
		$this->assertEquals( $entity, $entityChange->getEntity() );
	}

	/**
	 * @dataProvider changeProvider
	 * @since 0.3
	 */
	public function testMetadata( EntityChange $entityChange ) {
		$entityChange->setMetadata( array(
			'kittens' => 3,
			'rev_id' => 23,
			'user_text' => '171.80.182.208',
		) );
		$this->assertEquals(
			array(
				'rev_id' => 23,
				'user_text' => '171.80.182.208',
				'comment' => $entityChange->getComment(),
			),
			$entityChange->getMetadata()
		);
	}

	/**
	 * @dataProvider changeProvider
	 * @since 0.3
	 */
	public function testGetEmptyMetadata( EntityChange $entityChange ) {
		$entityChange->setField( 'info', array() );
		$this->assertEquals(
			false,
			$entityChange->getMetadata()
		);
	}

	/**
	 * @dataProvider changeProvider
	 * @since 0.4
	 */
	public function testToString( EntityChange $entityChange ) {
		$s = "$entityChange"; // magically calls __toString()

		$id = $entityChange->getEntityId()->getPrefixedId();
		$type = $entityChange->getType();

		$this->assertTrue( stripos( $s, $id ) !== false, "missing entity ID $id" );
		$this->assertTrue( stripos( $s, $type ) !== false, "missing type $type" );
	}
}
