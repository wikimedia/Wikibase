<?php

namespace Wikibase\Repo\Tests\ChangeOp;

use InvalidArgumentException;
use Wikibase\ChangeOp\ChangeOp;
use Wikibase\ChangeOp\SiteLinkChangeOpFactory;
use Wikibase\DataModel\Entity\ItemId;

/**
 * @covers Wikibase\ChangeOp\SiteLinkChangeOpFactory
 *
 * @group Wikibase
 * @group ChangeOp
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class SiteLinkChangeOpFactoryTest extends \PHPUnit_Framework_TestCase {

	public function provideInvalidBadgeItemIdList() {
		return [
			[ [ 123 ] ],
			[ [ null ] ],
			[ [ new ItemId( 'Q123' ) ] ],
		];
	}

	/**
	 * @dataProvider provideInvalidBadgeItemIdList
	 */
	public function testGivenInvalidAllowedBadgeItemList_constructorThrowsException( array $allowedBadgeItemIds ) {
		$this->setExpectedException( InvalidArgumentException::class );

		new SiteLinkChangeOpFactory( $allowedBadgeItemIds );
	}

	/**
	 * @param string[] $allowedBadgeItemIds
	 *
	 * @return SiteLinkChangeOpFactory
	 */
	private function newChangeOpFactory( $allowedBadgeItemIds = [] ) {
		return new SiteLinkChangeOpFactory( $allowedBadgeItemIds );
	}

	public function testNewSetSiteLinkOpReturnsChangeOpInstance() {
		$op = $this->newChangeOpFactory()->newSetSiteLinkOp( 'enwiki', 'Foo' );
		$this->assertInstanceOf( ChangeOp::class, $op );
	}

	public function testNewRemoveSiteLinkOpReturnsChangeOpInstance() {
		$op = $this->newChangeOpFactory()->newRemoveSiteLinkOp( 'enwiki' );
		$this->assertInstanceOf( ChangeOp::class, $op );
	}

	public function testGivenBadgesIsNotListOfItemIds_exceptionIsThrown() {
		$factory = new SiteLinkChangeOpFactory( [ 'Q100' ] );

		$this->setExpectedException( InvalidArgumentException::class );

		$factory->newSetSiteLinkOp( 'enwiki', 'foo', [ 'Q500' ] );
	}

	public function testGivenBadgeNotInTheAllowedList_exceptionIsThrown() {
		$factory = new SiteLinkChangeOpFactory( [ 'Q100' ] );

		$this->setExpectedException( InvalidArgumentException::class );

		$factory->newSetSiteLinkOp( 'enwiki', 'foo', [ new ItemId( 'Q500' ) ] );
	}

	public function testGivenBadgesInTheAllowedList_changeOpIsConstructed() {
		$factory = new SiteLinkChangeOpFactory( [ 'Q1', 'Q2', 'Q4' ] );

		$this->assertInstanceOf(
			ChangeOp::class,
			$factory->newSetSiteLinkOp( 'enwiki', 'foo', [ new ItemId( 'Q1' ), new ItemId( 'Q4' ) ] )
		);
	}

}
