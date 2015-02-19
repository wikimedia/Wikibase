<?php

namespace Wikibase\Client\Tests\Scribunto;

use DataValues\DataValue;
use DataValues\StringValue;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\ReferenceList;
use Wikibase\DataModel\SiteLink;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\SnakFactory;
use Wikibase\Test\MockClientStore;
use Wikibase\Test\MockRepository;

/**
 * Helper class for Lua integration tests.
 *
 * @license GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 */
class WikibaseLuaIntegrationTestItemSetUpHelper {

	/**
	 * @var MockRepository
	 */
	protected $mockRepository;

	public function __construct() {
		$clientStore = new MockClientStore( 'de' );
		$this->mockRepository = $clientStore->getEntityLookup();
	}

	/**
	 * Sets up the test data.
	 */
	public function setUp() {
		$siteLink = new SiteLink(
			WikibaseClient::getDefaultInstance()->getSettings()->getSetting( 'siteGlobalID' ),
			'WikibaseClientLuaTest'
		);

		if ( $this->mockRepository->getEntityIdForSiteLink( $siteLink ) ) {
			// Already set up for this MockRepository
			return;
		}

		$stringProperty = $this->getTestProperty( new PropertyId( 'P342' ), 'string', 'LuaTestStringProperty' );

		$stringSnak = $this->getTestSnak(
			$stringProperty->getId(),
			new StringValue( 'Lua :)' )
		);

		$statement1 = $this->getTestStatement( $stringSnak );
		$statement1->setRank( Statement::RANK_PREFERRED );

		$qualifierSnak1 = $this->getTestSnak(
			new PropertyId( 'P342' ),
			new StringValue( 'A qualifier Snak')
		);
		$qualifierSnak2 = $this->getTestSnak(
			new PropertyId( 'P342' ),
			new StringValue( 'Moar qualifiers')
		);
		$referenceSnak = $this->getTestSnak(
			new PropertyId( 'P342' ),
			new StringValue( 'A reference')
		);

		$statement1->setQualifiers(
			new SnakList( array( $qualifierSnak1, $qualifierSnak2 ) )
		);

		$statement1->addNewReference( $referenceSnak );

		$stringProperty->getStatements()->addStatement( $statement1 );
		$this->mockRepository->putEntity( $stringProperty );

		$stringSnak2 = $this->getTestSnak(
			$stringProperty->getId(),
			new StringValue( 'This is clearly superior to the parser function' )
		);

		$statement2 = $this->getTestStatement( $stringSnak2 );
		$statement2->setRank( Statement::RANK_NORMAL );

		$siteLinks = array( $siteLink );
		$siteLinks[] = new SiteLink(
			'fooSiteId',
			'FooBarFoo'
		);

		$labels = array(
			'de' => 'Lua Test Item',
			'en' => 'Test all the code paths'
		);

		$this->createTestItem( new ItemId( 'Q32487' ), $labels, array( $statement1, $statement2 ), $siteLinks );

		$this->createTestItem( new ItemId( 'Q32488' ), array(), array( $statement1 ), array() );

		// Create another test item to test arbitrary access
		$this->createTestItem( new ItemId( 'Q199024' ), array( 'de' => 'Arbitrary access \o/' ) );

		$this->createTestItem( new ItemId( 'Q885588'), array( 'ku-latn' => 'Pisîk' ) );
	}

	/**
	 * @param PropertyId $id
	 * @param string $dataTypeId
	 * @param string $label
	 *
	 * @return Property
	 */
	private function getTestProperty( PropertyId $id, $dataTypeId, $label ) {
		$property = Property::newFromType( $dataTypeId );
		$property->setId( $id );
		$property->setLabel( 'de', $label );

		return $property;
	}

	/**
	 * @param ItemId $id
	 * @param string[] $labels
	 * @param Claim[]|null $claims
	 * @param SiteLink[]|null $siteLinks
	 *
	 * @return Item
	 */
	private function createTestItem( ItemId $id, array $labels, array $claims = null, array $siteLinks = null ) {
		$item = new Item( $id );
		$item->setLabels( $labels );

		if ( is_array( $siteLinks ) ) {
			foreach( $siteLinks as $siteLink ) {
				$item->addSiteLink( $siteLink );
			}
		}

		if ( is_array( $claims ) ) {
			foreach( $claims as $claim ) {
				$item->addClaim( $claim );
			}
		}

		$this->mockRepository->putEntity( $item );

		return $item;
	}

	/**
	 * @param PropertyId $propertyId
	 * @param DataValue $value
	 *
	 * @return Snak
	 */
	private function getTestSnak( PropertyId $propertyId, DataValue $value ) {
		$snakFactory = new SnakFactory();
		$snak = $snakFactory->newSnak( $propertyId, 'value', $value );

		return $snak;
	}

	/**
	 * @param Snak $mainSnak
	 *
	 * @return Statement
	 */
	private function getTestStatement( Snak $mainSnak ) {
		$statement = new Statement( new Claim( $mainSnak ) );
		$statement->setGuid( uniqid( 'kittens', true ) );

		return $statement;
	}

}
