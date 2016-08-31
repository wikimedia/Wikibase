<?php

namespace Wikibase\Repo\Tests\ParserOutput;

use DataValues\StringValue;
use MediaWikiTestCase;
use SpecialPage;
use Title;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Entity\PropertyDataTypeMatcher;
use Wikibase\DataModel\Services\Lookup\InMemoryDataTypeLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\LanguageFallbackChain;
use Wikibase\Lib\EntityIdComposer;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Lib\Store\Sql\SqlEntityInfoBuilderFactory;
use Wikibase\Repo\LinkedData\EntityDataFormatProvider;
use Wikibase\Repo\ParserOutput\DispatchingEntityViewFactory;
use Wikibase\Repo\ParserOutput\EntityParserOutputGenerator;
use Wikibase\Repo\ParserOutput\ExternalLinksDataUpdater;
use Wikibase\Repo\ParserOutput\ImageLinksDataUpdater;
use Wikibase\Repo\ParserOutput\ParserOutputJsConfigBuilder;
use Wikibase\Repo\ParserOutput\ReferencedEntitiesDataUpdater;
use Wikibase\View\EntityView;
use Wikibase\View\LocalizedTextProvider;
use Wikibase\View\Template\TemplateFactory;

/**
 * @covers Wikibase\Repo\ParserOutput\EntityParserOutputGenerator
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group Database
 *
 * @license GPL-2.0+
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class EntityParserOutputGeneratorTest extends MediaWikiTestCase {

	public function provideTestGetParserOutput() {
		return [
			[
				$this->newItem(),
				'kitten item' ,
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
		$entityParserOutputGenerator = $this->newEntityParserOutputGenerator();

		$parserOutput = $entityParserOutputGenerator->getParserOutput( $entity );

		$this->assertSame( '<TITLE>', $parserOutput->getTitleText(), 'title text' );
		$this->assertSame( '<HTML>', $parserOutput->getText(), 'html text' );

		$this->assertSame( [], $parserOutput->getExtensionData( 'wikibase-view-chunks' ), 'view chunks' );

		$this->assertArrayHasKey(
			'en',
			$parserOutput->getExtensionData( 'wikibase-terms-list-items' )
		);

		$this->assertSame( array( '<JS>' ), $parserOutput->getJsConfigVars(), 'config vars' );

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
//			array( 'item:Q42', 'item:Q35' ),
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
		$entityParserOutputGenerator = $this->newEntityParserOutputGenerator();

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

	private function newEntityParserOutputGenerator( $createView = true ) {
		$entityDataFormatProvider = new EntityDataFormatProvider();

		$formats = array( 'json', 'ntriples' );
		$entityDataFormatProvider->setFormatWhiteList( $formats );

		$entityTitleLookup = $this->getEntityTitleLookupMock();

		$propertyDataTypeMatcher = new PropertyDataTypeMatcher( $this->getPropertyDataTypeLookup() );

		$entityIdParser = new BasicEntityIdParser();

		$dataUpdaters = array(
			new ExternalLinksDataUpdater( $propertyDataTypeMatcher ),
			new ImageLinksDataUpdater( $propertyDataTypeMatcher ),
			new ReferencedEntitiesDataUpdater(
				$entityTitleLookup,
				$entityIdParser
			)
		);

		return new EntityParserOutputGenerator(
			$this->getEntityViewFactory( $createView ),
			$this->getConfigBuilderMock(),
			$entityTitleLookup,
			new SqlEntityInfoBuilderFactory( $entityIdParser, new EntityIdComposer( [] ) ),
			$this->newLanguageFallbackChain(),
			TemplateFactory::getDefaultInstance(),
			$this->getMock( LocalizedTextProvider::class ),
			$entityDataFormatProvider,
			$dataUpdaters,
			'en',
			true
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
					return array(
						'value' => $labels['en'],
						'language' => 'en',
						'source' => 'en'
					);
				}

				return null;
			} ) );

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

		$item->getSiteLinkList()->addNewSiteLink( 'enwiki', 'kitten', array( new ItemId( 'Q42' ) ) );
		$item->getSiteLinkList()->addNewSiteLink( 'dewiki', 'meow', array( new ItemId( 'Q42' ), new ItemId( 'Q35' ) ) );

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
	 * @return EntityView
	 */
	private function getEntityView() {
		$entityView = $this->getMockBuilder( EntityView::class )
			->setMethods( array(
				'getTitleHtml',
				'getHtml',
			) )
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		$entityView->expects( $this->any() )
			->method( 'getTitleHtml' )
			->will( $this->returnValue( '<TITLE>' ) );

		$entityView->expects( $this->any() )
			->method( 'getHtml' )
			->will( $this->returnValue( '<HTML>' ) );

		return $entityView;
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
			->will( $this->returnValue( array( '<JS>' ) ) );

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

}
