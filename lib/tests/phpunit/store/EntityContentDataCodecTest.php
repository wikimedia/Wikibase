<?php

namespace Wikibase\Store\Test;

use DataValues\Deserializers\DataValueDeserializer;
use DataValues\Serializers\DataValueSerializer;
use MediaWikiTestCase;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\InternalSerialization\DeserializerFactory;
use Wikibase\InternalSerialization\SerializerFactory;
use Wikibase\Lib\Store\EntityContentDataCodec;

/**
 * @covers Wikibase\Lib\Store\EntityContentDataCodec
 *
 * @group Wikibase
 * @group WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class EntityContentDataCodecTest extends MediaWikiTestCase {

	private function getCodec( $maxBlobSize = 0 ) {
		$idParser = new BasicEntityIdParser();

		$serializerFactory = new SerializerFactory( new DataValueSerializer() );
		$deserializerFactory = new DeserializerFactory( new DataValueDeserializer(), $idParser );

		$codec = new EntityContentDataCodec(
			$idParser,
			$serializerFactory->newEntitySerializer(),
			$deserializerFactory->newEntityDeserializer(),
			$maxBlobSize
		);

		return $codec;
	}

	public function entityIdProvider() {
		$p1 = new PropertyId( 'P1' );
		$q11 = new ItemId( 'Q11' );

		return array(
			'PropertyId' => array( '{ "entity": "P1", "datatype": "string" }', $p1 ),
			'new style' => array( '{ "entity": "Q11" }', $q11 ),
			'old style' => array( '{ "entity": ["item", 11] }', $q11 ),
		);
	}

	/**
	 * @dataProvider entityIdProvider
	 */
	public function testEntityIdDecoding( $data, EntityId $id ) {
		$entity = $this->getCodec()->decodeEntity( $data, CONTENT_FORMAT_JSON );
		$this->assertEquals( $id, $entity->getId() );
	}

	public function entityProvider() {
		$empty = new Item( new ItemId( 'Q1' ) );

		$simple = new Item( new ItemId( 'Q1' ) );
		$simple->setLabel( 'en', 'Test' );

		return array(
			'Property' => array( Property::newFromType( 'string' ), null ),

			'empty' => array( $empty, null ),
			'empty json' => array( $empty, CONTENT_FORMAT_JSON ),

			'simple' => array( $simple, null ),
			'simple json' => array( $simple, CONTENT_FORMAT_JSON ),
			'simple php' => array( $simple, CONTENT_FORMAT_SERIALIZED ),
		);
	}

	/**
	 * @dataProvider entityProvider
	 */
	public function testEncodeAndDecodeEntity( EntityDocument $entity, $format ) {
		$blob = $this->getCodec()->encodeEntity( $entity, $format );
		$this->assertType( 'string', $blob );

		$actual = $this->getCodec()->decodeEntity( $blob, $format );
		$this->assertEquals( $entity, $actual, 'round trip' );
	}

	public function testEncodeBigEntity() {
		$entity = new Item( new ItemId( 'Q1' ) );

		$this->setExpectedException( 'MWContentSerializationException' );
		$this->getCodec( 6 )->encodeEntity( $entity, CONTENT_FORMAT_JSON );
	}

	public function testDecodeBigEntity() {
		$entity = new Item( new ItemId( 'Q1' ) );

		$blob = $this->getCodec()->encodeEntity( $entity, CONTENT_FORMAT_JSON );

		$this->setExpectedException( 'MWContentSerializationException' );
		$this->getCodec( 6 )->decodeEntity( $blob, CONTENT_FORMAT_JSON );
	}

	public function redirectProvider() {
		$q6 = new ItemId( 'Q6' );
		$q8 = new ItemId( 'Q8' );

		$redirect = new EntityRedirect( $q6, $q8 );

		return array(
			'redirect' => array( $redirect, null ),
			'empty json' => array( $redirect, CONTENT_FORMAT_JSON ),
		);
	}

	/**
	 * @dataProvider redirectProvider
	 */
	public function testEncodeAndDecodeRedirect( EntityRedirect $redirect, $format ) {
		$blob = $this->getCodec()->encodeRedirect( $redirect, $format );
		$this->assertType( 'string', $blob );

		$actual = $this->getCodec()->decodeRedirect( $blob, $format );
		$this->assertTrue( $redirect->equals( $actual ), 'round trip' );
	}

	public function testGetDefaultFormat_isJson() {
		$defaultFormat = $this->getCodec()->getDefaultFormat();
		$this->assertEquals( CONTENT_FORMAT_JSON, $defaultFormat );
	}

	public function testGetSupportedFormats() {
		$supportedFormats = $this->getCodec()->getSupportedFormats();
		$this->assertType( 'array', $supportedFormats );
		$this->assertNotEmpty( $supportedFormats );
		$this->assertContainsOnly( 'string', $supportedFormats );
	}

	public function testGetSupportedFormats_containsDefaultFormat() {
		$supportedFormats = $this->getCodec()->getSupportedFormats();
		$this->assertContains( $this->getCodec()->getDefaultFormat(), $supportedFormats );
	}

}
