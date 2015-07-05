<?php

namespace Wikibase\Test\Api;

use Exception;
use UsageException;
use Wikibase\Api\EntityLoadHelper;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Store\BadRevisionException;
use Wikibase\Lib\Store\StorageException;
use Wikibase\Lib\Store\UnresolvedRedirectException;

/**
 * @covers Wikibase\Test\Api\EntityLoadHelper
 *
 * @group Wikibase
 * @group WikibaseAPI
 * @group WikibaseRepo
 *
 * @licence GNU GPL v2+
 * @author Adam Shorland
 */
class EntityLoadHelperTest extends \MediaWikiTestCase {

	/**
	 * @param mixed $entityRevisionReturn if value is instance of Exception it will be thrown
	 *
	 * @return \PHPUnit_Framework_MockObject_MockObject
	 */
	public function getMockEntityRevisionLookup( $entityRevisionReturn ) {
		$mock = $this->getMock( 'Wikibase\Lib\Store\EntityRevisionLookup' );
		if ( $entityRevisionReturn instanceof Exception ) {
			$mock->expects( $this->once() )
				->method( 'getEntityRevision' )
				->will( $this->throwException( $entityRevisionReturn ) );
		} else {
			$mock->expects( $this->once() )
				->method( 'getEntityRevision' )
				->will( $this->returnValue( $entityRevisionReturn ) );
		}
		return $mock;
	}

	private function getMockErrorReporter( $expectedExceptionCode = null, $expectedErrorCode = null ) {
		$mock = $this->getMockBuilder( 'Wikibase\Api\ApiErrorReporter' )
			->disableOriginalConstructor()
			->getMock();
		if ( $expectedExceptionCode ) {
			$mock->expects( $this->once() )
				->method( 'dieException' )
				->with( $this->isInstanceOf( 'Exception' ), $expectedExceptionCode )
				->will( $this->throwException( new UsageException( 'mockUsageException', 'mock' ) ) );
		}
		if ( $expectedErrorCode ) {
			$mock->expects( $this->once() )
				->method( 'dieError' )
				->with( $this->isType( 'string' ), $expectedErrorCode )
				->will( $this->throwException( new UsageException( 'mockUsageException', 'mock' ) ) );
		}
		return $mock;
	}

	public function getMockRevision() {
		return $this->getMockBuilder( 'Revision' )
			->disableOriginalConstructor()
			->getMock();
	}

	public function testRevision_returnsRevision() {
		$revision = $this->getMockRevision();
		$helper = new EntityLoadHelper(
			$this->getMockEntityRevisionLookup( $revision ),
			$this->getMockErrorReporter()
		);

		$return = $helper->loadEntityRevision( new ItemId( 'Q1' ) );

		$this->assertSame( $revision, $return );
	}

	public function testNullRevision_callsErrorReporter() {
		$helper = new EntityLoadHelper(
			$this->getMockEntityRevisionLookup( null ),
			$this->getMockErrorReporter( null, 'cant-load-entity-content' )
		);

		$this->setExpectedException( 'UsageException' );
		$helper->loadEntityRevision( new ItemId( 'Q1' ) );
	}

	public function testUnresolvedRedirectException_callsErrorReporter() {
		$helper = new EntityLoadHelper(
			$this->getMockEntityRevisionLookup( new UnresolvedRedirectException( new ItemId( 'Q1' ) ) ),
			$this->getMockErrorReporter( 'unresolved-redirect' )
		);

		$this->setExpectedException( 'UsageException' );
		$helper->loadEntityRevision( new ItemId( 'Q1' ) );
	}

	public function testBadRevisionException_callsErrorReporter() {
		$helper = new EntityLoadHelper(
			$this->getMockEntityRevisionLookup( new BadRevisionException() ),
			$this->getMockErrorReporter( 'nosuchrevid' )
		);

		$this->setExpectedException( 'UsageException' );
		$helper->loadEntityRevision( new ItemId( 'Q1' ) );
	}

	public function testStorageException_callsErrorReporter() {
		$helper = new EntityLoadHelper(
			$this->getMockEntityRevisionLookup( new StorageException() ),
			$this->getMockErrorReporter( 'cant-load-entity-content' )
		);

		$this->setExpectedException( 'UsageException' );
		$helper->loadEntityRevision( new ItemId( 'Q1' ) );
	}

}
