<?php

namespace Wikibase\Client\Tests\DataAccess\PropertyParserFunction;

use Language;
use Parser;
use ParserOptions;
use Title;
use User;
use ValueFormatters\FormatterOptions;
use ValueFormatters\ValueFormatter;
use Wikibase\Client\DataAccess\PropertyParserFunction\StatementGroupRendererFactory;
use Wikibase\Client\DataAccess\SnaksFinder;
use Wikibase\Client\Usage\ParserOutputUsageAccumulator;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikibase\LanguageFallbackChainFactory;

/**
 * @covers Wikibase\Client\DataAccess\PropertyParserFunction\StatementGroupRendererFactory
 *
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseDataAccess
 * @group PropertyParserFunctionTest
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class StatementGroupRendererFactoryTest extends \PHPUnit_Framework_TestCase {

	public function testNewRendererForInterfaceMessage() {
		$parser = $this->getParser( 'zh', 'es', true );

		$rendererFactory = $this->getStatementGroupRendererFactory();
		$renderer = $rendererFactory->newRendererFromParser( $parser );

		$this->assertInstanceOf(
			'Wikibase\Client\DataAccess\PropertyParserFunction\LanguageAwareRenderer',
			$renderer
		);
	}

	public function testNewRenderer_contentConversionDisabled() {
		$parser = $this->getParser( 'zh', 'es', false, true );

		$rendererFactory = $this->getStatementGroupRendererFactory();
		$renderer = $rendererFactory->newRendererFromParser( $parser );

		$this->assertInstanceOf(
			'Wikibase\Client\DataAccess\PropertyParserFunction\LanguageAwareRenderer',
			$renderer
		);
	}

	public function testNewRenderer_titleConversionDisabled() {
		$parser = $this->getParser( 'zh', 'es', false, false, true );

		$rendererFactory = $this->getStatementGroupRendererFactory();
		$renderer = $rendererFactory->newRendererFromParser( $parser );

		$this->assertInstanceOf(
			'Wikibase\Client\DataAccess\PropertyParserFunction\VariantsAwareRenderer',
			$renderer
		);
	}

	/**
	 * @dataProvider newRenderer_forParserFormatProvider
	 */
	public function testNewRenderer_forParserFormat( $languageCode, $format ) {
		$parser = $this->getParser( $languageCode, 'es', false, false, false, $format );

		$rendererFactory = $this->getStatementGroupRendererFactory();
		$renderer = $rendererFactory->newRendererFromParser( $parser );

		$this->assertInstanceOf(
			'Wikibase\Client\DataAccess\PropertyParserFunction\LanguageAwareRenderer',
			$renderer
		);
	}

	public function newRenderer_forParserFormatProvider() {
		return array(
			array( 'ku', Parser::OT_PLAIN ),
			array( 'zh', Parser::OT_WIKI ),
			array( 'zh', Parser::OT_PREPROCESS )
		);
	}

	public function testNewRenderer_forNonVariantLanguage() {
		$parser = $this->getParser( 'en', 'es', true );

		$rendererFactory = $this->getStatementGroupRendererFactory();
		$renderer = $rendererFactory->newRendererFromParser( $parser );

		$this->assertInstanceOf(
			'Wikibase\Client\DataAccess\PropertyParserFunction\LanguageAwareRenderer',
			$renderer
		);
	}

	public function testNewRender_forVariantLanguage() {
		$parser = $this->getParser( 'zh' );

		$rendererFactory = $this->getStatementGroupRendererFactory();
		$renderer = $rendererFactory->newRendererFromParser( $parser );

		$this->assertInstanceOf(
			'Wikibase\Client\DataAccess\PropertyParserFunction\VariantsAwareRenderer',
			$renderer
		);
	}

	/**
	 * @dataProvider allowDataAccessInUserLanguageProvider
	 */
	public function testNewRenderer_usageTracking( $allowDataAccessInUserLanguage ) {
		$parser = $this->getParser( 'en', 'es', true );

		$rendererFactory = $this->getStatementGroupRendererFactory( $allowDataAccessInUserLanguage );
		$renderer = $rendererFactory->newRendererFromParser( $parser );

		$usageAccumulator = new ParserOutputUsageAccumulator( $parser->getOutput() );
		$this->assertEquals( "Kittens!", $renderer->render( new ItemId( 'Q1' ), 'P1' ) );

		$usages = $usageAccumulator->getUsages();
		if ( $allowDataAccessInUserLanguage ) {
			$this->assertArrayHasKey( 'Q7#L.es', $usages );
		} else {
			$this->assertArrayHasKey( 'Q7#L.en', $usages );
		}
		$this->assertArrayHasKey( 'Q7#T', $usages );
	}

	/**
	 * @dataProvider allowDataAccessInUserLanguageProvider
	 */
	public function testNewRendererFromParser_languageOption( $allowDataAccessInUserLanguage ) {
		$idResolver = $this->getMockBuilder( 'Wikibase\Client\DataAccess\PropertyIdResolver' )
			->disableOriginalConstructor()
			->getMock();

		$formatterFactory = $this->getMockBuilder( 'Wikibase\Lib\OutputFormatSnakFormatterFactory' )
			->disableOriginalConstructor()
			->getMock();
		$self = $this;
		$formatterFactory->expects( $this->once() )
			->method( 'getSnakFormatter' )
			->will( $this->returnCallback(
				function( $format, FormatterOptions $options ) use ( $self, $allowDataAccessInUserLanguage )  {
					$self->assertSame(
						$allowDataAccessInUserLanguage ? 'es' : 'de',
						$options->getOption( ValueFormatter::OPT_LANG )
					);
					return $self->getMock( 'Wikibase\Lib\SnakFormatter' );
				}
			) );

		$factory = new StatementGroupRendererFactory(
			$idResolver,
			new SnaksFinder(),
			new LanguageFallbackChainFactory(),
			$formatterFactory,
			$this->getMock( 'Wikibase\DataModel\Services\Lookup\EntityLookup' ),
			$allowDataAccessInUserLanguage
		);
		$factory->newRendererFromParser( $this->getParser( 'de', 'es' ) );
	}

	public function allowDataAccessInUserLanguageProvider() {
		return array(
			array( true ),
			array( false ),
		);
	}

	private function getStatementGroupRendererFactory( $allowDataAccessInUserLanguage = false ) {
		return new StatementGroupRendererFactory(
			$this->getPropertyIdResolver(),
			$this->getSnaksFinder(),
			$this->getLanguageFallbackChainFactory(),
			$this->getSnakFormatterFactory(),
			$this->getEntityLookup(),
			$allowDataAccessInUserLanguage
		);
	}

	private function getPropertyIdResolver() {
		$propertyIdResolver = $this->getMockBuilder(
				'Wikibase\Client\DataAccess\PropertyIdResolver'
			)
			->disableOriginalConstructor()
			->getMock();

		$propertyIdResolver->expects( $this->any() )
			->method( 'resolvePropertyId' )
			->will( $this->returnCallback( function ( $name, $lang ) {
				return new PropertyId( $name );
			} ) );

		return $propertyIdResolver;
	}

	private function getSnaksFinder() {
		$snakListFinder = $this->getMock( 'Wikibase\Client\DataAccess\SnaksFinder' );

		$snakListFinder->expects( $this->any() )
			->method( 'findSnaks' )
			->will( $this->returnCallback( function(
				StatementListProvider $statementListProvider,
				PropertyId $propertyId,
				array $acceptableRanks = null
			) {
				return array(
					new PropertyValueSnak( $propertyId, new EntityIdValue( new ItemId( 'Q7' ) ) )
				);
			} ) );

		return $snakListFinder;
	}

	private function getLanguageFallbackChainFactory() {
		return new LanguageFallbackChainFactory();
	}

	private function getSnakFormatterFactory() {
		$snakFormatter = $this->getMockBuilder( 'Wikibase\Lib\SnakFormatter' )
			->disableOriginalConstructor()
			->getMock();

		$snakFormatter->expects( $this->any() )
			->method( 'formatSnak' )
			->will( $this->returnValue( 'Kittens!' ) );

		$snakFormatterFactory = $this->getMockBuilder(
				'Wikibase\Lib\OutputFormatSnakFormatterFactory'
			)
			->disableOriginalConstructor()
			->getMock();

		$snakFormatterFactory->expects( $this->any() )
			->method( 'getSnakFormatter' )
			->will( $this->returnValue( $snakFormatter ) );

		return $snakFormatterFactory;
	}

	private function getEntityLookup() {
		$entityLookup = $this->getMockBuilder( 'Wikibase\DataModel\Services\Lookup\EntityLookup' )
			->disableOriginalConstructor()
			->getMock();

		$entityLookup->expects( $this->any() )
			->method( 'getEntity' )
			->will( $this->returnCallback( function ( EntityId $id ) {
				return new Item( $id );
			} ) );

		return $entityLookup;
	}

	private function getParser(
		$languageCode = 'en',
		$userLanguageCode = 'es',
		$interfaceMessage = false,
		$disableContentConversion = false,
		$disableTitleConversion = false,
		$outputType = Parser::OT_HTML
	) {
		$parserConfig = array( 'class' => 'Parser' );

		$parserOptions = $this->getParserOptions(
			$languageCode,
			$userLanguageCode,
			$interfaceMessage,
			$disableContentConversion,
			$disableTitleConversion
		);

		$parser = new Parser( $parserConfig );

		$parser->setTitle( Title::newFromText( 'Cat' ) );
		$parser->startExternalParse( null, $parserOptions, $outputType );

		return $parser;
	}

	private function getParserOptions( $languageCode, $userLanguageCode, $interfaceMessage,
		$disableContentConversion, $disableTitleConversion
	) {
		$language = Language::factory( $languageCode );
		$userLanguage = Language::factory( $userLanguageCode );

		$parserOptions = new ParserOptions( User::newFromId( 0 ), $userLanguage );
		$parserOptions->setTargetLanguage( $language );
		$parserOptions->setInterfaceMessage( $interfaceMessage );
		$parserOptions->disableContentConversion( $disableContentConversion );
		$parserOptions->disableTitleConversion( $disableTitleConversion );

		return $parserOptions;
	}

}
