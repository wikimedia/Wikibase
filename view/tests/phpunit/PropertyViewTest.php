<?php

namespace Wikibase\Test;

use DataTypes\DataTypeFactory;
use Language;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\View\PropertyView;
use Wikibase\View\Template\TemplateFactory;

/**
 * @covers Wikibase\View\EntityView
 * @covers Wikibase\View\PropertyView
 *
 * @uses Wikibase\View\Template\Template
 * @uses Wikibase\View\Template\TemplateFactory
 * @uses Wikibase\View\Template\TemplateRegistry
 * @uses Wikibase\View\TextInjector
 *
 * @group Wikibase
 * @group WikibaseView
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class PropertyViewTest extends EntityViewTest {

	/**
	 * @param EntityId $id
	 * @param Statement[] $statements
	 *
	 * @return Entity
	 */
	protected function makeEntity( EntityId $id, array $statements = array() ) {
		$property = Property::newFromType( 'string' );
		$property->setId( $id );

		$property->setLabel( 'en', "label:$id" );
		$property->setDescription( 'en', "description:$id" );

		$property->setStatements( new StatementList( $statements ) );

		return $property;
	}

	/**
	 * Generates a suitable entity ID based on $n.
	 *
	 * @param int|string $n
	 *
	 * @return EntityId
	 */
	protected function makeEntityId( $n ) {
		return new PropertyId( "P$n" );
	}

	/**
	 * Prepares the given entity data for comparison with $entity.
	 * That is, this method should add any extra data from $entity to $entityData.
	 *
	 * @param Entity $entity
	 * @param array $entityData
	 */
	protected function prepareEntityData( Entity $entity, array &$entityData ) {
		/* @var Property $entity */
		$entityData['datatype'] = $entity->getDataTypeId();
	}

	public function provideTestGetHtml() {
		$templateFactory = TemplateFactory::getDefaultInstance();
		$propertyView = new PropertyView(
			$templateFactory,
			$this->getMockBuilder( 'Wikibase\View\EntityTermsView' )
				->disableOriginalConstructor()
				->getMock(),
			$this->getMockBuilder( 'Wikibase\View\StatementGroupListView' )
				->disableOriginalConstructor()
				->getMock(),
			$this->getDataTypeFactory(),
			Language::factory( 'en' ),
			true,
			false
		);

		return array(
			array(
				$propertyView,
				$this->newEntityRevisionForStatements( array() ),
				'/wb-property/'
			)
		);
	}

	private function getDataTypeFactory() {
		return new DataTypeFactory( array( 'type' => 'datavalue', 'string' => 'string' ) );
	}

}
