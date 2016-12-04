<?php

namespace Wikibase\Client\Tests\DataAccess\PropertyParserFunction;

use DataValues\StringValue;
use Language;
use Parser;
use ParserOutput;
use Title;
use Wikibase\Client\DataAccess\PropertyIdResolver;
use Wikibase\Client\DataAccess\PropertyParserFunction\LanguageAwareRenderer;
use Wikibase\DataModel\Services\Lookup\RestrictedEntityLookup;
use Wikibase\Client\DataAccess\SnaksFinder;
use Wikibase\Client\DataAccess\StatementTransclusionInteractor;
use Wikibase\Client\PropertyLabelNotResolvedException;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikibase\Lib\SnakFormatter;

/**
 * @covers Wikibase\Client\DataAccess\PropertyParserFunction\LanguageAwareRenderer
 *
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseDataAccess
 *
 * @license GPL-2.0+
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Daniel Kinzler
 */
class LanguageAwareRendererTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @param PropertyIdResolver $propertyIdResolver
	 * @param SnaksFinder $snaksFinder
	 * @param EntityLookup $entityLookup
	 * @param string $languageCode
	 *
	 * @return LanguageAwareRenderer
	 */
	private function getRenderer(
		PropertyIdResolver $propertyIdResolver,
		SnaksFinder $snaksFinder,
		EntityLookup $entityLookup,
		$languageCode
	) {
		$targetLanguage = Language::factory( $languageCode );

		$entityStatementsRenderer = new StatementTransclusionInteractor(
			$targetLanguage,
			$propertyIdResolver,
			$snaksFinder,
			$this->getSnakFormatter(),
			$entityLookup
		);

		return new LanguageAwareRenderer(
			$targetLanguage,
			$entityStatementsRenderer
		);
	}

	/**
	 * Return a mock ParserOutput object that checks how many times it adds a tracking category.
	 * @param $num Number of times a tracking category should be added
	 *
	 * @return ParserOutput
	 */
	private function getMockParserOutput( $num ) {
		$mockParser = $this->getMockBuilder( ParserOutput::class )
			->setMethods( [ 'addTrackingCategory' ] )
			->getMock();
		$mockParser->expects( $this->exactly( $num ) )
			->method( 'addTrackingCategory' );

		return $mockParser;
	}

	public function testRender() {
		$propertyId = new PropertyId( 'P1337' );
		$snaks = array(
			'Q42$1' => new PropertyValueSnak( $propertyId, new StringValue( 'a kitten!' ) ),
			'Q42$2' => new PropertyValueSnak( $propertyId, new StringValue( 'two kittens!!' ) )
		);

		$renderer = $this->getRenderer(
			$this->getPropertyIdResolver(),
			$this->getSnaksFinder( $snaks ),
			$this->getEntityLookup( 100 ),
			'en'
		);

		$q42 = new ItemId( 'Q42' );
		$result = $renderer->render( $q42, 'p1337', $this->getMockParserOutput( 0 ), $this->getMock( Title::class ) );

		$expected = 'a kitten!, two kittens!!';
		$this->assertEquals( $expected, $result );
	}

	public function testRenderForPropertyNotFound() {
		$mockParser = $this->getMockParserOutput( 1 );
		$renderer = $this->getRenderer(
			$this->getPropertyIdResolverForPropertyNotFound(),
			$this->getSnaksFinder( array() ),
			$this->getEntityLookup( 100 ),
			'qqx'
		);

		$result = $renderer->render( new ItemId( 'Q4' ), 'invalidLabel', $mockParser, $this->getMock( Title::class ) );

		$this->assertRegExp(
			'/<(?:strong|span|p|div)\s(?:[^\s>]*\s+)*?class="(?:[^"\s>]*\s+)*?error(?:\s[^">]*)?"/',
			$result
		);

		$this->assertRegExp(
			'/wikibase-property-render-error.*invalidLabel.*qqx/',
			$result
		);
	}

	public function testRender_exceededEntityAccessLimit() {
		$renderer = $this->getRenderer(
			$this->getPropertyIdResolver(),
			$this->getSnaksFinder( array() ),
			$this->getEntityLookup( 1 ),
			'qqx'
		);

		$renderer->render( new ItemId( 'Q3' ), 'tooManyEntities', $this->getMockParserOutput( 0 ), $this->getMock( Title::class ) );
		$result = $renderer->render( new ItemId( 'Q4' ), 'tooManyEntities', $this->getMockParserOutput( 0 ), $this->getMock( Title::class ) );

		$this->assertRegExp(
			'/<(?:strong|span|p|div)\s(?:[^\s>]*\s+)*?class="(?:[^"\s>]*\s+)*?error(?:\s[^">]*)?"/',
			$result
		);

		$this->assertRegExp(
			'/wikibase-property-render-error.*tooManyEntities.*/',
			$result
		);
	}

	/**
	 * @param Snak[] $snaks
	 *
	 * @return SnaksFinder
	 */
	private function getSnaksFinder( array $snaks ) {
		$snaksFinder = $this->getMockBuilder( SnaksFinder::class )
			->disableOriginalConstructor()
			->getMock();

		$snaksFinder->expects( $this->any() )
			->method( 'findSnaks' )
			->will( $this->returnValue( $snaks ) );

		return $snaksFinder;
	}

	/**
	 * @return PropertyIdResolver
	 */
	private function getPropertyIdResolver() {
		$propertyIdResolver = $this->getMockBuilder( PropertyIdResolver::class )
			->disableOriginalConstructor()
			->getMock();

		$propertyIdResolver->expects( $this->any() )
			->method( 'resolvePropertyId' )
			->will( $this->returnValue( new PropertyId( 'P1337' ) ) );

		return $propertyIdResolver;
	}

	/**
	 * @return PropertyIdResolver
	 */
	private function getPropertyIdResolverForPropertyNotFound() {
		$propertyIdResolver = $this->getMockBuilder( PropertyIdResolver::class )
			->disableOriginalConstructor()
			->getMock();

		$propertyIdResolver->expects( $this->any() )
			->method( 'resolvePropertyId' )
			->will( $this->returnCallback( function( $propertyLabelOrId, $languageCode ) {
				throw new PropertyLabelNotResolvedException( $propertyLabelOrId, $languageCode );
			} )
		);

		return $propertyIdResolver;
	}

	/**
	 * @param int $entityAccessLimit
	 *
	 * @return EntityLookup
	 */
	private function getEntityLookup( $entityAccessLimit ) {
		$lookup = $this->getMock( EntityLookup::class );
		$lookup->expects( $this->any() )
			->method( 'getEntity' )
			->will( $this->returnValue( $this->getMock( StatementListProvider::class ) ) );

		return new RestrictedEntityLookup( $lookup, $entityAccessLimit );
	}

	/**
	 * @return SnakFormatter
	 */
	private function getSnakFormatter() {
		$snakFormatter = $this->getMock( SnakFormatter::class );

		$snakFormatter->expects( $this->any() )
			->method( 'formatSnak' )
			->will( $this->returnCallback(
				function ( Snak $snak ) {
					if ( $snak instanceof PropertyValueSnak ) {
						$value = $snak->getDataValue();
						if ( $value instanceof StringValue ) {
							return $value->getValue();
						} elseif ( $value instanceof EntityIdValue ) {
							return $value->getEntityId()->getSerialization();
						} else {
							return '(' . $value->getType() . ')';
						}
					} else {
						return '(' . $snak->getType() . ')';
					}
				}
			) );

		$snakFormatter->expects( $this->any() )
			->method( 'getFormat' )
			->will( $this->returnValue( SnakFormatter::FORMAT_PLAIN ) );

		return $snakFormatter;
	}

}
