<?php

namespace Wikibase\Client\Tests\DataAccess;

use DataValues\StringValue;
use Wikibase\DataAccess\PropertyIdResolver;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Test\MockPropertyLabelResolver;
use Wikibase\Test\MockRepository;

/**
 * @covers Wikibase\DataAccess\PropertyIdResolver
 *
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseDataAccess
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class PropertyIdResolverTest extends \PHPUnit_Framework_TestCase {

	private function getPropertyIdResolver() {
		$mockRepository = $this->getMockRepository();
		$propertyLabelResolver = new MockPropertyLabelResolver( 'en', $mockRepository );

		return new PropertyIdResolver( $propertyLabelResolver );
	}

	private function getMockRepository() {
		$propertyId = new PropertyId( 'P1337' );

		$property = Property::newFromType( 'string' );
		$property->setId( $propertyId );
		$property->setLabel( 'en', 'a kitten!' );

		$mockRepository = new MockRepository();
		$mockRepository->putEntity( $property );

		return $mockRepository;
	}

	/**
	 * @dataProvider resolvePropertyIdProvider
	 */
	public function testResolvePropertyId( $expected, $propertyLabelOrId ) {
		$propertyIdResolver = $this->getPropertyIdResolver();

		$propertyId = $propertyIdResolver->resolvePropertyId( $propertyLabelOrId, 'en' );
		$this->assertEquals( $expected, $propertyId );
	}

	public function resolvePropertyIdProvider() {
		return array(
			array( new PropertyId( 'P1337' ), 'a kitten!' ),
			array( new PropertyId( 'P1337' ), 'p1337' ),
			array( new PropertyId( 'P1337' ), 'P1337' ),
			array( new PropertyId( 'P1444' ), 'P1444' )
		);
	}

	/**
	 * @dataProvider resolvePropertyIdWithInvalidInput_throwsExceptionProvider
	 */
	public function testResolvePropertyIdWithInvalidInput_throwsException( $propertyIdOrLabel ) {
		$propertyIdResolver = $this->getPropertyIdResolver();

		$this->setExpectedException( 'Wikibase\Lib\PropertyLabelNotResolvedException' );

		$propertyIdResolver->resolvePropertyId( $propertyIdOrLabel, 'en' );
	}

	public function resolvePropertyIdWithInvalidInput_throwsExceptionProvider() {
		return array(
			array( 'hedgehog' ),
			array( 'Q100' )
		);
	}

}
