<?php

namespace Wikibase\Repo\Tests\ChangeOp;

use PHPUnit4And6Compat;
use PHPUnit\Framework\TestCase;
use Wikibase\Repo\ChangeOp\ChangeOpApplyException;

/**
 * @covers \Wikibase\Repo\ChangeOp\ChangeOpApplyException
 *
 * @license GPL-2.0-or-later
 */
class ChangeOpApplyExceptionTest extends TestCase {
	use PHPUnit4And6Compat;

	public function testGivenConstructionParameters_availableThroughAccessors() {
		$previous = new \Exception( 'hello' );
		$exception = new ChangeOpApplyException( 'key', [ 'param1', 'param2' ], $previous );

		$this->assertSame( 'key', $exception->getKey() );
		$this->assertSame( 'key', $exception->getMessage() );

		$this->assertSame( [ 'param1', 'param2' ], $exception->getParams() );

		$this->assertSame( $previous, $exception->getPrevious() );
	}

}
