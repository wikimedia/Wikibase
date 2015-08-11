<?php

namespace Wikibase\Client\Tests\DataAccess\PropertyParserFunction;

use DataValues\StringValue;
use Language;
use PHPUnit_Framework_TestCase;
use Wikibase\Client\DataAccess\PropertyIdResolver;
use Wikibase\Client\DataAccess\SnaksFinder;
use Wikibase\Client\DataAccess\StatementTransclusionInteractor;
use Wikibase\Client\PropertyLabelNotResolvedException;
use Wikibase\Client\Usage\EntityUsage;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\Lib\SnakFormatter;

/**
 * @covers Wikibase\Client\DataAccess\StatementTransclusionInteractor
 *
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseDataAccess
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Daniel Kinzler
 * @author Marius Hoch < hoo@online.de >
 */
class StatementTransclusionInteractorTest extends PHPUnit_Framework_TestCase {

	/**
	 * @param PropertyIdResolver $propertyIdResolver
	 * @param SnaksFinder $snaksFinder
	 * @param string $languageCode
	 *
	 * @return StatementTransclusionInteractor
	 */
	private function getInteractor(
		PropertyIdResolver $propertyIdResolver,
		SnaksFinder $snaksFinder,
		$languageCode
	) {
		$targetLanguage = Language::factory( $languageCode );

		return new StatementTransclusionInteractor(
			$targetLanguage,
			$propertyIdResolver,
			$snaksFinder,
			$this->getSnakFormatter(),
			$this->getEntityLookup()
		);
	}

	public function testRender() {
		$propertyId = new PropertyId( 'P1337' );
		$snaks = array(
			'Q42$1' => new PropertyValueSnak( $propertyId, new StringValue( 'a kitten!' ) ),
			'Q42$2' => new PropertyValueSnak( $propertyId, new StringValue( 'two kittens!!' ) )
		);

		$renderer = $this->getInteractor(
			$this->getPropertyIdResolver(),
			$this->getSnaksFinder( $snaks ),
			'en'
		);

		$q42 = new ItemId( 'Q42' );
		$result = $renderer->render( $q42, 'p1337' );

		$expected = 'a kitten!, two kittens!!';
		$this->assertEquals( $expected, $result );
	}

	public function testRender_PropertyLabelNotResolvedException() {
		$renderer = $this->getInteractor(
			$this->getPropertyIdResolverForPropertyNotFound(),
			$this->getSnaksFinder( array() ),
			'en'
		);

		$this->setExpectedException( 'Wikibase\Client\PropertyLabelNotResolvedException' );
		$renderer->render( new ItemId( 'Q42' ), 'blah' );
	}

	/**
	 * @param EntityUsage[] $expected
	 * @param EntityUsage[] $actual
	 * @param string $message
	 */
	private function assertSameUsages( array $expected, array $actual, $message = '' ) {
		$expected = $this->getUsageStrings( $expected );
		$actual = $this->getUsageStrings( $actual );

		$this->assertEquals( $expected, $actual, $message );
	}

	/**
	 * @param EntityUsage[] $usages
	 *
	 * @return string[]
	 */
	private function getUsageStrings( array $usages ) {
		return array_values(
			array_map( function( EntityUsage $usage ) {
				return $usage->getIdentityString();
			}, $usages )
		);
	}

	/**
	 * @param Snak[] $snaks
	 *
	 * @return SnaksFinder
	 */
	private function getSnaksFinder( array $snaks ) {
		$snaksFinder = $this->getMockBuilder(
				'Wikibase\Client\DataAccess\SnaksFinder'
			)
			->disableOriginalConstructor()
			->getMock();

		$snaksFinder->expects( $this->any() )
			->method( 'findSnaks' )
			->will( $this->returnValue( $snaks ) );

		return $snaksFinder;
	}

	private function getPropertyIdResolver() {
		$propertyIdResolver = $this->getMockBuilder(
				'Wikibase\Client\DataAccess\PropertyIdResolver'
			)
			->disableOriginalConstructor()
			->getMock();

		$propertyIdResolver->expects( $this->any() )
			->method( 'resolvePropertyId' )
			->will( $this->returnValue( new PropertyId( 'P1337' ) ) );

		return $propertyIdResolver;
	}

	private function getPropertyIdResolverForPropertyNotFound() {
		$propertyIdResolver = $this->getMockBuilder(
				'Wikibase\Client\DataAccess\PropertyIdResolver'
			)
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

	private function getEntityLookup() {
		$lookup = $this->getMock( 'Wikibase\DataModel\Services\Lookup\EntityLookup' );
		$lookup->expects( $this->any() )
			->method( 'getEntity' )
			->will( $this->returnValue(
				$this->getMock( 'Wikibase\DataModel\StatementListProvider' )
			) );

		return $lookup;
	}

	/**
	 * @return SnakFormatter
	 */
	private function getSnakFormatter() {
		$snakFormatter = $this->getMock( 'Wikibase\Lib\SnakFormatter' );

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

		return $snakFormatter;
	}

}
