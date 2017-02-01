<?php

namespace Wikibase\Repo\Tests\Validators;

use ValueValidators\Error;
use ValueValidators\Result;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Term\AliasGroupList;
use Wikibase\DataModel\Term\TermList;
use Wikibase\Repo\Validators\CompositeFingerprintValidator;
use Wikibase\Repo\Validators\FingerprintValidator;

/**
 * @covers Wikibase\Repo\Validators\CompositeFingerprintValidator
 *
 * @group Wikibase
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class CompositeFingerprintValidatorTest extends \PHPUnit_Framework_TestCase {

	public function validFingerprintProvider() {
		$success = Result::newSuccess();
		$failure = Result::newError( array( Error::newError( 'Foo!' ) ) );

		$good = $this->getMock( FingerprintValidator::class );
		$good->expects( $this->any() )
			->method( 'validateFingerprint' )
			->will( $this->returnValue( $success ) );

		$bad = $this->getMock( FingerprintValidator::class );
		$bad->expects( $this->any() )
			->method( 'validateFingerprint' )
			->will( $this->returnValue( $failure ) );

		return array(
			array( array( $good, $bad ), false ),
			array( array( $bad, $good ), false ),
			array( array( $good, $good ), true ),
			array( array(), true ),
		);
	}

	/**
	 * @dataProvider validFingerprintProvider
	 */
	public function testValidateFingerprint( $validators, $expected ) {
		$terms = new TermList();
		$aliasGroups = new AliasGroupList();

		$validator = new CompositeFingerprintValidator( $validators );
		$result = $validator->validateFingerprint(
			$terms,
			$terms,
			$aliasGroups,
			new ItemId( 'Q99' )
		);

		$this->assertEquals( $expected, $result->isValid(), 'isValid' );
	}

}
