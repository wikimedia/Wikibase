<?php

namespace Wikibase\Client\Tests\Serializer;

use PHPUnit_Framework_TestCase;
use Wikibase\Client\Serializer\ClientEntitySerializer;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;

/**
 * @covers Wikibase\Client\Serializer\ClientEntitySerializer
 *
 * @group Wikibase
 * @group WikibaseClient
 *
 * @license GPL-2.0+
 * @author Thiemo Mättig
 */
class ClientEntitySerializerTest extends PHPUnit_Framework_TestCase {

	private function newInstance() {
		$fallbackChain = $this->getMockBuilder( 'Wikibase\LanguageFallbackChain' )
			->disableOriginalConstructor()
			->getMock();
		$fallbackChain->expects( $this->any() )
			->method( 'extractPreferredValue' )
			->will( $this->returnValue( array( 'source' => '<SOURCE>' ) ) );

		$dataTypeLookup = $this->getMock(
			'Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup'
		);
		$dataTypeLookup->expects( $this->any() )
			->method( 'getDataTypeIdForProperty' )
			->will( $this->returnValue( '<DATATYPE>' ) );

		return new ClientEntitySerializer(
			$dataTypeLookup,
			array( 'en' ),
			array( 'en' => $fallbackChain )
		);
	}

	public function testSerialize() {
		$item = new Item();
		$item->setLabel( 'de', 'German' );
		$item->setDescription( 'de', 'German' );
		$item->setAliases( 'de', array( 'German' ) );
		$item->setAliases( 'en', array( 'English' ) );
		$item->getStatements()->addNewStatement( new PropertyNoValueSnak( 1 ) );

		$instance = $this->newInstance();
		$serialization = $instance->serialize( $item );

		$expected = array(
			'type' => 'item',
			'labels' => array(
				'en' => array( 'source-language' => '<SOURCE>' ),
			),
			'descriptions' => array(
				'en' => array( 'source-language' => '<SOURCE>' ),
			),
			'aliases' => array(
				'en' => array( array( 'language' => 'en', 'value' => 'English' ) ),
			),
			'claims' => array(
				'P1' => array( array(
					'mainsnak' => array(
						'snaktype' => 'novalue',
						'property' => 'P1',
						'datatype' => '<DATATYPE>',
					),
					'type' => 'statement',
					'rank' => 'normal'
				) ),
			),
		);
		$this->assertSame( $expected, $serialization );
	}

}
