<?php

namespace Wikibase\Test;

use Wikibase\ChangeOp\StatementChangeOpFactory;
use Wikibase\DataModel\Claim\Statement;
use Wikibase\DataModel\Reference;

/**
 * @covers Wikibase\ChangeOp\ItemChangeOpFactory
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group ChangeOp
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class StatementChangeOpFactoryTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @return StatementChangeOpFactory
	 */
	protected function newChangeOpFactory() {
		$mockProvider = new ChangeOpTestMockProvider( $this );

		return new StatementChangeOpFactory(
			$mockProvider->getMockSnakValidator()
		);
	}

	public function testNewSetReferenceOp() {
		$reference = new Reference();

		$op = $this->newChangeOpFactory()->newSetReferenceOp( 'DEADBEEF', $reference, '1337BABE' );
		$this->assertInstanceOf( 'Wikibase\ChangeOp\ChangeOp', $op );
	}

	public function testNewRemoveReferenceOp() {
		$op = $this->newChangeOpFactory()->newRemoveReferenceOp( 'DEADBEEF', '1337BABE' );
		$this->assertInstanceOf( 'Wikibase\ChangeOp\ChangeOp', $op );
	}


	public function testNewSetStatementRankOp() {
		$op = $this->newChangeOpFactory()->newSetStatementRankOp( 'DEADBEEF', Statement::RANK_NORMAL );
		$this->assertInstanceOf( 'Wikibase\ChangeOp\ChangeOp', $op );
	}

}
