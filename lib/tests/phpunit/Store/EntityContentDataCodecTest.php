<?php

namespace Wikibase\Lib\Tests\Store;

use DataValues\Deserializers\DataValueDeserializer;
use DataValues\Serializers\DataValueSerializer;
use DataValues\StringValue;
use MediaWiki\Logger\LoggerFactory;
use MediaWikiTestCase;
use MWContentSerializationException;
use Psr\Log\LoggerInterface;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\SerializerFactory;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\InternalSerialization\DeserializerFactory;
use Wikibase\Lib\Store\EntityContentDataCodec;
use Wikibase\DataModel\Term\Fingerprint;
use Psr\Log\Test\TestLogger;

/**
 * @covers \Wikibase\Lib\Store\EntityContentDataCodec
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class EntityContentDataCodecTest extends MediaWikiTestCase {

	private function getCodec( $maxBlobSize = 0, LoggerInterface $logger = null ) {
	    if ($logger === null ) {
	        $logger = new TestLogger();
        }
		$idParser = new BasicEntityIdParser();
		$serializerFactory = new SerializerFactory( new DataValueSerializer() );
		$deserializerFactory = new DeserializerFactory( new DataValueDeserializer(), $idParser );
		return new EntityContentDataCodec(
			$idParser,
			$serializerFactory->newEntitySerializer(),
			$deserializerFactory->newEntityDeserializer(),
            $logger,
			$maxBlobSize
		);
	}

	public function entityIdProvider() {
		$p1 = new PropertyId( 'P1' );
		$q11 = new ItemId( 'Q11' );

		return [
			'PropertyId' => [ '{ "entity": "P1", "datatype": "string" }', $p1 ],
			'new style' => [ '{ "entity": "Q11" }', $q11 ],
			'old style' => [ '{ "entity": ["item", 11] }', $q11 ],
		];
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

		return [
			'Property' => [ Property::newFromType( 'string' ), null ],

			'empty' => [ $empty, null ],
			'empty json' => [ $empty, CONTENT_FORMAT_JSON ],

			'simple' => [ $simple, null ],
			'simple json' => [ $simple, CONTENT_FORMAT_JSON ],
		];
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
        $logger = new TestLogger();
	    $entity = new Item( new ItemId( 'Q1' ) );

		$this->getCodec( 6, $logger)->encodeEntity( $entity, CONTENT_FORMAT_JSON );
		$this->assertTrue($logger->hasWarning( 'Warning: entity content too big. Entity: Q1' ));
	}

	public function redirectProvider() {
		$q6 = new ItemId( 'Q6' );
		$q8 = new ItemId( 'Q8' );

		$redirect = new EntityRedirect( $q6, $q8 );

		return [
			'redirect' => [ $redirect, null ],
			'empty json' => [ $redirect, CONTENT_FORMAT_JSON ],
		];
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
