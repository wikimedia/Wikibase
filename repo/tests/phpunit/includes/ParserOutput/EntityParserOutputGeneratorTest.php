<?php

namespace Wikibase\Repo\Tests\ParserOutput;

use DataValues\StringValue;
use Language;
use MediaWikiTestCase;
use SpecialPage;
use Title;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Entity\PropertyDataTypeMatcher;
use Wikibase\DataModel\Services\EntityId\EntityIdComposer;
use Wikibase\DataModel\Services\EntityId\SuffixEntityIdParser;
use Wikibase\DataModel\Services\Lookup\InMemoryDataTypeLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\LanguageFallbackChain;
use Wikibase\Lib\Store\EntityInfo;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Lib\Store\Sql\SqlEntityInfoBuilder;
use Wikibase\Repo\EntityReferenceExtractors\EntityReferenceExtractorCollection;
use Wikibase\Repo\EntityReferenceExtractors\EntityReferenceExtractorDelegator;
use Wikibase\Repo\EntityReferenceExtractors\SiteLinkBadgeItemReferenceExtractor;
use Wikibase\Repo\EntityReferenceExtractors\StatementEntityReferenceExtractor;
use Wikibase\Repo\LinkedData\EntityDataFormatProvider;
use Wikibase\Repo\ParserOutput\DispatchingEntityMetaTagsCreatorFactory;
use Wikibase\Repo\ParserOutput\DispatchingEntityViewFactory;
use Wikibase\Repo\ParserOutput\EntityParserOutputGenerator;
use Wikibase\Repo\ParserOutput\EntityStatementDataUpdaterAdapter;
use Wikibase\Repo\ParserOutput\ExternalLinksDataUpdater;
use Wikibase\Repo\ParserOutput\ImageLinksDataUpdater;
use Wikibase\Repo\ParserOutput\ParserOutputJsConfigBuilder;
use Wikibase\Repo\ParserOutput\PlaceholderEmittingEntityTermsView;
use Wikibase\Repo\ParserOutput\ReferencedEntitiesDataUpdater;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\View\EntityDocumentView;
use Wikibase\View\EntityMetaTagsCreator;
use Wikibase\View\LocalizedTextProvider;
use Wikibase\View\ViewContent;
use Wikibase\View\Template\TemplateFactory;

/**
 * @covers \Wikibase\Repo\ParserOutput\EntityParserOutputGenerator
 *
 * @group Wikibase
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class EntityParserOutputGeneratorTest extends MediaWikiTestCase {

	public function provideTestGetParserOutput() {
		return [
			[
				$this->newItem(),
				'kitten item',
				[ 'http://an.url.com', 'https://another.url.org' ],
				[ 'File:This_is_a_file.pdf', 'File:Selfie.jpg' ],
				[
					new ItemId( 'Q42' ),
					new ItemId( 'Q35' ),
					new PropertyId( 'P42' ),
					new PropertyId( 'P10' )
				],
			],
			[ new Item(), null, [], [], [] ]
		];
	}

	/**
	 * EntityDocument $entity
	 * string|null $titleText
	 * string[] $externalLinks
	 * string[] $images
	 * EntityId[] $referencedEntities
	 *
	 * @dataProvider provideTestGetParserOutput
	 */
	public function testGetParserOutput(
		EntityDocument $entity,
		$titleText,
		array $externalLinks,
		array $images,
		array $referencedEntities
	) {
		$entityParserOutputGenerator = $this->newEntityParserOutputGenerator( true, $titleText );

		$parserOutput = $entityParserOutputGenerator->getParserOutput( $entity );

		$this->assertSame( '<TITLE>', $parserOutput->getTitleText(), 'title text' );
		$this->assertSame( '<HTML>', $parserOutput->getText(), 'html text' );

		/**
		 * @see \Wikibase\Repo\Tests\ParserOutput\EntityParserOutputGeneratorIntegrationTest
		 * for tests concerning html view placeholder integration.
		 */

		$this->assertSame( [ '<JS>' ], $parserOutput->getJsConfigVars(), 'config vars' );

		$this->assertSame(
			[
				'title' => $titleText,
			],
			$parserOutput->getExtensionData( 'wikibase-meta-tags' )
		);

		$this->assertEquals(
			$externalLinks,
			array_keys( $parserOutput->getExternalLinks() ),
			'external links'
		);

		$this->assertEquals(
			$images,
			array_keys( $parserOutput->getImages() ),
			'images'
		);

		// TODO would be nice to test this, but ReferencedEntitiesDataUpdater uses LinkBatch which uses the database
//		$this->assertEquals(
//			[ 'item:Q42', 'item:Q35' ],
//			array_keys( $parserOutput->getLinks()[NS_MAIN] ),
//			'badges'
//		);

		$this->assertArrayEquals(
			$referencedEntities,
			$parserOutput->getExtensionData( 'referenced-entities' )
		);

		$alternateLinks = null;
		if ( $entity->getId() ) {
			$jsonHref = SpecialPage::getTitleFor( 'EntityData', $entity->getId()->getSerialization() . '.json' )->getCanonicalURL();
			$ntHref = SpecialPage::getTitleFor( 'EntityData', $entity->getId()->getSerialization() . '.nt' )->getCanonicalURL();
			$alternateLinks = [
				[
					'rel' => 'alternate',
					'href' => $jsonHref,
					'type' => 'application/json'
				],
				[
					'rel' => 'alternate',
					'href' => $ntHref,
					'type' => 'application/n-triples'
				]
			];
		}

		$this->assertEquals(
			$alternateLinks,
			$parserOutput->getExtensionData( 'wikibase-alternate-links' ),
			'alternate links (extension data)'
		);
	}

	public function testGetParserOutput_dontGenerateHtml() {
		$entityParserOutputGenerator = $this->newEntityParserOutputGenerator( false );

		$item = $this->newItem();

		$parserOutput = $entityParserOutputGenerator->getParserOutput( $item, false );

		$this->assertSame( '', $parserOutput->getText() );
		// ParserOutput without HTML must not end up in the cache.
		$this->assertFalse( $parserOutput->isCacheable() );
	}

	public function testTitleText_ItemHasNoLabel() {
		$entityParserOutputGenerator = $this->newEntityParserOutputGenerator( true, 'Q7799929', 'a kitten' );

		$item = new Item( new ItemId( 'Q7799929' ) );
		$item->setDescription( 'en', 'a kitten' );

		$parserOutput = $entityParserOutputGenerator->getParserOutput( $item );

		$this->assertSame(
			[
				'title' => 'Q7799929',
				'description' => 'a kitten',
			],
			$parserOutput->getExtensionData( 'wikibase-meta-tags' )
		);
	}

	private function newEntityParserOutputGenerator( $createView = true, $title = null, $description = null ) {
		$entityDataFormatProvider = new EntityDataFormatProvider();
		$entityDataFormatProvider->setFormatWhiteList( [ 'json', 'ntriples' ] );

		$entityTitleLookup = $this->getEntityTitleLookupMock();

		$propertyDataTypeMatcher = new PropertyDataTypeMatcher( $this->getPropertyDataTypeLookup() );

		$entityIdParser = new BasicEntityIdParser();

		$dataUpdaters = [
			new EntityStatementDataUpdaterAdapter( new ExternalLinksDataUpdater( $propertyDataTypeMatcher ) ),
			new EntityStatementDataUpdaterAdapter( new ImageLinksDataUpdater( $propertyDataTypeMatcher ) ),
			new ReferencedEntitiesDataUpdater(
				$this->newEntityReferenceExtractor(),
				$entityTitleLookup
			)
		];

		return new EntityParserOutputGenerator(
			$this->getEntityViewFactory( $createView ),
			$this->getEntityMetaTagsFactory( $title, $description ),
			$this->getConfigBuilderMock(),
			$entityTitleLookup,
			new SqlEntityInfoBuilder(
				$entityIdParser,
				new EntityIdComposer( [] ),
				WikibaseRepo::getDefaultInstance()->getEntityNamespaceLookup()
			),
			$this->newLanguageFallbackChain(),
			TemplateFactory::getDefaultInstance(),
			$this->getMock( LocalizedTextProvider::class ),
			$entityDataFormatProvider,
			$dataUpdaters,
			Language::factory( 'en' )
		);
	}

	/**
	 * @return LanguageFallbackChain
	 */
	private function newLanguageFallbackChain() {
		$fallbackChain = $this->getMockBuilder( LanguageFallbackChain::class )
			->disableOriginalConstructor()
			->getMock();

		$fallbackChain->expects( $this->any() )
			->method( 'extractPreferredValue' )
			->will( $this->returnCallback( function( $labels ) {
				if ( array_key_exists( 'en', $labels ) ) {
					return [
						'value' => $labels['en'],
						'language' => 'en',
						'source' => 'en'
					];
				}

				return null;
			} ) );

		$fallbackChain->method( 'getFetchLanguageCodes' )
			->willReturn( [ 'en' ] );

		return $fallbackChain;
	}

	private function newItem() {
		$item = new Item( new ItemId( 'Q7799929' ) );

		$item->setLabel( 'en', 'kitten item' );

		$statements = $item->getStatements();

		$statements->addNewStatement( new PropertyValueSnak( 42, new StringValue( 'http://an.url.com' ) ) );
		$statements->addNewStatement( new PropertyValueSnak( 42, new StringValue( 'https://another.url.org' ) ) );

		$statements->addNewStatement( new PropertyValueSnak( 10, new StringValue( 'File:This is a file.pdf' ) ) );
		$statements->addNewStatement( new PropertyValueSnak( 10, new StringValue( 'File:Selfie.jpg' ) ) );

		$item->getSiteLinkList()->addNewSiteLink( 'enwiki', 'kitten', [ new ItemId( 'Q42' ) ] );
		$item->getSiteLinkList()->addNewSiteLink( 'dewiki', 'meow', [ new ItemId( 'Q42' ), new ItemId( 'Q35' ) ] );

		return $item;
	}

	/**
	 * @param bool $createView
	 *
	 * @return DispatchingEntityViewFactory
	 */
	private function getEntityViewFactory( $createView ) {
		$entityViewFactory = $this->getMockBuilder( DispatchingEntityViewFactory::class )
			->disableOriginalConstructor()
			->getMock();

		$entityViewFactory->expects( $createView ? $this->once() : $this->never() )
			->method( 'newEntityView' )
			->will( $this->returnValue( $this->getEntityView() ) );

		return $entityViewFactory;
	}

	/**
	 * @return EntityDocumentView
	 */
	private function getEntityView() {
		$entityView = $this->getMockBuilder( EntityDocumentView::class )
			->setMethods( [
				'getTitleHtml',
				'getContent'
			] )
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		$entityView->expects( $this->any() )
			->method( 'getTitleHtml' )
			->will( $this->returnValue( '<TITLE>' ) );

		$viewContent = new ViewContent(
			'<HTML>',
			[]
		);

		$entityView->expects( $this->any() )
			->method( 'getContent' )
			->will( $this->returnValue( $viewContent ) );

		return $entityView;
	}

	/**
	 * @return DispatchingEntityMetaTagsCreatorFactory
	 */
	private function getEntityMetaTagsFactory( $title = null, $description = null ) {
		$entityMetaTagsCreatorFactory = $this->createMock( DispatchingEntityMetaTagsCreatorFactory::class );

		$entityMetaTagsCreatorFactory
			->method( 'newEntityMetaTags' )
			->will( $this->returnValue( $this->getMetaTags( $title, $description ) ) );

		return $entityMetaTagsCreatorFactory;
	}

	/**
	 * @return EntityMetaTags
	 */
	private function getMetaTags( $title, $description ) {
		$entityMetaTagsCreator = $this->getMockBuilder( EntityMetaTagsCreator::class )
			->setMethods( [
				'getMetaTags',
			] )
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		$tags = [];

		$tags[ 'title' ] = $title;

		if ( $description !== null ) {
			$tags[ 'description' ] = $description;
		}

		$entityMetaTagsCreator->expects( $this->any() )
			->method( 'getMetaTags' )
			->will( $this->returnValue( $tags ) );

		return $entityMetaTagsCreator;
	}

	/**
	 * @return ParserOutputJsConfigBuilder
	 */
	private function getConfigBuilderMock() {
		$configBuilder = $this->getMockBuilder( ParserOutputJsConfigBuilder::class )
			->disableOriginalConstructor()
			->getMock();

		$configBuilder->expects( $this->any() )
			->method( 'build' )
			->will( $this->returnValue( [ '<JS>' ] ) );

		return $configBuilder;
	}

	/**
	 * @return EntityTitleLookup
	 */
	private function getEntityTitleLookupMock() {
		$entityTitleLookup = $this->getMock( EntityTitleLookup::class );

		$entityTitleLookup->expects( $this->any() )
			->method( 'getTitleForId' )
			->will( $this->returnCallback( function( EntityId $id ) {
				return Title::makeTitle(
					NS_MAIN,
					$id->getEntityType() . ':' . $id->getSerialization()
				);
			} ) );

		return $entityTitleLookup;
	}

	private function getPropertyDataTypeLookup() {
		$dataTypeLookup = new InMemoryDataTypeLookup();

		$dataTypeLookup->setDataTypeForProperty( new PropertyId( 'P42' ), 'url' );
		$dataTypeLookup->setDataTypeForProperty( new PropertyId( 'P10' ), 'commonsMedia' );

		return $dataTypeLookup;
	}

	public function testGetParserOutputIncludesLabelsOfRedirectEntity() {
		$item = new Item( new ItemId( 'Q303' ) );

		$redirectSourceId = new ItemId( 'Q809' );
		$redirectSource = new Item( $redirectSourceId );
		$redirectSource->setLabel( 'en', 'redirect label' );

		$redirectTargetId = new ItemId( 'Q808' );
		$redirectTarget = new Item( $redirectTargetId );
		$redirectTarget->setLabel( 'en', 'target label' );

		$item->getStatements()->addNewStatement( new PropertyValueSnak( new PropertyId( 'P11' ), new EntityIdValue( $redirectSourceId ) ) );

		$user = $this->getTestUser()->getUser();

		$store = WikibaseRepo::getDefaultInstance()->getEntityStore();
		$store->saveEntity( $item, 'test item', $user );
		$store->saveEntity( $redirectSource, 'test item', $user );
		$store->saveEntity( $redirectTarget, 'test item', $user );
		$store->saveRedirect( new EntityRedirect( $redirectSourceId, $redirectTargetId ), 'mistake', $user );

		$entityParserOutputGenerator = $this->getGeneratorForRedirectTest();
		$parserOutput = $entityParserOutputGenerator->getParserOutput( $item );

		$foo = $parserOutput->getText();

		$this->assertContains( 'target label', $foo );
	}

	private function getGeneratorForRedirectTest() {
		$entityTitleLookup = $this->getEntityTitleLookupMock();
		$entityIdParser = new BasicEntityIdParser();

		$dataUpdaters = [
			new ReferencedEntitiesDataUpdater(
				$this->newEntityReferenceExtractor(),
				$entityTitleLookup
			)
		];

		return new EntityParserOutputGenerator(
			$this->getViewFactoryForRedirectTest(),
			$this->getEntityMetaTagsFactory(),
			$this->getConfigBuilderMock(),
			$entityTitleLookup,
			new SqlEntityInfoBuilder(
				$entityIdParser,
				new EntityIdComposer( [
					'item' => function( $ignore, $idPart ) {
						return new ItemId( 'Q' . $idPart );
					}
				] ),
				WikibaseRepo::getDefaultInstance()->getEntityNamespaceLookup()
			),
			$this->newLanguageFallbackChain(),
			TemplateFactory::getDefaultInstance(),
			$this->getMock( LocalizedTextProvider::class ),
			new EntityDataFormatProvider(),
			$dataUpdaters,
			Language::factory( 'en' )
		);
	}

	private function getViewFactoryForRedirectTest() {
		$repo = WikibaseRepo::getDefaultInstance();
		return new DispatchingEntityViewFactory( [
			'item' => function(
				Language $language,
				LanguageFallbackChain $fallbackChain,
				EntityDocument $entity,
				EntityInfo $entityInfo
			) use ( $repo ) {
				$viewFactory = $repo->getViewFactory();
				$termsView = $this->createMock( PlaceholderEmittingEntityTermsView::class );
				$termsView->method( 'getPlaceholders' )->with( $entity )->willReturn( [] );
				return $viewFactory->newItemView(
					$language,
					$fallbackChain,
					$entityInfo,
					$termsView
				);
			},
		] );
	}

	private function newEntityReferenceExtractor() {
		return new EntityReferenceExtractorDelegator( [
			'item' => function() {
				return new EntityReferenceExtractorCollection( [
					new SiteLinkBadgeItemReferenceExtractor(),
					new StatementEntityReferenceExtractor(
						$this->getMockBuilder( SuffixEntityIdParser::class )
							->disableOriginalConstructor()
							->getMock()
					)
				] );
			}
		], $this->getMockBuilder( StatementEntityReferenceExtractor::class )
			->disableOriginalConstructor()
			->getMock() );
	}

}
