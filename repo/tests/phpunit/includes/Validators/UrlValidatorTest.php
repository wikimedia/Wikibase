<?php

namespace Wikibase\Test\Repo\Validators;

use Wikibase\Repo\Validators\RegexValidator;
use Wikibase\Repo\Validators\UrlValidator;
use Wikibase\Repo\Validators\ValidatorErrorLocalizer;

/**
 * @covers Wikibase\Repo\Validators\UrlValidator
 *
 * @license GPL 2+
 *
 * @group WikibaseRepo
 * @group Wikibase
 * @group WikibaseValidators
 * @group Database
 *
 * @author Daniel Kinzler
 */
class UrlValidatorTest extends \MediaWikiTestCase {

	/**
	 * @dataProvider invalidConstructorArgumentProvider
	 */
	public function testInvalidConstructorArgument( array $validators ) {
		$this->setExpectedException( 'InvalidArgumentException' );
		new UrlValidator( $validators );
	}

	public function invalidConstructorArgumentProvider() {
		return array(
			array( array( new RegexValidator( '/.*/' ) ) ),
			array( array( 'scheme' => '/.*/' ) ),
		);
	}

	public function testGivenNonString_validateFails() {
		$validator = new UrlValidator( array() );
		$this->setExpectedException( 'InvalidArgumentException' );
		$validator->validate( null );
	}

	public function provideValidate() {
		$yes = new RegexValidator( '/.*/', false, 'oops' );
		$no  = new RegexValidator( '/.*/', true, 'bad-url' );

		return array(
			'no scheme' => array( array(), '', 'bad-url' ),
			'empty' => array( array(), 'http://acme.com', 'bad-url-scheme' ),
			'valid' => array( array( 'http' => $yes ), 'http://acme.com', null ),
			'invalid' => array( array( 'http' => $no ), 'http://acme.com', 'bad-url' ),
			'wildcard' => array( array( '*' => $yes ), 'http://acme.com', null ),
		);
	}

	/**
	 * @dataProvider provideValidate()
	 */
	public function testValidate( $schemes, $value, $expectedErrorCode ) {
		$validator = new UrlValidator( $schemes );
		$result = $validator->validate( $value );

		if ( $expectedErrorCode === null ) {
			$this->assertTrue( $result->isValid(), 'isValid' );
		} else {
			$this->assertFalse( $result->isValid(), 'isValid' );

			$errors = $result->getErrors();
			$this->assertCount( 1, $errors );
			$this->assertEquals( $expectedErrorCode, $errors[0]->getCode(), 'error code' );

			$localizer = new ValidatorErrorLocalizer();
			$msg = $localizer->getErrorMessage( $errors[0] );
			$this->assertTrue( $msg->exists(), 'message: ' . $msg );
		}
	}

}
