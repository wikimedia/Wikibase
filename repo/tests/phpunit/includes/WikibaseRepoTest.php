<?php

namespace Wikibase\Repo\Tests;

use DataTypes\DataTypeFactory;
use DataValues\DataValue;
use DataValues\DataValueFactory;
use DataValues\Geo\Values\GlobeCoordinateValue;
use DataValues\Geo\Values\LatLongValue;
use DataValues\MonolingualTextValue;
use DataValues\QuantityValue;
use DataValues\StringValue;
use DataValues\TimeValue;
use DataValues\UnboundedQuantityValue;
use DataValues\UnknownValue;
use Deserializers\Deserializer;
use Language;
use MediaWikiTestCase;
use RequestContext;
use Serializers\Serializer;
use User;
use Wikibase\ChangeOp\ChangeOpFactoryProvider;
use Wikibase\DataModel\DeserializerFactory;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\TermLookup;
use Wikibase\DataModel\Services\Statement\StatementGuidParser;
use Wikibase\DataModel\Services\Statement\StatementGuidValidator;
use Wikibase\DataModel\Services\Term\TermBuffer;
use Wikibase\EditEntityFactory;
use Wikibase\EntityFactory;
use Wikibase\InternalSerialization\DeserializerFactory as InternalDeserializerFactory;
use Wikibase\InternalSerialization\SerializerFactory;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\Changes\EntityChangeFactory;
use Wikibase\Lib\ContentLanguages;
use Wikibase\Lib\DataTypeDefinitions;
use Wikibase\Lib\EntityTypeDefinitions;
use Wikibase\Lib\Interactors\TermIndexSearchInteractor;
use Wikibase\Lib\OutputFormatSnakFormatterFactory;
use Wikibase\Lib\OutputFormatValueFormatterFactory;
use Wikibase\Lib\Store\EntityContentDataCodec;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Lib\Store\EntityStoreWatcher;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookupFactory;
use Wikibase\Lib\WikibaseSnakFormatterBuilders;
use Wikibase\Lib\WikibaseValueFormatterBuilders;
use Wikibase\PropertyInfoBuilder;
use Wikibase\Rdf\RdfVocabulary;
use Wikibase\Rdf\ValueSnakRdfBuilderFactory;
use Wikibase\Repo\Api\ApiHelperFactory;
use Wikibase\Repo\BuilderBasedDataTypeValidatorFactory;
use Wikibase\Repo\CachingCommonsMediaFileNameLookup;
use Wikibase\Repo\Content\EntityContentFactory;
use Wikibase\Repo\Content\EntityHandler;
use Wikibase\Repo\EntityIdHtmlLinkFormatterFactory;
use Wikibase\Repo\Interactors\ItemMergeInteractor;
use Wikibase\Repo\Interactors\RedirectCreationInteractor;
use Wikibase\Repo\Localizer\ExceptionLocalizer;
use Wikibase\Repo\Notifications\ChangeNotifier;
use Wikibase\Repo\ParserOutput\EntityParserOutputGeneratorFactory;
use Wikibase\Repo\SnakFactory;
use Wikibase\Repo\ValidatorBuilders;
use Wikibase\Repo\Validators\CompositeValidator;
use Wikibase\Repo\ValueParserFactory;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\SettingsArray;
use Wikibase\Store\EntityIdLookup;
use Wikibase\Store;
use Wikibase\StringNormalizer;
use Wikibase\SummaryFormatter;

/**
 * @covers Wikibase\Repo\WikibaseRepo
 *
 * @group Wikibase
 * @group Database
 *
 * @license GPL-2.0+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 */
class WikibaseRepoTest extends MediaWikiTestCase {

	public function testGetDefaultValidatorBuilders() {
		$first = $this->getWikibaseRepo()->getDefaultValidatorBuilders();
		$this->assertInstanceOf( ValidatorBuilders::class, $first );

		$second = $this->getWikibaseRepo()->getDefaultValidatorBuilders();
		$this->assertSame( $first, $second );
	}

	/**
	 * @dataProvider urlSchemesProvider
	 */
	public function testDefaultUrlValidators( $input, $expected ) {
		$validatorBuilders = $this->getWikibaseRepo()->getDefaultValidatorBuilders();
		$urlValidator = new CompositeValidator( $validatorBuilders->buildUrlValidators() );
		$result = $urlValidator->validate( new StringValue( $input ) );
		$this->assertSame( $expected, $result->isValid() );
	}

	public function urlSchemesProvider() {
		return [
			[ 'bzr://x', true ],
			[ 'cvs://x', true ],
			[ 'ftp://x', true ],
			[ 'git://x', true ],
			[ 'http://x', true ],
			[ 'https://x', true ],
			[ 'irc://x', true ],
			[ 'mailto:x@x', true ],
			[ 'ssh://x', true ],
			[ 'svn://x', true ],

			// Supported by UrlSchemeValidators, but not enabled by default.
			[ 'ftps://x', false ],
			[ 'gopher://x', false ],
			[ 'ircs://x', false ],
			[ 'mms://x', false ],
			[ 'nntp://x', false ],
			[ 'redis://x', false ],
			[ 'sftp://x', false ],
			[ 'telnet://x', false ],
			[ 'worldwind://x', false ],
		];
	}

	public function testGetDefaultValueFormatterBuilders() {
		$first = $this->getWikibaseRepo()->getDefaultValueFormatterBuilders();
		$this->assertInstanceOf( WikibaseValueFormatterBuilders::class, $first );

		$second = $this->getWikibaseRepo()->getDefaultValueFormatterBuilders();
		$this->assertSame( $first, $second );
	}

	public function testGetDefaultSnakFormatterBuilders() {
		$first = $this->getWikibaseRepo()->getDefaultSnakFormatterBuilders();
		$this->assertInstanceOf( WikibaseSnakFormatterBuilders::class, $first );

		$second = $this->getWikibaseRepo()->getDefaultSnakFormatterBuilders();
		$this->assertSame( $first, $second );
	}

	public function testGetDataTypeFactoryReturnType() {
		$returnValue = $this->getWikibaseRepo()->getDataTypeFactory();
		$this->assertInstanceOf( DataTypeFactory::class, $returnValue );
	}

	public function testGetValueParserFactoryReturnType() {
		$returnValue = $this->getWikibaseRepo()->getValueParserFactory();
		$this->assertInstanceOf( ValueParserFactory::class, $returnValue );
	}

	public function testGetDataValueFactoryReturnType() {
		$returnValue = $this->getWikibaseRepo()->getDataValueFactory();
		$this->assertInstanceOf( DataValueFactory::class, $returnValue );
	}

	public function testGetEntityContentFactoryReturnType() {
		$returnValue = $this->getWikibaseRepo()->getEntityContentFactory();
		$this->assertInstanceOf( EntityContentFactory::class, $returnValue );
	}

	public function testGetEntityStoreWatcherReturnType() {
		$returnValue = $this->getWikibaseRepo()->getEntityStoreWatcher();
		$this->assertInstanceOf( EntityStoreWatcher::class, $returnValue );
	}

	public function testGetEntityTitleLookupReturnType() {
		$returnValue = $this->getWikibaseRepo()->getEntityTitleLookup();
		$this->assertInstanceOf( EntityTitleLookup::class, $returnValue );
	}

	public function testGetEntityIdLookupReturnType() {
		$returnValue = $this->getWikibaseRepo()->getEntityIdLookup();
		$this->assertInstanceOf( EntityIdLookup::class, $returnValue );
	}

	public function testGetEntityRevisionLookupReturnType() {
		$returnValue = $this->getWikibaseRepo()->getEntityRevisionLookup();
		$this->assertInstanceOf( EntityRevisionLookup::class, $returnValue );
	}

	public function testNewRedirectCreationInteractorReturnType() {
		$user = $this->getMockBuilder( User::class )
			->disableOriginalConstructor()
			->getMock();
		$context = new RequestContext();
		$returnValue = $this->getWikibaseRepo()->newRedirectCreationInteractor( $user, $context );
		$this->assertInstanceOf( RedirectCreationInteractor::class, $returnValue );
	}

	public function testNewTermSearchInteractorReturnType() {
		$returnValue = $this->getWikibaseRepo()->newTermSearchInteractor( '' );
		$this->assertInstanceOf( TermIndexSearchInteractor::class, $returnValue );
	}

	public function testGetEntityStoreReturnType() {
		$returnValue = $this->getWikibaseRepo()->getEntityStore();
		$this->assertInstanceOf( EntityStore::class, $returnValue );
	}

	public function testGetPropertyDataTypeLookupReturnType() {
		$returnValue = $this->getWikibaseRepo()->getPropertyDataTypeLookup();
		$this->assertInstanceOf( PropertyDataTypeLookup::class, $returnValue );
	}

	public function testGetStringNormalizerReturnType() {
		$returnValue = $this->getWikibaseRepo()->getStringNormalizer();
		$this->assertInstanceOf( StringNormalizer::class, $returnValue );
	}

	public function testGetEntityLookupReturnType() {
		$returnValue = $this->getWikibaseRepo()->getEntityLookup();
		$this->assertInstanceOf( EntityLookup::class, $returnValue );
	}

	public function testGetSnakFactoryReturnType() {
		$returnValue = $this->getWikibaseRepo()->getSnakFactory();
		$this->assertInstanceOf( SnakFactory::class, $returnValue );
	}

	public function testGetEntityIdParserReturnType() {
		$returnValue = $this->getWikibaseRepo()->getEntityIdParser();
		$this->assertInstanceOf( EntityIdParser::class, $returnValue );
	}

	public function testGetStatementGuidParser() {
		$returnValue = $this->getWikibaseRepo()->getStatementGuidParser();
		$this->assertInstanceOf( StatementGuidParser::class, $returnValue );
	}

	public function testGetLanguageFallbackChainFactory() {
		$returnValue = $this->getWikibaseRepo()->getLanguageFallbackChainFactory();
		$this->assertInstanceOf( LanguageFallbackChainFactory::class, $returnValue );
	}

	public function testGetLanguageFallbackLabelDescriptionLookupFactory() {
		$returnValue = $this->getWikibaseRepo()->getLanguageFallbackLabelDescriptionLookupFactory();
		$this->assertInstanceOf( LanguageFallbackLabelDescriptionLookupFactory::class, $returnValue );
	}

	public function testGetStatementGuidValidator() {
		$returnValue = $this->getWikibaseRepo()->getStatementGuidValidator();
		$this->assertInstanceOf( StatementGuidValidator::class, $returnValue );
	}

	public function testGetSettingsReturnType() {
		$returnValue = $this->getWikibaseRepo()->getSettings();
		$this->assertInstanceOf( SettingsArray::class, $returnValue );
	}

	public function testGetStoreReturnType() {
		$returnValue = $this->getWikibaseRepo()->getStore();
		$this->assertInstanceOf( Store::class, $returnValue );
	}

	public function testGetSnakFormatterFactory() {
		$returnValue = $this->getWikibaseRepo()->getSnakFormatterFactory();
		$this->assertInstanceOf( OutputFormatSnakFormatterFactory::class, $returnValue );
	}

	public function testGetValueFormatterFactory() {
		$returnValue = $this->getWikibaseRepo()->getValueFormatterFactory();
		$this->assertInstanceOf( OutputFormatValueFormatterFactory::class, $returnValue );
	}

	public function testGetSummaryFormatter() {
		$returnValue = $this->getWikibaseRepo()->getSummaryFormatter();
		$this->assertInstanceOf( SummaryFormatter::class, $returnValue );
	}

	public function testGetChangeOpFactory() {
		$returnValue = $this->getWikibaseRepo()->getChangeOpFactoryProvider();
		$this->assertInstanceOf( ChangeOpFactoryProvider::class, $returnValue );
	}

	public function testGetChangeNotifier() {
		$factory = $this->getWikibaseRepo()->getChangeNotifier();
		$this->assertInstanceOf( ChangeNotifier::class, $factory );
	}

	public function testGetContentModelMappings() {
		$array = $this->getWikibaseRepo()->getContentModelMappings();
		$this->assertInternalType( 'array', $array );
		$this->assertContainsOnly( 'string', $array );
	}

	public function testGetEntityFactory() {
		$entityFactory = $this->getWikibaseRepo()->getEntityFactory();
		$this->assertInstanceOf( EntityFactory::class, $entityFactory );
	}

	public function testGetLocalEntityTypes() {
		$wikibaseRepo = $this->getWikibaseRepo();
		$wikibaseRepo->getSettings()->setSetting(
			'entityNamespaces',
			[
				'foo' => 100,
				'bar' => 102
			]
		);

		$localEntityTypes = $wikibaseRepo->getLocalEntityTypes();
		$this->assertContains( 'foo', $localEntityTypes );
		$this->assertContains( 'bar', $localEntityTypes );
	}

	public function testGetEnabledEntityTypes() {
		$wikibaseRepo = $this->getWikibaseRepo();
		$wikibaseRepo->getSettings()->setSetting(
			'entityNamespaces',
			[ 'foo' => 100, 'bar' => 102 ]
		);
		$wikibaseRepo->getSettings()->setSetting(
			'foreignRepositories',
			[
				'repo1' => [ 'supportedEntityTypes' => [ 'foo', 'baz' ] ],
				'repo2' => [ 'supportedEntityTypes' => [ 'foobar' ] ],
			]
		);
		$enabled = $wikibaseRepo->getEnabledEntityTypes();
		$this->assertContains( 'foo', $enabled );
		$this->assertContains( 'bar', $enabled );
		$this->assertContains( 'baz', $enabled );
		$this->assertContains( 'foobar', $enabled );
	}

	public function testGetExceptionLocalizer() {
		$localizer = $this->getWikibaseRepo()->getExceptionLocalizer();
		$this->assertInstanceOf( ExceptionLocalizer::class, $localizer );
	}

	public function testGetEntityContentDataCodec() {
		$codec = $this->getWikibaseRepo()->getEntityContentDataCodec();
		$this->assertInstanceOf( EntityContentDataCodec::class, $codec );
	}

	public function testGetInternalFormatDeserializerFactory() {
		$deserializerFactory = $this->getWikibaseRepo()->getInternalFormatDeserializerFactory();
		$this->assertInstanceOf( InternalDeserializerFactory::class, $deserializerFactory );
	}

	public function testGetExternalFormatDeserializerFactory() {
		$deserializerFactory = $this->getWikibaseRepo()->getExternalFormatDeserializerFactory();
		$this->assertInstanceOf( DeserializerFactory::class, $deserializerFactory );
	}

	public function testGetSerializerFactory() {
		$serializerFactory = $this->getWikibaseRepo()->getSerializerFactory();
		$this->assertInstanceOf( SerializerFactory::class, $serializerFactory );
	}

	public function testGetExternalFormatEntityDeserializer() {
		$deserializer = $this->getWikibaseRepo()->getExternalFormatEntityDeserializer();
		$this->assertInstanceOf( Deserializer::class, $deserializer );
	}

	public function testGetInternalFormatEntityDeserializer() {
		$deserializer = $this->getWikibaseRepo()->getInternalFormatEntityDeserializer();
		$this->assertInstanceOf( Deserializer::class, $deserializer );
	}

	public function testGetEntitySerializer() {
		$serializer = $this->getWikibaseRepo()->getEntitySerializer();
		$this->assertInstanceOf( Serializer::class, $serializer );
	}

	public function testGetExternalFormatStatementDeserializer() {
		$deserializer = $this->getWikibaseRepo()->getExternalFormatStatementDeserializer();
		$this->assertInstanceOf( Deserializer::class, $deserializer );
	}

	public function testGetInternalFormatStatementDeserializer() {
		$deserializer = $this->getWikibaseRepo()->getInternalFormatStatementDeserializer();
		$this->assertInstanceOf( Deserializer::class, $deserializer );
	}

	public function testGetStatementSerializer() {
		$serializer = $this->getWikibaseRepo()->getStatementSerializer();
		$this->assertInstanceOf( Serializer::class, $serializer );
	}

	public function testGetEntityChangeFactory() {
		$factory = $this->getWikibaseRepo()->getEntityChangeFactory();
		$this->assertInstanceOf( EntityChangeFactory::class, $factory );
	}

	public function testNewItemHandler() {
		$handler = $this->getWikibaseRepo()->newItemHandler();
		$this->assertInstanceOf( EntityHandler::class, $handler );
	}

	public function testNewPropertyHandler() {
		$handler = $this->getWikibaseRepo()->newPropertyHandler();
		$this->assertInstanceOf( EntityHandler::class, $handler );
	}

	public function testNewItemHandler_noTransform() {
		$wikibaseRepo = $this->getWikibaseRepo();
		$wikibaseRepo->getSettings()->setSetting( 'transformLegacyFormatOnExport', false );

		$handler = $wikibaseRepo->newItemHandler();
		$this->assertNull( $handler->getLegacyExportFormatDetector() );
	}

	public function testNewPropertyHandler_noTransform() {
		$wikibaseRepo = $this->getWikibaseRepo();
		$wikibaseRepo->getSettings()->setSetting( 'transformLegacyFormatOnExport', false );

		$handler = $wikibaseRepo->newPropertyHandler();
		$this->assertNull( $handler->getLegacyExportFormatDetector() );
	}

	public function testNewItemHandler_withTransform() {
		$wikibaseRepo = $this->getWikibaseRepo();
		$wikibaseRepo->getSettings()->setSetting( 'transformLegacyFormatOnExport', true );

		$handler = $wikibaseRepo->newItemHandler();
		$this->assertNotNull( $handler->getLegacyExportFormatDetector() );
	}

	public function testNewPropertyHandler_withTransform() {
		$wikibaseRepo = $this->getWikibaseRepo();
		$wikibaseRepo->getSettings()->setSetting( 'transformLegacyFormatOnExport', true );

		$handler = $wikibaseRepo->newPropertyHandler();
		$this->assertNotNull( $handler->getLegacyExportFormatDetector() );
	}

	/**
	 * @param array[] $entityTypeDefinitions
	 *
	 * @return WikibaseRepo
	 */
	private function getWikibaseRepo( $entityTypeDefinitions = array() ) {
		$settings = new SettingsArray( WikibaseRepo::getDefaultInstance()->getSettings()->getArrayCopy() );
		return new WikibaseRepo(
			$settings,
			new DataTypeDefinitions( array() ),
			new EntityTypeDefinitions( $entityTypeDefinitions )
		);
	}

	public function testGetApiHelperFactory() {
		$factory = $this->getWikibaseRepo()->getApiHelperFactory( new RequestContext() );
		$this->assertInstanceOf( ApiHelperFactory::class, $factory );
	}

	public function testNewEditEntityFactory() {
		$factory = $this->getWikibaseRepo()->newEditEntityFactory( new RequestContext() );
		$this->assertInstanceOf( EditEntityFactory::class, $factory );
	}

	public function testNewEditEntityFactory_withoutContextParam() {
		$factory = $this->getWikibaseRepo()->newEditEntityFactory();
		$this->assertInstanceOf( EditEntityFactory::class, $factory );
	}

	public function testNewItemMergeInteractor() {
		$interactor = $this->getWikibaseRepo()->newItemMergeInteractor( new RequestContext() );
		$this->assertInstanceOf( ItemMergeInteractor::class, $interactor );
	}

	public function testGetTermLookup() {
		$service = $this->getWikibaseRepo()->getTermLookup();
		$this->assertInstanceOf( TermLookup::class, $service );
	}

	public function testGetTermBuffer() {
		$service = $this->getWikibaseRepo()->getTermBuffer();
		$this->assertInstanceOf( TermBuffer::class, $service );
	}

	public function testGetTermBuffer_instance() {
		$repo = $this->getWikibaseRepo();
		$service = $repo->getTermBuffer();
		$this->assertSame( $service, $repo->getTermBuffer(), 'Second call should return same instance' );
		$this->assertSame( $service, $repo->getTermLookup(), 'TermBuffer and TermLookup should be the same object' );
	}

	public function testGetTermsLanguages() {
		$service = $this->getWikibaseRepo()->getTermsLanguages();
		$this->assertInstanceOf( ContentLanguages::class, $service );
	}

	public function testNewPropertyInfoBuilder() {
		$builder = $this->getWikibaseRepo()->newPropertyInfoBuilder();
		$this->assertInstanceOf( PropertyInfoBuilder::class, $builder );
	}

	public function testGetEntityNamespaceLookup() {
		$service = $this->getWikibaseRepo()->getEntityNamespaceLookup();
		$this->assertInstanceOf( EntityNamespaceLookup::class, $service );
	}

	public function testGetEntityIdHtmlLinkFormatterFactory() {
		$service = $this->getWikibaseRepo()->getEntityIdHtmlLinkFormatterFactory();
		$this->assertInstanceOf( EntityIdHtmlLinkFormatterFactory::class, $service );
	}

	public function testGetEntityParserOutputGeneratorFactory() {
		$service = $this->getWikibaseRepo()->getEntityParserOutputGeneratorFactory();
		$this->assertInstanceOf( EntityParserOutputGeneratorFactory::class, $service );
	}

	public function testGetDataTypeValidatorFactory() {
		$service = $this->getWikibaseRepo()->getDataTypeValidatorFactory();
		$this->assertInstanceOf( BuilderBasedDataTypeValidatorFactory::class, $service );
	}

	public function testGetDataTypeDefinitions() {
		$dataTypeDefinitions = $this->getWikibaseRepo()->getDataTypeDefinitions();
		$this->assertInstanceOf( DataTypeDefinitions::class, $dataTypeDefinitions );
	}

	public function testGetValueSnakRdfBuilderFactory() {
		$factory = $this->getWikibaseRepo()->getValueSnakRdfBuilderFactory();
		$this->assertInstanceOf( ValueSnakRdfBuilderFactory::class, $factory );
	}

	public function testGetRdfVocabulary() {
		$factory = $this->getWikibaseRepo()->getRdfVocabulary();
		$this->assertInstanceOf( RdfVocabulary::class, $factory );
	}

	public function testGetCachingCommonsMediaFileNameLookup() {
		$lookup = $this->getWikibaseRepo()->getCachingCommonsMediaFileNameLookup();
		$this->assertInstanceOf( CachingCommonsMediaFileNameLookup::class, $lookup );
	}

	/**
	 * @return DataValueFactory
	 */
	private function getDataValueFactory() {
		return $this->getWikibaseRepo( [
			'item' => [
				'entity-id-pattern' => ItemId::PATTERN,
				'entity-id-builder' => function( $serialization ) {
					return new ItemId( $serialization );
				},
			],
		] )->getDataValueFactory();
	}

	public function dataValueProvider() {
		return [
			'string' => [ new StringValue( 'Test' ) ],
			'unknown' => [ new UnknownValue( [ 'foo' => 'bar' ] ) ],
			'globecoordinate' => [ new GlobeCoordinateValue( new LatLongValue( 2, 3 ), 1 ) ],
			'monolingualtext' => [ new MonolingualTextValue( 'als', 'Test' ) ],
			'unbounded quantity' => [ UnboundedQuantityValue::newFromNumber( 2 ) ],
			'quantity' => [ QuantityValue::newFromNumber( 2 ) ],
			'time' => [ new TimeValue(
				'+1980-10-07T17:33:22Z',
				0,
				0,
				1,
				TimeValue::PRECISION_DAY,
				TimeValue::CALENDAR_GREGORIAN
			) ],
			'wikibase-entityid' => [ new EntityIdValue( new ItemId( 'Q13' ) ) ],
		];
	}

	/**
	 * @dataProvider dataValueProvider
	 */
	public function testDataValueSerializationDeserializationRoundtrip( DataValue $expected ) {
		$service = $this->getDataValueFactory();
		$deserialized = $service->newFromArray( $expected->toArray() );

		$this->assertEquals( $expected, $deserialized );
	}

	public function entityIdValueSerializationProvider() {
		return [
			'legacy' => [ [
				'entity-type' => 'item',
				'numeric-id' => 13,
			] ],
			'intermediate' => [ [
				'entity-type' => 'item',
				'numeric-id' => 13,
				'id' => 'Q13',
			] ],
			'new' => [ [
				'id' => 'Q13',
			] ],
		];
	}

	/**
	 * @dataProvider entityIdValueSerializationProvider
	 */
	public function testEntityIdValueDeserialization( array $serialization ) {
		$service = $this->getDataValueFactory();
		$deserialized = $service->newFromArray( [
			'type' => 'wikibase-entityid',
			'value' => $serialization,
		] );

		$expected = new EntityIdValue( new ItemId( 'Q13' ) );
		$this->assertEquals( $expected, $deserialized );
	}

	public function testGetChangeOpDeserializerCallbacks() {
		$wikibaseRepo = $this->getWikibaseRepo(
			[
				'foo' => [
					'changeop-deserializer-callback' => 'new-changeop-deserializer-callback'
				]
			]
		);
		$changeOpsCallbacks = $wikibaseRepo->getChangeOpDeserializerCallbacks();
		$expected = [
			'foo' => 'new-changeop-deserializer-callback'
		];
		$this->assertSame( $expected, $changeOpsCallbacks );
	}

}
