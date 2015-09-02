<?php

namespace Wikibase\Lib\Test;

use DataValues\DataValue;
use DataValues\MonolingualTextValue;
use DataValues\QuantityValue;
use DataValues\StringValue;
use DataValues\TimeValue;
use Language;
use MediaWikiTestCase;
use Title;
use ValueFormatters\FormatterOptions;
use ValueFormatters\StringFormatter;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Services\EntityId\PlainEntityIdFormatter;
use Wikibase\DataModel\Term\Term;
use Wikibase\LanguageFallbackChain;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\EntityIdValueFormatter;
use Wikibase\Lib\FormatterLabelDescriptionLookupFactory;
use Wikibase\Lib\OutputFormatValueFormatterFactory;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Lib\WikibaseValueFormatterBuilders;

/**
 * @covers Wikibase\Lib\WikibaseValueFormatterBuilders
 *
 * @group ValueFormatters
 * @group DataValueExtensions
 * @group WikibaseLib
 * @group Wikibase
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class WikibaseValueFormatterBuildersTest extends MediaWikiTestCase {

	protected function setUp() {
		parent::setUp();

		$this->setMwGlobals( 'wgArticlePath', '/wiki/$1' );
	}

	/**
	 * @param EntityTitleLookup|null $entityTitleLookup
	 *
	 * @return WikibaseValueFormatterBuilders
	 */
	private function newWikibaseValueFormatterBuilders( EntityTitleLookup $entityTitleLookup = null ) {
		$termLookup = $this->getMock( 'Wikibase\DataModel\Services\Lookup\TermLookup' );

		$termLookup->expects( $this->any() )
			->method( 'getLabel' )
			->will( $this->returnCallback( function ( EntityId $entityId, $language ) {
				switch ( $language ) {
					case 'de':
						return 'Name für ' . $entityId->getSerialization();
					default:
						return 'Label for ' . $entityId->getSerialization();
				}
			} ) );

		$termLookup->expects( $this->any() )
			->method( 'getLabels' )
			->will( $this->returnCallback( function ( EntityId $entityId ) {
				return array(
					'de' => 'Name für ' . $entityId->getSerialization(),
					'en' => 'Label for ' . $entityId->getSerialization(),
				);
			} ) );

		$languageNameLookup = $this->getMock( 'Wikibase\Lib\LanguageNameLookup' );
		$languageNameLookup->expects( $this->any() )
			->method( 'getName' )
			->will( $this->returnValue( 'Deutsch' ) );

		return new WikibaseValueFormatterBuilders(
			Language::factory( 'en' ),
			new FormatterLabelDescriptionLookupFactory( $termLookup ),
			$languageNameLookup,
			new BasicEntityIdParser(),
			$entityTitleLookup
		);
	}

	private function newFormatterOptions( $lang = 'en' ) {
		return new FormatterOptions( array(
			ValueFormatter::OPT_LANG => $lang,
		) );
	}

	/**
	 * @return EntityTitleLookup
	 */
	private function newEntityTitleLookup() {
		$entityTitleLookup = $this->getMock( 'Wikibase\Lib\Store\EntityTitleLookup' );
		$entityTitleLookup->expects( $this->any() )
			->method( 'getTitleForId' )
			->will( $this->returnCallback( function ( EntityId $entityId ) {
				return Title::newFromText( $entityId->getSerialization() );
			} )
		);

		return $entityTitleLookup;
	}

	/**
	 * @dataProvider buildDispatchingValueFormatterProvider
	 */
	public function testBuildDispatchingValueFormatter(
		$format,
		FormatterOptions $options,
		DataValue $value,
		$expected,
		$dataTypeId = null
	) {
		$builders = $this->newWikibaseValueFormatterBuilders( $this->newEntityTitleLookup() );

		$factory = new OutputFormatValueFormatterFactory( $builders->getValueFormatterBuildersForFormats() );
		$formatter = $builders->buildDispatchingValueFormatter( $factory, $format, $options );

		$text = $formatter->formatValue( $value, $dataTypeId );
		$this->assertRegExp( $expected, $text );
	}

	public function buildDispatchingValueFormatterProvider() {
		return array(
			'plain url' => array(
				SnakFormatter::FORMAT_PLAIN,
				$this->newFormatterOptions(),
				new StringValue( 'http://acme.com/' ),
				'@^http://acme\\.com/$@'
			),
			'wikitext string' => array(
				SnakFormatter::FORMAT_WIKI,
				$this->newFormatterOptions(),
				new StringValue( '{Wikibase}' ),
				'@^&#123;Wikibase&#125;$@'
			),
			'html string' => array(
				SnakFormatter::FORMAT_HTML,
				$this->newFormatterOptions(),
				new StringValue( 'I <3 Wikibase & stuff' ),
				'@^I &lt;3 Wikibase &amp; stuff$@'
			),
			'plain item label (with entity lookup)' => array(
				SnakFormatter::FORMAT_PLAIN,
				$this->newFormatterOptions(),
				new EntityIdValue( new ItemId( 'Q5' ) ),
				'@^Label for Q5$@' // compare mock object created in newBuilders()
			),
			'plain item label (with language fallback)' => array(
				SnakFormatter::FORMAT_PLAIN,
				$this->newFormatterOptions( 'de-ch' ), // should fall back to 'de'
				new EntityIdValue( new ItemId( 'Q5' ) ),
				'@^Name für Q5$@' // compare mock object created in newBuilders()
			),
			'widget item link (with entity lookup)' => array(
				SnakFormatter::FORMAT_HTML_WIDGET,
				$this->newFormatterOptions(),
				new EntityIdValue( new ItemId( 'Q5' ) ),
				'/^<a\b[^>]* href="[^"]*\bQ5">Label for Q5<\/a>.*$/', // compare mock object created in newBuilders()
				'wikibase-item'
			),
			'property link' => array(
				SnakFormatter::FORMAT_HTML,
				$this->newFormatterOptions(),
				new EntityIdValue( new PropertyId( 'P5' ) ),
				'/^<a\b[^>]* href="[^"]*\bP5">Label for P5<\/a>.*$/',
				'wikibase-property'
			),
			'diff <url>' => array(
				SnakFormatter::FORMAT_HTML_DIFF,
				$this->newFormatterOptions(),
				new StringValue( '<http://acme.com/>' ),
				'@^&lt;http://acme\\.com/&gt;$@'
			),
			'localized quantity' => array(
				SnakFormatter::FORMAT_WIKI,
				$this->newFormatterOptions( 'de' ),
				QuantityValue::newFromNumber( '+123456.789' ),
				'@^123\\.456,789$@'
			),
			'commons link' => array(
				SnakFormatter::FORMAT_HTML,
				$this->newFormatterOptions(),
				new StringValue( 'Example.jpg' ),
				'@^<a class="extiw" href="//commons\\.wikimedia\\.org/wiki/File:Example\\.jpg">Example\\.jpg</a>$@',
				'commonsMedia'
			),
			'a month in 1980' => array(
				SnakFormatter::FORMAT_HTML,
				$this->newFormatterOptions(),
				new TimeValue(
					'+1980-05-01T00:00:00Z',
					0, 0, 0,
					TimeValue::PRECISION_MONTH,
					'http://www.wikidata.org/entity/Q1985727'
				),
				'/^May 1980$/'
			),
			'a gregorian day in 1520' => array(
				SnakFormatter::FORMAT_HTML,
				$this->newFormatterOptions(),
				new TimeValue(
					'+1520-05-01T00:00:00Z',
					0, 0, 0,
					TimeValue::PRECISION_DAY,
					'http://www.wikidata.org/entity/Q1985727'
				),
				'/^1 May 1520<sup class="wb-calendar-name">Gregorian<\/sup>$/'
			),
			'a julian day in 1980' => array(
				SnakFormatter::FORMAT_HTML,
				$this->newFormatterOptions(),
				new TimeValue(
					'+1980-05-01T00:00:00Z',
					0, 0, 0,
					TimeValue::PRECISION_DAY,
					'http://www.wikidata.org/entity/Q1985786'
				),
				'/^1 May 1980<sup class="wb-calendar-name">Julian<\/sup>$/'
			),
			'text in english' => array(
				SnakFormatter::FORMAT_PLAIN,
				$this->newFormatterOptions( 'en' ),
				new MonolingualTextValue( 'en', 'Hello World' ),
				'/^Hello World$/'
			),
			'text in german' => array(
				SnakFormatter::FORMAT_HTML,
				$this->newFormatterOptions( 'en' ),
				new MonolingualTextValue( 'de', 'Hallo Welt' ),
				'/^<span lang="de".*?>Hallo Welt<\/span>.*\Deutsch.*$/'
			),
			'text in spanish' => array(
				SnakFormatter::FORMAT_WIKI,
				$this->newFormatterOptions( 'de' ),
				new MonolingualTextValue( 'es', 'Ola' ),
				'/^Ola$/u'
			),
		);
	}

	/**
	 * In case WikibaseValueFormatterBuilders doesn't have a EntityTitleLookup it returns
	 * a formatter which doesn't link the entity id.
	 *
	 * @dataProvider buildDispatchingValueFormatterNoTitleLookupProvider
	 */
	public function testBuildDispatchingValueFormatter_noTitleLookup(
		$format,
		FormatterOptions $options,
		DataValue $value,
		$expected,
		$dataTypeId = null
	) {
		$builders = $this->newWikibaseValueFormatterBuilders();

		$factory = new OutputFormatValueFormatterFactory( $builders->getValueFormatterBuildersForFormats() );
		$formatter = $builders->buildDispatchingValueFormatter( $factory, $format, $options );

		$text = $formatter->formatValue( $value, $dataTypeId );
		$this->assertRegExp( $expected, $text );
	}

	public function buildDispatchingValueFormatterNoTitleLookupProvider() {
		return array(
			'plain item label' => array(
				SnakFormatter::FORMAT_PLAIN,
				$this->newFormatterOptions(),
				new EntityIdValue( new ItemId( 'Q5' ) ),
				'@^Label for Q5$@'
			),
			'widget item link' => array(
				SnakFormatter::FORMAT_HTML_WIDGET,
				$this->newFormatterOptions(),
				new EntityIdValue( new ItemId( 'Q5' ) ),
				'/^Label for Q5*$/',
				'wikibase-item'
			)
		);
	}

	/**
	 * @dataProvider buildDispatchingValueFormatterProvider_LabelDescriptionLookupOption
	 */
	public function testBuildDispatchingValueFormatter_LabelDescriptionLookupOption(
		FormatterOptions $options,
		ItemId $value,
		$expected
	) {
		$builders = $this->newWikibaseValueFormatterBuilders( $this->newEntityTitleLookup() );

		$factory = new OutputFormatValueFormatterFactory( $builders->getValueFormatterBuildersForFormats() );
		$formatter = $builders->buildDispatchingValueFormatter( $factory, SnakFormatter::FORMAT_HTML, $options );

		$value = new EntityIdValue( $value );
		$text = $formatter->formatValue( $value, 'wikibase-item' );
		$this->assertRegExp( $expected, $text );
	}

	public function buildDispatchingValueFormatterProvider_LabelDescriptionLookupOption() {
		$labelDescriptionLookup = $this->getMock( 'Wikibase\DataModel\Services\Lookup\LabelDescriptionLookup' );
		$labelDescriptionLookup->expects( $this->any() )
			->method( 'getLabel' )
			->will( $this->returnValue( new Term( 'xy', 'Custom LabelDescriptionLookup' ) ) );

		$fallbackFactory = new LanguageFallbackChainFactory();
		$fallbackChain = $fallbackFactory->newFromLanguage( Language::factory( 'de-ch' ) );

		return array(
			'language option' => array(
				new FormatterOptions( array(
					ValueFormatter::OPT_LANG => 'de',
				) ),
				new ItemId( 'Q5' ),
				'@>Name für Q5<@'
			),
			'fallback option' => array(
				new FormatterOptions( array(
					FormatterLabelDescriptionLookupFactory::OPT_LANGUAGE_FALLBACK_CHAIN => $fallbackChain,
				) ),
				new ItemId( 'Q5' ),
				'@>Name für Q5<@'
			),
			'LabelDescriptionLookup option' => array(
				new FormatterOptions( array(
					FormatterLabelDescriptionLookupFactory::OPT_LABEL_DESCRIPTION_LOOKUP => $labelDescriptionLookup,
				) ),
				new ItemId( 'Q5' ),
				'@>Custom LabelDescriptionLookup<@'
			),
		);
	}

	public function testSetValueFormatter() {
		$mockFormatter = $this->getMock( 'ValueFormatters\ValueFormatter' );
		$mockFormatter->expects( $this->any() )
			->method( 'format' )
			->will( $this->returnValue( 'MOCK!' ) );

		$builders = $this->newWikibaseValueFormatterBuilders();
		$builders->setValueFormatter( SnakFormatter::FORMAT_PLAIN, 'VT:string', $mockFormatter );
		$builders->setValueFormatter( SnakFormatter::FORMAT_PLAIN, 'VT:time', null );

		$formatter = $builders->buildDispatchingValueFormatter(
			new OutputFormatValueFormatterFactory(
				$builders->getValueFormatterBuildersForFormats()
			),
			SnakFormatter::FORMAT_PLAIN,
			new FormatterOptions()
		);

		$this->assertEquals(
			'MOCK!',
			$formatter->format( new StringValue( 'o_O' ) ),
			'Formatter override'
		);

		$this->setExpectedException( 'ValueFormatters\FormattingException' );

		$timeValue = new TimeValue(
			'+2013-01-01T00:00:00Z',
			0, 0, 0,
			TimeValue::PRECISION_SECOND,
			'http://nyan.cat/original.php'
		);
		$formatter->format( $timeValue ); // expecting a FormattingException
	}

	public function testSetValueFormatterClass() {
		$builders = $this->newWikibaseValueFormatterBuilders();
		$builders->setValueFormatterClass(
			SnakFormatter::FORMAT_PLAIN,
			'VT:monolingualtext',
			'Wikibase\Formatters\MonolingualTextFormatter'
		);
		$builders->setValueFormatterClass(
			SnakFormatter::FORMAT_PLAIN,
			'VT:time',
			null
		);

		$options = new FormatterOptions();
		$factory = new OutputFormatValueFormatterFactory(
			$builders->getValueFormatterBuildersForFormats()
		);
		$formatter = $builders->buildDispatchingValueFormatter(
			$factory,
			SnakFormatter::FORMAT_PLAIN,
			$options
		);

		$this->assertEquals(
			'value',
			$formatter->format( new MonolingualTextValue( 'en', 'value' ) ),
			'Extra formatter'
		);

		$this->setExpectedException( 'ValueFormatters\FormattingException' );

		$timeValue = new TimeValue(
			'+2013-01-01T00:00:00Z',
			0, 0, 0,
			TimeValue::PRECISION_SECOND,
			'http://nyan.cat/original.php'
		);

		$formatter->format( $timeValue ); // expecting a FormattingException
	}

	public function testSetValueFormatterBuilder() {
		$builder = function () {
			return new EntityIdValueFormatter( new PlainEntityIdFormatter() );
		};

		$builders = $this->newWikibaseValueFormatterBuilders();
		$builders->setValueFormatterBuilder(
			SnakFormatter::FORMAT_PLAIN,
			'VT:wikibase-entityid',
			$builder
		);
		$builders->setValueFormatterBuilder(
			SnakFormatter::FORMAT_PLAIN,
			'VT:time',
			null
		);

		$options = new FormatterOptions();
		$factory = new OutputFormatValueFormatterFactory(
			$builders->getValueFormatterBuildersForFormats()
		);
		$formatter = $builders->buildDispatchingValueFormatter(
			$factory,
			SnakFormatter::FORMAT_PLAIN,
			$options
		);

		$this->assertEquals(
			'Q5',
			$formatter->format( new EntityIdValue( new ItemId( "Q5" ) ) ),
			'Extra formatter'
		);

		$this->setExpectedException( 'ValueFormatters\FormattingException' );

		$timeValue = new TimeValue(
			'+2013-01-01T00:00:00Z',
			0, 0, 0,
			TimeValue::PRECISION_SECOND,
			'http://nyan.cat/original.php'
		);
		$formatter->format( $timeValue ); // expecting a FormattingException
	}

	public function testGetPlainTextFormatters() {
		$builders = $this->newWikibaseValueFormatterBuilders();
		$options = new FormatterOptions();

		// check for all the required types
		$required = array(
			'VT:string',
			'VT:time',
			'VT:globecoordinate',
			'VT:wikibase-entityid',
			'VT:quantity',
		);

		// check for all the required types, that is, the ones supported by the fallback format
		$this->assertIncluded(
			$required,
			array_keys( $builders->getPlainTextFormatters( $options ) )
		);

		// skip two of the required entries
		$skip = array_slice( $required, 2 );
		$this->assertExcluded(
			$skip,
			array_keys( $builders->getPlainTextFormatters( $options, $skip ) )
		);
	}

	public function testGetWikiTextFormatters() {
		$builders = $this->newWikibaseValueFormatterBuilders();
		$options = new FormatterOptions();

		// check for all the required types, that is, the ones supported by the fallback format
		$required = array_keys( $builders->getPlainTextFormatters( $options ) );
		$this->assertIncluded(
			$required,
			array_keys( $builders->getWikiTextFormatters( $options ) )
		);

		// skip two of the required entries
		$skip = array_slice( $required, 2 );
		$this->assertExcluded(
			$skip,
			array_keys( $builders->getWikiTextFormatters( $options, $skip ) )
		);
	}

	public function testGetHtmlFormatters() {
		$builders = $this->newWikibaseValueFormatterBuilders();
		$options = new FormatterOptions();

		// check for all the required types, that is, the ones supported by the fallback format
		$required = array_keys( $builders->getPlainTextFormatters( $options ) );
		$this->assertIncluded(
			$required,
			array_keys( $builders->getHtmlFormatters( $options ) )
		);

		// skip two of the required entries
		$skip = array_slice( $required, 2 );
		$this->assertExcluded(
			$skip,
			array_keys( $builders->getHtmlFormatters( $options, $skip ) )
		);
	}

	public function testGetWidgetFormatters() {
		$builders = $this->newWikibaseValueFormatterBuilders();
		$options = $this->newFormatterOptions();

		// check for all the required types, that is, the ones supported by the fallback format
		$required = array_keys( $builders->getHtmlFormatters( $options ) );
		$this->assertIncluded(
			$required,
			array_keys( $builders->getWidgetFormatters( $options ) )
		);

		// skip two of the required entries
		$skip = array_slice( $required, 2 );
		$this->assertExcluded(
			$skip,
			array_keys( $builders->getWidgetFormatters( $options, $skip ) )
		);
	}

	public function testGetDiffFormatters() {
		$builders = $this->newWikibaseValueFormatterBuilders();
		$options = $this->newFormatterOptions();

		// check for all the required types, that is, the ones supported by the fallback format
		$required = array_keys( $builders->getHtmlFormatters( $options ) );
		$this->assertIncluded(
			$required,
			array_keys( $builders->getDiffFormatters( $options ) )
		);

		// skip two of the required entries
		$skip = array_slice( $required, 2 );
		$this->assertExcluded(
			$skip,
			array_keys( $builders->getDiffFormatters( $options, $skip ) )
		);
	}

	/**
	 * Asserts that $actualTypes contains all types listed in $requiredTypes.
	 *
	 * @param string[] $requiredTypes
	 * @param string[] $actualTypes
	 */
	private function assertIncluded( array $requiredTypes, array $actualTypes ) {
		sort( $requiredTypes );
		sort( $actualTypes );
		$this->assertEmpty( array_diff( $requiredTypes, $actualTypes ), 'required' );
	}

	/**
	 * Asserts that $actualTypes does not contain types listed in $skippedTypes.
	 *
	 * @param string[] $skippedTypes
	 * @param string[] $actualTypes
	 */
	private function assertExcluded( array $skippedTypes, array $actualTypes ) {
		sort( $skippedTypes );
		sort( $actualTypes );
		$this->assertEmpty( array_intersect( $skippedTypes, $actualTypes ), 'skipped' );
	}

	public function testMakeEscapingFormatters() {
		$builders = $this->newWikibaseValueFormatterBuilders();

		$formatters = $builders->makeEscapingFormatters(
			array( new StringFormatter( new FormatterOptions() ) ),
			'htmlspecialchars'
		);

		$text = $formatters[0]->format( new StringValue( 'I <3 Wikibase' ) );
		$this->assertEquals( 'I &lt;3 Wikibase', $text );
	}

	/**
	 * @dataProvider applyLanguageDefaultsProvider
	 */
	public function testApplyLanguageDefaults( FormatterOptions $options, $expectedLanguage, $expectedFallback ) {
		$builders = $this->newWikibaseValueFormatterBuilders();

		$builders->applyLanguageDefaults( $options );

		if ( $expectedLanguage !== null ) {
			$lang = $options->getOption( ValueFormatter::OPT_LANG );
			$this->assertEquals( $expectedLanguage, $lang, 'OPT_LANG' );
		}

		if ( $expectedFallback !== null ) {
			/** @var LanguageFallbackChain $languageFallback */
			$languageFallback = $options->getOption( FormatterLabelDescriptionLookupFactory::OPT_LANGUAGE_FALLBACK_CHAIN );
			$languages = $languageFallback->getFallbackChain();
			$lang = $languages[0]->getLanguage()->getCode();

			$this->assertEquals( $expectedFallback, $lang, 'OPT_LANGUAGE_FALLBACK_CHAIN' );
		}
	}

	public function applyLanguageDefaultsProvider() {
		$languageFallbackFactory = new LanguageFallbackChainFactory();
		$languageFallback = $languageFallbackFactory->newFromLanguage( Language::factory( 'fr' ) );

		return array(
			'empty' => array(
				new FormatterOptions( array() ),
				'en', // determined in WikibaseValueFormatterBuildersTest::newBuilder()
				'en'  // derived from language code
			),
			'language code set' => array(
				new FormatterOptions( array( ValueFormatter::OPT_LANG => 'de' ) ),
				'de', // as given
				'de'  // derived from language code
			),
			'language fallback set' => array(
				new FormatterOptions( array(
					FormatterLabelDescriptionLookupFactory::OPT_LANGUAGE_FALLBACK_CHAIN => $languageFallback
				) ),
				'en', // default code is taken from the constructor, not the fallback chain
				'fr'  // as given
			),
			'language code and fallback set' => array(
				new FormatterOptions( array(
					ValueFormatter::OPT_LANG => 'de',
					FormatterLabelDescriptionLookupFactory::OPT_LANGUAGE_FALLBACK_CHAIN => $languageFallback
				) ),
				'de', // as given
				'fr'  // as given
			),
		);
	}

}
