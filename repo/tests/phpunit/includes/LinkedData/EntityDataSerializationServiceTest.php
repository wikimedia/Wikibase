<?php

namespace Wikibase\Test;

use SiteList;
use Title;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\EntityRevision;
use Wikibase\Lib\Serializers\SerializationOptions;
use Wikibase\Lib\Serializers\SerializerFactory;
use Wikibase\Repo\LinkedData\EntityDataSerializationService;

/**
 * @covers Wikibase\Repo\LinkedData\EntityDataSerializationService
 *
 * @group Wikibase
 * @group WikibaseEntityData
 * @group WikibaseRepo
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class EntityDataSerializationServiceTest extends \MediaWikiTestCase {

	const URI_BASE = 'http://acme.test/';
	const URI_DATA = 'http://data.acme.test/';

	private function newService() {
		$entityLookup = new MockRepository();

		$dataTypeLookup = $this->getMock( 'Wikibase\DataModel\Entity\PropertyDataTypeLookup' );
		$dataTypeLookup->expects( $this->any() )
			->method( 'getDataTypeIdForProperty' )
			->will( $this->returnValue( 'string' ) );

		$titleLookup = $this->getMock( 'Wikibase\Lib\Store\EntityTitleLookup' );
		$titleLookup->expects( $this->any() )
			->method( 'getTitleForId' )
			->will( $this->returnCallback( function( EntityId $id ) {
				return Title::newFromText( $id->getEntityType() . ':' . $id->getSerialization() );
			} ) );
		$dataTypeLookup = $this->getMock( 'Wikibase\DataModel\Entity\PropertyDataTypeLookup' );
		$dataTypeLookup->expects( $this->any() )
			->method( 'getDataTypeIdForProperty' )
			->will( $this->returnValue( 'string' ) );

		$serializerOptions = new SerializationOptions();
		$serializerFactory = new SerializerFactory( $serializerOptions, $dataTypeLookup );

		$service = new EntityDataSerializationService(
			self::URI_BASE,
			self::URI_DATA,
			$entityLookup,
			$titleLookup,
			$serializerFactory,
			$dataTypeLookup,
			new SiteList()
		);

		$service->setFormatWhiteList(
			array(
				// using the API
				'json', // default
				'php',

				// using RdfWriter
				'rdfxml',
				'n3',
				'turtle',
				'ntriples',
			)
		);

		return $service;
	}

	public function provideGetSerializedData() {
		$cases = EntityDataTestProvider::provideGetSerializedData();

		return $cases;
	}

	/**
	 * @dataProvider provideGetSerializedData
	 */
	public function testGetSerializedData(
		$format,
		EntityRevision $entityRev,
		$expectedDataRegex,
		$expectedMimeType
	) {
		$service = $this->newService();
		list( $data, $mimeType ) = $service->getSerializedData( $format, $entityRev );

		$this->assertEquals( $expectedMimeType, $mimeType );
		$this->assertRegExp( $expectedDataRegex, $data, "outpout" );
	}

	private static $apiMimeTypes = array(
		'application/vnd.php.serialized',
		'application/json',
	);

	private static $apiExtensions = array(
		'php',
		'json',
	);

	private static $apiFormats = array(
		'php',
		'json',
	);

	private static $rdfMimeTypes = array(
		'application/rdf+xml',
		'text/n3',
		'text/rdf+n3',
		'text/turtle',
		'application/x-turtle',
		'text/n-triples',
		'application/n-triples',
	);

	private static $rdfExtensions = array(
		'rdf',
		'n3',
		'ttl',
		'nt'
	);

	private static $rdfFormats = array(
		'rdfxml',
		'n3',
		'turtle',
		'ntriples'
	);

	private static $badMimeTypes = array(
		'text/html',
		'text/text',
		// 'text/plain', // ntriples presents as text/plain!
	);

	private static $badExtensions = array(
		'html',
		'text',
		'txt',
	);

	private static $badFormats = array(
		'html',
		'text',
	);

	private static $formatMappings = array(
		'json' => 'json', // should be api json
		'application/json' => 'json', // should be api json
		'application/rdf+xml' => 'rdfxml', // should be rdfxml
		'text/n-triples' => 'ntriples', // should be ntriples
		'text/plain' => 'ntriples', // should be ntriples
		'ttl' => 'turtle', // should be turtle
	);

	public function testGetSupportedMimeTypes() {
		$service = $this->newService();

		$types = $service->getSupportedMimeTypes();

		foreach ( self::$apiMimeTypes as $type ) {
			$this->assertTrue( in_array( $type, $types), $type, "api mime type $type" );
		}

		foreach ( self::$rdfMimeTypes as $type ) {
			$this->assertTrue( in_array( $type, $types), $type, "rdf mime type $type" );
		}

		foreach ( self::$badMimeTypes as $type ) {
			$this->assertFalse( in_array( $type, $types), $type, "bad mime type $type" );
		}
	}

	public function testGetSupportedExtensions() {
		$service = $this->newService();

		$types = $service->getSupportedExtensions();

		foreach ( self::$apiExtensions as $type ) {
			$this->assertTrue( in_array( $type, $types), $type, "api extension $type" );
		}

		foreach ( self::$rdfExtensions as $type ) {
			$this->assertTrue( in_array( $type, $types), $type, "rdf extension $type" );
		}

		foreach ( self::$badExtensions as $type ) {
			$this->assertFalse( in_array( $type, $types), $type, "bad extension $type" );
		}
	}

	public function testGetSupportedFormats() {
		$service = $this->newService();

		$types = $service->getSupportedFormats();

		foreach ( self::$apiFormats as $type ) {
			$this->assertTrue( in_array( $type, $types), $type, "api format $type" );
		}

		foreach ( self::$rdfFormats as $type ) {
			$this->assertTrue( in_array( $type, $types), $type, "rdf format $type" );
		}

		foreach ( self::$badFormats as $type ) {
			$this->assertFalse( in_array( $type, $types), $type, "bad format $type" );
		}
	}

	public function testGetFormatName() {
		$service = $this->newService();

		$types = $service->getSupportedMimeTypes();

		foreach ( $types as $type ) {
			$format = $service->getFormatName( $type );
			$this->assertNotNull( $format, $type );
		}

		$types = $service->getSupportedExtensions();

		foreach ( $types as $type ) {
			$format = $service->getFormatName( $type );
			$this->assertNotNull( $format, $type );
		}

		$types = $service->getSupportedFormats();

		foreach ( $types as $type ) {
			$format = $service->getFormatName( $type );
			$this->assertNotNull( $format, $type );
		}

		foreach ( self::$formatMappings as $type => $expectedName ) {
			$name = $service->getFormatName( $type );
			$this->assertEquals( $expectedName, $name, $type );
		}
	}

	public function testGetExtension() {
		$service = $this->newService();

		$extensions = $service->getSupportedExtensions();
		foreach ( $extensions as $expected ) {
			$format = $service->getFormatName( $expected );
			$actual = $service->getExtension( $format );

			$this->assertInternalType( 'string', $actual, $expected );
		}

		foreach ( self::$badFormats as $format ) {
			$actual = $service->getExtension( $format );

			$this->assertNull( $actual, $format );
		}
	}

	public function testGetMimeType() {
		$service = $this->newService();

		$extensions = $service->getSupportedMimeTypes();
		foreach ( $extensions as $expected ) {
			$format = $service->getFormatName( $expected );
			$actual = $service->getMimeType( $format );

			$this->assertInternalType( 'string', $actual, $expected );
		}

		foreach ( self::$badFormats as $format ) {
			$actual = $service->getMimeType( $format );

			$this->assertNull( $actual, $format );
		}
	}
}
