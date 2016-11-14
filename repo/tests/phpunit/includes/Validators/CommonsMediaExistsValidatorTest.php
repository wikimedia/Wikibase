<?php

namespace Wikibase\Test\Repo\Validators;

use DataValues\StringValue;
use InvalidArgumentException;
use Wikibase\Repo\CachingCommonsMediaFileNameLookup;
use Wikibase\Repo\Validators\CommonsMediaExistsValidator;

/**
 * @covers Wikibase\Repo\Validators\CommonsMediaExistsValidator
 *
 * @group WikibaseRepo
 * @group Wikibase
 * @group WikibaseValidators
 *
 * @license GPL-2.0+
 * @author Marius Hoch
 */
class CommonsMediaExistsValidatorTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @return CachingCommonsMediaFileNameLookup
	 */
	private function getCachingCommonsMediaFileNameLookup() {
		$fileNameLookup = $this->getMockBuilder( CachingCommonsMediaFileNameLookup::class )
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
		return [
			"Valid, plain string" => [
				true, "Foo.png"
			],
			"Valid, StringValue" => [
				true, new StringValue( "Foo.png" )
			],
			"Invalid, StringValue" => [
				false, new StringValue( "Foo.NOT-FOUND.png" )
			]
		];
	}

	public function testValidate_noString() {
		$validator = new CommonsMediaExistsValidator( $this->getCachingCommonsMediaFileNameLookup() );

		$this->setExpectedException( InvalidArgumentException::class );
		$validator->validate( 5 );
	}

}
