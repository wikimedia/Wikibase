<?php

namespace Wikibase\Repo\Tests\Localizer;

use InvalidArgumentException;
use Message;
use PHPUnit4And6Compat;
use Wikibase\Repo\ChangeOp\Deserialization\ChangeOpDeserializationException;
use Wikibase\Repo\Localizer\ChangeOpDeserializationExceptionLocalizer;

/**
 * @covers Wikibase\Repo\Localizer\ChangeOpDeserializationExceptionLocalizer
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class ChangeOpDeserializationExceptionLocalizerTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	public function testGivenExceptionOfOtherType_hasExceptionMessageReturnsFalse() {
		$localizer = new ChangeOpDeserializationExceptionLocalizer();

		$this->assertFalse( $localizer->hasExceptionMessage( new InvalidArgumentException( 'foo' ) ) );
	}

	public function testGivenExceptionOfOtherType_getExceptionMessageThrowsException() {
		$localizer = new ChangeOpDeserializationExceptionLocalizer();

		$this->setExpectedException( InvalidArgumentException::class );

		$localizer->getExceptionMessage( new InvalidArgumentException( 'foo' ) );
	}

	private function getExceptionWithoutLocalizableMessage() {
		return new ChangeOpDeserializationException( 'Foooo', 'some-error' );
	}

	public function testGivenExceptionAndNoLocalizableMessageExists_hasExceptionMessageReturnsFalse() {
		$localizer = new ChangeOpDeserializationExceptionLocalizer();

		$this->assertFalse( $localizer->hasExceptionMessage( $this->getExceptionWithoutLocalizableMessage() ) );
	}

	public function testGivenExceptionAndNoLocalizableMessageExists_getExceptionMessageThrowsException() {
		$localizer = new ChangeOpDeserializationExceptionLocalizer();

		$this->setExpectedException( InvalidArgumentException::class );

		$localizer->getExceptionMessage( $this->getExceptionWithoutLocalizableMessage() );
	}

	private function getExceptionWithLocalizableMessage() {
		return new ChangeOpDeserializationException( 'Foooo', 'no-external-page', [ 'foowiki', 'Random page' ] );
	}

	public function testGivenExceptionAndLocalizableMessageExists_hasExceptionMessageReturnsTrue() {
		$localizer = new ChangeOpDeserializationExceptionLocalizer();

		$this->assertTrue( $localizer->hasExceptionMessage( $this->getExceptionWithLocalizableMessage() ) );
	}

	public function testGivenExceptionAndLocalizableMessageExists_getExceptionMessageReturnsIt() {
		$localizer = new ChangeOpDeserializationExceptionLocalizer();

		$this->assertEquals(
			new Message( 'wikibase-api-no-external-page', [ 'foowiki', 'Random page' ] ),
			$localizer->getExceptionMessage( $this->getExceptionWithLocalizableMessage() )
		);
	}

}
