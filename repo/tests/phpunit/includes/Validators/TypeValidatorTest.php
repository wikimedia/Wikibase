<?php

namespace Wikibase\Test\Repo\Validators;

use DataValues\NumberValue;
use DataValues\StringValue;
use Wikibase\Repo\Validators\TypeValidator;
use Wikibase\Repo\Validators\ValidatorErrorLocalizer;

/**
 * @covers Wikibase\Repo\Validators\TypeValidator
 *
 * @group WikibaseRepo
 * @group Wikibase
 * @group WikibaseValidators
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class TypeValidatorTest extends \PHPUnit_Framework_TestCase {

	public function provideValidate() {
		return [
			[ 'integer', 1, true, "integer" ],
			[ 'integer', 1.1, false, "not an integer" ],
			[ 'object', new StringValue( "foo" ), true, "object" ],
			[ 'object', "foo", false, "not an object" ],
			[ StringValue::class, new StringValue( "foo" ), true, "StringValue" ],
			[ StringValue::class, new NumberValue( 7 ), false, "not a StringValue" ],
			[ StringValue::class, 33, false, "definitly not a StringValue" ],
		];
	}

	/**
	 * @dataProvider provideValidate()
	 */
	public function testValidate( $type, $value, $expected, $message ) {
		$validator = new TypeValidator( $type );
		$result = $validator->validate( $value );

		$this->assertEquals( $expected, $result->isValid(), $message );

		if ( !$expected ) {
			$errors = $result->getErrors();
			$this->assertCount( 1, $errors, $message );
			$this->assertEquals( 'bad-type', $errors[0]->getCode(), $message );

			$localizer = new ValidatorErrorLocalizer();
			$msg = $localizer->getErrorMessage( $errors[0] );
			$this->assertTrue( $msg->exists(), $msg );
		}
	}

}
