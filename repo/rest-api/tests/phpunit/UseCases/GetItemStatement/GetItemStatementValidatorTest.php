<?php declare( strict_types=1 );

namespace Wikibase\Repo\Tests\RestApi\UseCases\GetItemStatement;

use Generator;
use PHPUnit\Framework\TestCase;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\Repo\RestApi\UseCases\GetItemstatement\GetItemstatementRequest;
use Wikibase\Repo\RestApi\UseCases\GetItemstatement\GetItemstatementValidator;
use Wikibase\Repo\RestApi\Validation\StatementIdValidator;

/**
 * @covers \Wikibase\Repo\RestApi\UseCases\GetItemStatement\GetItemStatementValidator
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class GetItemStatementValidatorTest extends TestCase {

	/**
	 * @dataProvider invalidStatementIdDataProvider
	 */
	public function testWithInvalidStatementId( string $statementId ): void {
		$error = $this->newStatementValidator()->validate(
			new GetItemStatementRequest( $statementId )
		);

		$this->assertSame( GetItemStatementValidator::SOURCE_STATEMENT_ID, $error->getSource() );
		$this->assertSame( $statementId, $error->getValue() );
	}

	public function invalidStatementIdDataProvider(): Generator {
		yield 'invalid format' => [ 'not-a-valid-statement-id' ];
		yield 'invalid ItemId' => [ 'X123$AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE' ];
		yield 'invalid UUID part' => [ 'Q123$INVALID-UUID-PART' ];
		yield 'statement not on an item' => [ 'P123$AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE' ];
	}

	public function testWithValidStatementId(): void {
		$this->assertNull(
			$this->newStatementValidator()->validate(
				new GetItemStatementRequest( 'Q123$AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE' )
			)
		);
	}

	private function newStatementValidator(): GetItemstatementValidator {
		return ( new GetItemstatementValidator( new StatementIdValidator( new ItemIdParser() ) ) );
	}

}
