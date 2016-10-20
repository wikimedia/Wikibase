<?php

namespace Wikibase\Client\Tests\DataAccess;

use DataValues\StringValue;
use Language;
use PHPUnit_Framework_TestCase;
use ValueFormatters\FormatterOptions;
use Wikibase\Client\DataAccess\DataAccessSnakFormatterFactory;
use Wikibase\Client\Usage\UsageAccumulator;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\OutputFormatSnakFormatterFactory;
use Wikibase\Lib\SnakFormatter;

/**
 * @covers Wikibase\Client\DataAccess\DataAccessSnakFormatterFactory
 *
 * @note We also have integration tests for this at DataAccessSnakFormatterOutputFormatTest.
 *
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseDataAccess
 *
 * @license GPL-2.0+
 * @author Marius Hoch
 */
class DataAccessSnakFormatterFactoryTest extends PHPUnit_Framework_TestCase {

	private function getDataAccessSnakFormatterFactory( $expectedFormat ) {
		return new DataAccessSnakFormatterFactory(
			$this->getLanguageFallbackChainFactory(),
			$this->getOutputFormatSnakFormatterFactory( $expectedFormat ),
			$this->getMock( PropertyDataTypeLookup::class )
		);
	}

	/**
	 * @return LanguageFallbackChainFactory
	 */
	private function getLanguageFallbackChainFactory() {
		$realFactory = new LanguageFallbackChainFactory();

		$factory = $this->getMock( LanguageFallbackChainFactory::class );

		$factory->expects( $this->once() )
			->method( 'newFromLanguage' )
			->with( $this->isInstanceOf( Language::class ), LanguageFallbackChainFactory::FALLBACK_ALL )
			->will( $this->returnCallback( [ $realFactory, 'newFromLanguage' ] ) );

		return $factory;
	}

	/**
	 * @return OutputFormatSnakFormatterFactory
	 */
	private function getOutputFormatSnakFormatterFactory( $expectedFormat ) {
		$factory = $this->getMockBuilder( OutputFormatSnakFormatterFactory::class )
			->disableOriginalConstructor()
			->getMock();

		$snakFormatter = $this->getMock( SnakFormatter::class );

		$snakFormatter->expects( $this->any() )
			->method( 'formatSnak' )
			->will( $this->returnCallback( function( PropertyValueSnak $snak ) {
				return $snak->getDataValue()->getValue();
			} ) );

		$snakFormatter->expects( $this->any() )
			->method( 'getFormat' )
			->will( $this->returnValue( $expectedFormat ) );

		$factory->expects( $this->once() )
			->method( 'getSnakFormatter' )
			->with( $expectedFormat, $this->isInstanceOf( FormatterOptions::class ) )
			->will( $this->returnValue( $snakFormatter ) );

		return $factory;
	}

	public function testNewEscapedPlainTextSnakFormatter() {
		$factory = $this->getDataAccessSnakFormatterFactory( SnakFormatter::FORMAT_PLAIN );
		$snakFormatter = $factory->newEscapedPlainTextSnakFormatter(
			Language::factory( 'fr' ),
			$this->getMock( UsageAccumulator::class )
		);

		$this->assertInstanceOf( SnakFormatter::class, $snakFormatter );
		$this->assertSame( SnakFormatter::FORMAT_PLAIN, $snakFormatter->getFormat() );
	}

	public function richWikitextSnakFormatterProvider() {
		$id = new PropertyId( 'P1' );

		return [
			[ new PropertyValueSnak( $id, new StringValue( '' ) ), '' ],
			[ new PropertyValueSnak( $id, new StringValue( '<RAW>' ) ), '<span><RAW></span>' ],
		];
	}

	/**
	 * @dataProvider richWikitextSnakFormatterProvider
	 */
	public function testRichWikitextSnakFormatter( Snak $snak, $expected ) {
		$factory = $this->getDataAccessSnakFormatterFactory( SnakFormatter::FORMAT_WIKI );
		$snakFormatter = $factory->newRichWikitextSnakFormatter(
			Language::factory( 'fr' ),
			$this->getMock( UsageAccumulator::class )
		);

		$this->assertSame( $expected, $snakFormatter->formatSnak( $snak ) );
		$this->assertSame( SnakFormatter::FORMAT_WIKI, $snakFormatter->getFormat() );
	}

}
