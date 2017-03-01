<?php

namespace Wikibase\Repo\Tests\ChangeOp;

use PHPUnit_Framework_MockObject_MockObject;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\Repo\ChangeOp\NullChangeOp;

/**
 * @covers Wikibase\Repo\ChangeOp\NullChangeOp
 *
 * @group Wikibase
 * @license GPL-2.0+
 */
class NullChangeOpTest extends \PHPUnit_Framework_TestCase {

	public function testReturnsValidResult_WhenValidatesEntityDocument() {
		/** @var EntityDocument $entityDocument */
		$entityDocument = $this->getMock( EntityDocument::class );
		$nullChangeOp = new NullChangeOp();

		$result = $nullChangeOp->validate( $entityDocument );

		$this->assertTrue( $result->isValid() );
	}

	public function testDoesNotCallAnyMethodOnEntity_WhenApplied() {
		/** @var EntityDocument|PHPUnit_Framework_MockObject_MockObject $entityDocument */
		$entityDocument = $this->getMock( EntityDocument::class );
		$nullChangeOp = new NullChangeOp();

		$this->expectNoMethodWillBeEverCalledOn( $entityDocument );
		$nullChangeOp->apply( $entityDocument );
	}

	private function expectNoMethodWillBeEverCalledOn( PHPUnit_Framework_MockObject_MockObject $entityMock ) {
		$entityMock->expects( $this->never() )->method( self::anything() );
	}

}
