<?php

namespace Wikibase\Test;

use LogicException;
use Title;
use ValueFormatters\FormatterOptions;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\EntityIdTitleFormatter;

/**
 * @covers Wikibase\Lib\EntityIdTitleFormatter
 *
 * @group Wikibase
 * @group ValueFormatters
 * @group DataValueExtensions
 * @group WikibaseDataTypes
 * @group EntityIdFormatterTest
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class EntityIdTitleFormatterTest extends \PHPUnit_Framework_TestCase {

	public function provideFormat() {
		return array(
			'ItemId' => array(
				new ItemId( 'Q23' ),
				'ITEM-TEST--Q23'
			),
			'PropertyId' => array(
				new PropertyId( 'P23' ),
				'PROPERTY-TEST--P23'
			),
			'EntityId' => array(
				new ItemId( 'q23' ),
				'ITEM-TEST--Q23'
			),
			'EntityIdValue' => array(
				new EntityIdValue( new ItemId( "Q23" ) ),
				'ITEM-TEST--Q23'
			),
		);
	}

	/**
	 * @dataProvider provideFormat
	 */
	public function testFormat( $id, $expected ) {
		$formatter = $this->newEntityIdTitleFormatter();

		$actual = $formatter->format( $id );
		$this->assertEquals( $expected, $actual );
	}

	public function getTitleForId( EntityId $entityId ) {
		switch ( $entityId->getEntityType() ) {
			case Item::ENTITY_TYPE:
				return Title::makeTitle( NS_MAIN, 'ITEM-TEST--' . $entityId->getSerialization() );
			case Property::ENTITY_TYPE:
				return Title::makeTitle( NS_MAIN, 'PROPERTY-TEST--' . $entityId->getSerialization() );
			default:
				throw new LogicException( "oops!" );
		}
	}

	protected function newEntityIdTitleFormatter() {
		$options = new FormatterOptions();
		$titleLookup = $this->getMock( 'Wikibase\Lib\Store\EntityTitleLookup' );
		$titleLookup->expects( $this->any() )->method( 'getTitleForId' )
			->will( $this->returnCallback( array( $this, 'getTitleForId' ) ) );

		$formatter = new EntityIdTitleFormatter( $options, $titleLookup );
		return $formatter;
	}

}
