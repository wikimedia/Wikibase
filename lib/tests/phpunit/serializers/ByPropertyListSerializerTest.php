<?php

namespace Wikibase\Test;

use DataValues\StringValue;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\Lib\Serializers\ByPropertyListSerializer;
use Wikibase\Lib\Serializers\SerializationOptions;
use Wikibase\Lib\Serializers\SnakSerializer;

/**
 * @covers Wikibase\Lib\Serializers\ByPropertyListSerializer
 *
 * @group WikibaseLib
 * @group Wikibase
 * @group WikibaseSerialization
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ByPropertyListSerializerTest extends SerializerBaseTest {

	/**
	 * @see SerializerBaseTest::getInstance
	 *
	 * @return ByPropertyListSerializer
	 */
	protected function getInstance() {
		return new ByPropertyListSerializer( 'test', new SnakSerializer() );
	}

	/**
	 * @see SerializerBaseTest::validProvider
	 *
	 * @return array
	 */
	public function validProvider() {
		$validArgs = array();

		$dataValue0 = new StringValue( 'ohi' );

		$id42 = new PropertyId( 'p42' );
		$id2 = new PropertyId( 'p2' );

		$snak0 = new PropertyNoValueSnak( $id42 );
		$snak1 = new PropertySomeValueSnak( $id2 );
		$snak2 = new PropertyValueSnak( $id2, $dataValue0 );

		$validArgs[] = new SnakList( array( $snak0, $snak1, $snak2 ) );

		$validArgs = $this->arrayWrap( $validArgs );

		$validArgs[ 'Empty' ] = array(
			new SnakList(),
			array(),
		);

		$validArgs[ 'Default' ] = array(
			new SnakList( array( $snak0, $snak1, $snak2 ) ),
			array(
				'P42' => array(
					0 => array(
						'snaktype' => 'novalue',
						'property' => 'P42',
					),
				),
				'P2' => array(
					0 => array(
						'snaktype' => 'somevalue',
						'property' => 'P2',
					),
					1 => array(
						'snaktype' => 'value',
						'property' => 'P2',
						'datavalue' => $dataValue0->toArray(),
					),
				),
			),
		);

		return $validArgs;
	}

}
