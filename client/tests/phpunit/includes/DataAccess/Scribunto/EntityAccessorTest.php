<?php

namespace Wikibase\Client\Tests\DataAccess\Scribunto;

use InvalidArgumentException;
use DataValues\Serializers\DataValueSerializer;
use DataValues\StringValue;
use Language;
use ReflectionMethod;
use Wikibase\Client\DataAccess\Scribunto\EntityAccessor;
use Wikibase\Client\Usage\EntityUsage;
use Wikibase\Client\Usage\HashUsageAccumulator;
use Wikibase\Client\Usage\UsageAccumulator;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\ReferenceList;
use Wikibase\DataModel\SerializerFactory;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\StaticContentLanguages;
use Wikibase\Lib\Tests\MockRepository;

/**
 * @covers Wikibase\Client\DataAccess\Scribunto\EntityAccessor
 *
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseScribunto
 *
 * @license GPL-2.0+
 * @author Marius Hoch < hoo@online.de >
 */
class EntityAccessorTest extends \PHPUnit_Framework_TestCase {

	public function testConstructor() {
		$entityAccessor = $this->getEntityAccessor();

		$this->assertInstanceOf( EntityAccessor::class, $entityAccessor );
	}

	private function getEntityAccessor(
		EntityLookup $entityLookup = null,
		UsageAccumulator $usageAccumulator = null,
		$langCode = 'en'
	) {
		$language = new Language( $langCode );
		$serializerFactory = new SerializerFactory(
			new DataValueSerializer(),
			SerializerFactory::OPTION_SERIALIZE_MAIN_SNAKS_WITHOUT_HASH +
			SerializerFactory::OPTION_SERIALIZE_REFERENCE_SNAKS_WITHOUT_HASH
		);
		$entitySerializer = $serializerFactory->newItemSerializer();
		$statementSerializer = $serializerFactory->newStatementListSerializer();

		$propertyDataTypeLookup = $this->getMock( PropertyDataTypeLookup::class );
		$propertyDataTypeLookup->expects( $this->any() )
			->method( 'getDataTypeIdForProperty' )
			->will( $this->returnValue( 'structured-cat' ) );

		$fallbackChainFactory = new LanguageFallbackChainFactory();
		$fallbackChain = $fallbackChainFactory->newFromLanguage( $language,
			LanguageFallbackChainFactory::FALLBACK_SELF | LanguageFallbackChainFactory::FALLBACK_VARIANTS
		);

		return new EntityAccessor(
			new ItemIdParser(),
			$entityLookup ?: new MockRepository(),
			$usageAccumulator ?: new HashUsageAccumulator(),
			$entitySerializer,
			$statementSerializer,
			$propertyDataTypeLookup,
			$fallbackChain,
			$language,
			new StaticContentLanguages( [ 'de', $langCode, 'es', 'ja' ] )
		);
	}

	private function hasUsage( $actualUsages, EntityId $entityId, $aspect, $modifier = null ) {
		$usage = new EntityUsage( $entityId, $aspect, $modifier );
		$key = $usage->getIdentityString();
		return isset( $actualUsages[$key] );
	}

	/**
	 * @dataProvider getEntityProvider
	 */
	public function testGetEntity( array $expected, Item $item, EntityLookup $entityLookup ) {
		$prefixedId = $item->getId()->getSerialization();
		$entityAccessor = $this->getEntityAccessor( $entityLookup );

		$entityArr = $entityAccessor->getEntity( $prefixedId );
		$actual = is_array( $entityArr ) ? $entityArr : [];
		$this->assertSameSize( $expected, $actual );

		foreach ( $expected as $expectedKey ) {
			$this->assertArrayHasKey( $expectedKey, $actual );
		}
	}

	public function testGetEntity_usage() {
		$item = $this->getItem();
		$itemId = $item->getId();

		$entityLookup = new MockRepository();

		$usages = new HashUsageAccumulator();
		$entityAccessor = $this->getEntityAccessor( $entityLookup, $usages );

		$entityAccessor->getEntity( $itemId->getSerialization() );
		$this->assertTrue(
			$this->hasUsage( $usages->getUsages(), $item->getId(), EntityUsage::ALL_USAGE ), 'all usage'
		);
	}

	public function getEntityProvider() {
		$item = $this->getItem();

		$entityLookup = new MockRepository();
		$entityLookup->putEntity( $item );

		$item2 = new Item( new ItemId( 'Q9999' ) );

		return [
			[ [ 'id', 'type', 'descriptions', 'labels', 'sitelinks', 'schemaVersion' ], $item, $entityLookup ],
			[ [], $item2, $entityLookup ]
		];
	}

	protected function getItem() {
		$item = new Item( new ItemId( 'Q666' ) );
		$item->setLabel( 'en', 'Beer' );
		$item->setDescription( 'en', 'yummy beverage' );
		$item->getSiteLinkList()->addNewSiteLink( 'enwiki', 'Beer' );
		$item->getSiteLinkList()->addNewSiteLink( 'dewiki', 'Bier' );

		return $item;
	}

	/**
	 * @dataProvider provideZeroIndexedArray
	 */
	public function testZeroIndexArray( array $array, array $expected ) {
		$renumber = new ReflectionMethod( EntityAccessor::class, 'renumber' );
		$renumber->setAccessible( true );
		$renumber->invokeArgs( $this->getEntityAccessor(), [ &$array ] );

		$this->assertSame( $expected, $array );
	}

	public function provideZeroIndexedArray() {
		return [
			[
				[ 'nyancat' => [ 0 => 'nyan', 1 => 'cat' ] ],
				[ 'nyancat' => [ 1 => 'nyan', 2 => 'cat' ] ]
			],
			[
				[ [ 'a', 'b' ] ],
				[ [ 1 => 'a', 2 => 'b' ] ]
			],
			[
				// Nested arrays
				[ [ 'a', 'b', [ 'c', 'd' ] ] ],
				[ [ 1 => 'a', 2 => 'b', 3 => [ 1 => 'c', 2 => 'd' ] ] ]
			],
			[
				// Already 1-based
				[ [ 1 => 'a', 4 => 'c', 3 => 'b' ] ],
				[ [ 1 => 'a', 4 => 'c', 3 => 'b' ] ]
			],
			[
				// Associative array
				[ [ 'foo' => 'bar', 1337 => 'Wikidata' ] ],
				[ [ 'foo' => 'bar', 1337 => 'Wikidata' ] ]
			],
		];
	}

	public function testFullEntityGetEntityResponse() {
		$item = new Item( new ItemId( 'Q123098' ) );

		//Basic
		$item->setLabel( 'de', 'foo-de' );
		$item->setLabel( 'qu', 'foo-qu' );
		$item->setAliases( 'en', [ 'bar', 'baz' ] );
		$item->setAliases( 'de-formal', [ 'bar', 'baz' ] );
		$item->setDescription( 'en', 'en-desc' );
		$item->setDescription( 'pt', 'ptDesc' );
		$item->getSiteLinkList()->addNewSiteLink( 'enwiki', 'Berlin', [ new ItemId( 'Q333' ) ] );
		$item->getSiteLinkList()->addNewSiteLink( 'zh_classicalwiki', 'User:Addshore', [] );

		$snak = new PropertyValueSnak( 65, new StringValue( 'snakStringValue' ) );

		$qualifiers = new SnakList();
		$qualifiers->addSnak( new PropertyValueSnak( 65, new StringValue( 'string!' ) ) );
		$qualifiers->addSnak( new PropertySomeValueSnak( 65 ) );

		$references = new ReferenceList();
		$references->addNewReference( [
			new PropertySomeValueSnak( 65 ),
			new PropertySomeValueSnak( 68 )
		] );

		$guid = 'imaguid';
		$item->getStatements()->addNewStatement( $snak, $qualifiers, $references, $guid );

		$entityLookup = new MockRepository();
		$entityLookup->putEntity( $item );

		$entityAccessor = $this->getEntityAccessor( $entityLookup, null, 'qug' );

		$expected = [
			'id' => 'Q123098',
			'type' => 'item',
			'labels' => [
				'de' => [
					'language' => 'de',
					'value' => 'foo-de',
				],
			],
			'descriptions' => [
				'en' => [
					'language' => 'en',
					'value' => 'en-desc',
				],
			],
			'aliases' => [
				'en' => [
					1 => [
						'language' => 'en',
						'value' => 'bar',
					],
					2 => [
						'language' => 'en',
						'value' => 'baz',
					],
				],
			],
			'claims' => [
				'P65' => [
					1 => [
						'id' => 'imaguid',
						'type' => 'statement',
						'mainsnak' => [
							'snaktype' => 'value',
							'property' => 'P65',
							'datatype' => 'structured-cat',
							'datavalue' => [
								'value' => 'snakStringValue',
								'type' => 'string',
							],
						],
						'qualifiers' => [
							'P65' => [
								1 => [
									'hash' => '3ea0f5404dd4e631780b3386d17a15a583e499a6',
									'snaktype' => 'value',
									'property' => 'P65',
									'datavalue' => [
										'value' => 'string!',
										'type' => 'string',
									],
									'datatype' => 'structured-cat',
								],
								2 => [
									'hash' => 'aa9a5f05e20d7fa5cda7d98371e44c0bdd5de35e',
									'snaktype' => 'somevalue',
									'property' => 'P65',
									'datatype' => 'structured-cat',
								],
							],
						],
						'rank' => 'normal',
						'qualifiers-order' => [
							1 => 'P65'
						],
						'references' => [
							1 => [
								'hash' => '8445204eb74e636cb53687e2f947c268d5186075',
								'snaks' => [
									'P65' => [
										1 => [
											'snaktype' => 'somevalue',
											'property' => 'P65',
											'datatype' => 'structured-cat',
										]
									],
									'P68' => [
										1 => [
											'snaktype' => 'somevalue',
											'property' => 'P68',
											'datatype' => 'structured-cat',
										]
									],
								],
								'snaks-order' => [
									1 => 'P65',
									2 => 'P68'
								],
							],
						],
					],
				],
			],
			'sitelinks' => [
				'enwiki' => [
					'site' => 'enwiki',
					'title' => 'Berlin',
					'badges' => [ 1 => 'Q333' ]
				],
				'zh_classicalwiki' => [
					'site' => 'zh_classicalwiki',
					'title' => 'User:Addshore',
					'badges' => []
				],
			],
			'schemaVersion' => 2,
		];

		$this->assertEquals( $expected, $entityAccessor->getEntity( 'Q123098' ) );
	}

	/**
	 * @dataProvider getEntityStatementProvider
	 */
	public function testGetEntityStatement( array $expected ) {
		$qid = 'Q123099';
		$pid = 'P65';
		$item = new Item( new ItemId( $qid ) );
		$snak = new PropertyValueSnak( new PropertyId( 'P65' ), new StringValue( 'snakStringValue' ) );

		$qualifiers = new SnakList();
		$qualifiers->addSnak( new PropertyValueSnak( new PropertyId( $pid ), new StringValue( 'string!' ) ) );
		$qualifiers->addSnak( new PropertySomeValueSnak( new PropertyId( $pid ) ) );

		$references = new ReferenceList();
		$references->addNewReference( [
			new PropertySomeValueSnak( new PropertyId( 'P65' ) ),
			new PropertySomeValueSnak( new PropertyId( 'P68' ) )
		] );

		$guid = 'imaguid';
		$item->getStatements()->addNewStatement( $snak, $qualifiers, $references, $guid );

		$entityLookup = new MockRepository();
		$entityLookup->putEntity( $item );

		$usages = new HashUsageAccumulator();
		$entityAccessor = $this->getEntityAccessor( $entityLookup, $usages );
		$actual = $entityAccessor->getEntityStatement( 'Q123099', $pid );

		$this->assertSameSize( $expected, $actual );
		$this->assertEquals( $expected, $actual );

		$this->assertEquals( [ 'Q123099#C.P65' ], array_keys( $usages->getUsages() ) );
		$this->assertFalse(
			$this->hasUsage( $usages->getUsages(), $item->getId(), EntityUsage::ALL_USAGE ), 'all usage'
		);
	}

	public function getEntityStatementProvider() {
		return [
			[
				[
					'P65' => [
						1 => [
							'id' => 'imaguid',
							'type' => 'statement',
							'mainsnak' => [
								'snaktype' => 'value',
								'property' => 'P65',
								'datatype' => 'structured-cat',
								'datavalue' => [
									'value' => 'snakStringValue',
									'type' => 'string',
								],
							],
							'qualifiers' => [
								'P65' => [
									1 => [
										'hash' => '3ea0f5404dd4e631780b3386d17a15a583e499a6',
										'snaktype' => 'value',
										'property' => 'P65',
										'datavalue' => [
											'value' => 'string!',
											'type' => 'string',
										],
										'datatype' => 'structured-cat',
									],
									2 => [
										'hash' => 'aa9a5f05e20d7fa5cda7d98371e44c0bdd5de35e',
										'snaktype' => 'somevalue',
										'property' => 'P65',
										'datatype' => 'structured-cat',
									],
								],
							],
							'rank' => 'normal',
							'qualifiers-order' => [
								1 => 'P65'
							],
							'references' => [
								1 => [
									'hash' => '8445204eb74e636cb53687e2f947c268d5186075',
									'snaks' => [
										'P65' => [
											1 => [
												'snaktype' => 'somevalue',
												'property' => 'P65',
												'datatype' => 'structured-cat',
											]
										],
										'P68' => [
											1 => [
												'snaktype' => 'somevalue',
												'property' => 'P68',
												'datatype' => 'structured-cat',
											]
										],
									],
									'snaks-order' => [
										1 => 'P65',
										2 => 'P68'
									],
								],
							],
						],
					]
				]
			]
		];
	}

	public function testGetEntityStatementBadProperty() {
		$entityLookup = new MockRepository();
		$entityAccessor = $this->getEntityAccessor( $entityLookup );
		$this->setExpectedException( InvalidArgumentException::class );
		$actual = $entityAccessor->getEntityStatement( 'Q123099', 'ffsdfs' );
	}

	public function testGetEntityStatementMissingStatement() {
		$entityLookup = new MockRepository();
		$item = new Item( new ItemId( 'Q123099' ) );
		$entityLookup = new MockRepository();
		$entityLookup->putEntity( $item );
		$entityAccessor = $this->getEntityAccessor( $entityLookup );
		$actual = $entityAccessor->getEntityStatement( 'Q123099', 'P13' );
		$this->assertEquals( [], $actual );
	}

}
