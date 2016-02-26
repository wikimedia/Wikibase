<?php

namespace Wikibase\Test\Repo\Validators;

use DataValues\StringValue;
use Wikibase\Repo\Validators\CommonsMediaExistsValidator;

/**
 * @covers Wikibase\Repo\Validators\CommonsMediaExistsValidator
 *
 * @group WikibaseRepo
 * @group Wikibase
 * @group WikibaseValidators
 *
 * @licence GNU GPL v2+
 * @author Marius Hoch
 */
class CommonsMediaExistsValidatorTest extends \PHPUnit_Framework_TestCase {

	private function getCachingCommonsMediaFileNameLookup() {
		$fileNameLookup = $this->getMockBuilder( 'Wikibase\Repo\CachingCommonsMediaFileNameLookup' )
			->disableOriginalConstructor()
			->getMock();

		$fileNameLookup->expects( $this->any() )
			->method( 'lookupFileName' )
			->with( $this->isType( 'string' ) )
			->will( $this->returnCallback( function( $fileName ) {
				return strpos( $fileName, 'NOT-FOUND' ) === false ? $fileName : null;
			} ) );

		return $fileNameLookup;
	}

	/**
	 * @dataProvider provideValidate()
	 */
	public function testValidate( $expected, $value ) {
		$validator = new CommonsMediaExistsValidator( $this->getCachingCommonsMediaFileNameLookup() );

		$this->assertSame(
			$expected,
			$validator->validate( $value )->isValid()
		);
	}

	public function provideValidate() {
		return array(
			"Valid, plain string" => array(
				true, "Foo.png"
			),
			"Valid, StringValue" => array(
				true, new StringValue( "Foo.png" )
			),
			"Invalid, StringValue" => array(
				false, new StringValue( "Foo.NOT-FOUND.png" )
			)
		);
	}

	public function testValidate_noString() {
		$validator = new CommonsMediaExistsValidator( $this->getCachingCommonsMediaFileNameLookup() );

		$this->setExpectedException( 'InvalidArgumentException' );
		$validator->validate( 5 );
	}

	public function testSetOptions() {
		$validator = new CommonsMediaExistsValidator( $this->getCachingCommonsMediaFileNameLookup() );
		$validator->setOptions( array() );
	}

}
