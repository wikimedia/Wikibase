<?php

namespace Wikibase\Client\Tests\DataAccess\PropertyParserFunction;

use DataValues\StringValue;
use Wikibase\DataAccess\PropertyParserFunction\SnaksFinder;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Test\MockRepository;

/**
 * @covers Wikibase\DataAccess\PropertyParserFunction\SnaksFinder
 *
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseDataAccess
 * @group PropertyParserFunctionTest
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class SnaksFinderTest extends \PHPUnit_Framework_TestCase {

	private function getDefaultInstance() {
		$repo = $this->newMockRepository();

		return new SnaksFinder( $repo );
	}

	private function newMockRepository() {
		$propertyId = new PropertyId( 'P1337' );

		$entityLookup = new MockRepository();

		$statement1 = new Statement( new Claim( new PropertyValueSnak(
			$propertyId,
			new StringValue( 'a kitten!' )
		) ) );
		$statement1->setGuid( 'Q42$1' );

		$statement2 = new Statement( new Claim( new PropertyValueSnak(
			$propertyId,
			new StringValue( 'two kittens!!' )
		) ) );
		$statement2->setGuid( 'Q42$2' );

		// A Statement with a lower rank which should not affect the output
		$statement3 = new Statement( new Claim( new PropertyValueSnak(
			$propertyId,
			new StringValue( 'three kittens!!!' )
		) ) );
		$statement3->setGuid( 'Q42$3' );
		$statement3->setRank( Statement::RANK_DEPRECATED );

		$item = Item::newEmpty();
		$item->setId( new ItemId( 'Q42' ) );
		$item->getStatements()->addStatement( $statement1 );
		$item->getStatements()->addStatement( $statement2 );
		$item->getStatements()->addStatement( $statement3 );

		$property = Property::newFromType( 'string' );
		$property->setId( $propertyId );
		$property->getFingerprint()->setLabel( 'en', 'a kitten!' );

		$entityLookup->putEntity( $item );
		$entityLookup->putEntity( $property );

		return $entityLookup;
	}

	/**
	 * @dataProvider findSnaksProvider
	 */
	public function testFindSnaks( $expected, ItemId $itemId, $propertyLabelOrId ) {
		$snaksFinder = $this->getDefaultInstance();

		$snakList = $snaksFinder->findSnaks( $itemId, $propertyLabelOrId, 'en' );
		$this->assertEquals( $expected, $snakList );
	}

	public function findSnaksProvider() {
		$itemId = new ItemId( 'Q42' );

		$propertyId = new PropertyId( 'P1337' );

		$snaks = array(
			new PropertyValueSnak( $propertyId, new StringValue( 'a kitten!' ) ),
			new PropertyValueSnak( $propertyId, new StringValue( 'two kittens!!' ) )
		);

		return array(
			array( $snaks, $itemId, new PropertyId( 'P1337' ) ),
			array( array(), $itemId, new PropertyId( 'P90001' ) )
		);
	}

}
