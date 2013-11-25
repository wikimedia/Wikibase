<?php

namespace Wikibase\Test;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\SimpleSiteLink;
use Wikibase\Entity;
use Wikibase\Item;
use Wikibase\Lib\Serializers\ClaimSerializer;
use Wikibase\Lib\Serializers\ItemSerializer;
use Wikibase\Lib\Serializers\SerializationOptions;
use Wikibase\Lib\Serializers\EntitySerializer;
use Wikibase\Lib\Serializers\SerializerFactory;
use Wikibase\Property;

/**
 * @covers Wikibase\Lib\Serializers\DispatchingEntitySerializer
 *
 * @since 0.5
 *
 * @group WikibaseLib
 * @group Wikibase
 * @group WikibaseSerialization
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class DispatchingEntitySerializerTest extends EntitySerializerBaseTest {

	/**
	 * @see SerializerBaseTest::getClass
	 *
	 * @since 0.5
	 *
	 * @return string
	 */
	protected function getClass() {
		return '\Wikibase\Lib\Serializers\DispatchingEntitySerializer';
	}

	/**
	 * @return ItemSerializer
	 */
	protected function getInstance() {
		$factory = new SerializerFactory();

		$class = $this->getClass();
		return new $class( $factory );
	}

	/**
	 * @see SerializerBaseTest::validProvider
	 *
	 * @since 0.5
	 *
	 * @return array
	 */
	public function validProvider() {
		return array(
			array( $this->getItemInstance() ),
			array( $this->getPropertyInstance() ),
		);
	}

	/**
	 * @return Entity
	 */
	protected function getEntityInstance() {
		return $this->getInstance();
	}

	/**
	 * @return Entity
	 */
	protected function getItemInstance() {
		$item = Item::newEmpty();
		$item->setId( new ItemId( 'Q17' ) );
		$item->addSimpleSiteLink( new SimpleSiteLink( 'test', 'Foo' ) );

		return $item;
	}

	/**
	 * @return Entity
	 */
	protected function getPropertyInstance() {
		$property = Property::newEmpty();
		$property->setId( new PropertyId( 'P17' ) );
		$property->setDataTypeId( 'wibbly' );

		return $property;
	}
}
