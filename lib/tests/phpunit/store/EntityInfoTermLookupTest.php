<?php

namespace Wikibase\Test;

use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Store\EntityInfo;
use Wikibase\Lib\Store\EntityInfoTermLookup;

/**
 * @covers Wikibase\Lib\Store\EntityInfoTermLookup
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert
 * @author Daniel Kinzler
 */
class EntityInfoTermLookupTest extends \MediaWikiTestCase {

	public function testGetLabel() {
		$termLookup = $this->getEntityInfoTermLookup();

		$label = $termLookup->getLabel( new ItemId( 'Q116' ), 'en' );
		$this->assertEquals( 'New York City', $label );
	}

	public function testGetLabel_notFoundThrowsException() {
		$termLookup = $this->getEntityInfoTermLookup();

		$this->setExpectedException( 'OutOfBoundsException' );
		$termLookup->getLabel( new ItemId( 'Q117' ), 'fr' );
	}

	public function testGetLabel_noEntityThrowsException() {
		$termLookup = $this->getEntityInfoTermLookup();

		$this->setExpectedException( 'OutOfBoundsException' );
		$termLookup->getLabel( new ItemId( 'Q90000' ), 'en' );
	}

	public function getLabelsProvider() {
		return array(
			array(
				array( 'en' => 'New York City', 'es' => 'Nueva York' ),
				new ItemId( 'Q116' )
			),
			array(
				array( 'es' => 'Nueva York' ),
				new ItemId( 'Q116' ),
				array( 'es' )
			),
			array(
				array( 'de' => 'Berlin' ),
				new ItemId( 'Q117' )
			)
		);
	}

	/**
	 * @dataProvider getLabelsProvider
	 */
	public function testGetLabels( $expected, EntityId $entityId, $languages = null ) {
		$termLookup = $this->getEntityInfoTermLookup();

		$labels = $termLookup->getLabels( $entityId, $languages );
		$this->assertEquals( $expected, $labels );
	}

	public function testGetLabels_noEntityThrowsException() {
		$termLookup = $this->getEntityInfoTermLookup();

		$this->setExpectedException( 'OutOfBoundsException' );
		$termLookup->getLabels( new ItemId( 'Q90000' ) );
	}

	public function testGetDescription() {
		$termLookup = $this->getEntityInfoTermLookup();

		$description = $termLookup->getDescription( new ItemId( 'Q116' ), 'de' );
		$expected = 'Metropole an der Ostküste der Vereinigten Staaten';

		$this->assertEquals( $expected, $description );
	}

	public function testGetDescription_notFoundThrowsException() {
		$termLookup = $this->getEntityInfoTermLookup();

		$this->setExpectedException( 'OutOfBoundsException' );
		$termLookup->getDescription( new ItemId( 'Q117' ), 'fr' );
	}

	public function testGetDescription_noEntityThrowsException() {
		$termLookup = $this->getEntityInfoTermLookup();

		$this->setExpectedException( 'OutOfBoundsException' );
		$termLookup->getDescription( new ItemId( 'Q90000' ), 'en' );
	}

	public function getDescriptionsProvider() {
		return array(
			array(
				array(
					'de' => 'Metropole an der Ostküste der Vereinigten Staaten',
					'en' => 'largest city in New York and the United States of America',
				),
				new ItemId( 'Q116' )
			),
			array(
				array(
					'de' => 'Metropole an der Ostküste der Vereinigten Staaten',
				),
				new ItemId( 'Q116' ),
				array( 'de', 'fr' )
			),
			array(
				array(),
				new ItemId( 'Q117' )
			)
		);
	}

	/**
	 * @dataProvider getDescriptionsProvider
	 */
	public function testGetDescriptions( $expected, EntityId $entityId, $languages = null ) {
		$termLookup = $this->getEntityInfoTermLookup();

		$descriptions = $termLookup->getDescriptions( $entityId, $languages );
		$this->assertEquals( $expected, $descriptions );
	}

	public function testGetDescriptions_noEntityThrowsException() {
		$termLookup = $this->getEntityInfoTermLookup();

		$this->setExpectedException( 'OutOfBoundsException' );
		$termLookup->getDescriptions( new ItemId( 'Q90000' ) );
	}

	private function getEntityInfoTermLookup() {
		$entityInfo = $this->makeEntityInfo();
		return new EntityInfoTermLookup( $entityInfo );
	}

	private function makeEntityInfo() {
		$entityInfo = array(
			'Q116' => array(
				'labels' => array(
					'en' => array( 'language' => 'en', 'value' => 'New York City' ),
					'es' => array( 'language' => 'es', 'value' => 'Nueva York' ),
				),
				'descriptions' => array(
					'en' => array( 'language' => 'en', 'value' => 'largest city in New York and the United States of America' ),
					'de' => array( 'language' => 'de', 'value' => 'Metropole an der Ostküste der Vereinigten Staaten' ),
				),
			),

			'Q117' => array(
				'labels' => array(
					'de' => array( 'language' => 'de', 'value' => 'Berlin' ),
				),
				'descriptions' => array()
			),
		);

		return new EntityInfo( $entityInfo );
	}

}
