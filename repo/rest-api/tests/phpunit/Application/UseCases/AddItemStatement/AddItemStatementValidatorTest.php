<?php declare( strict_types=1 );

namespace Wikibase\Repo\Tests\RestApi\Application\UseCases\AddItemStatement;

use CommentStore;
use PHPUnit\Framework\TestCase;
use Wikibase\Repo\RestApi\Application\UseCases\AddItemStatement\AddItemStatementRequest;
use Wikibase\Repo\RestApi\Application\UseCases\AddItemStatement\AddItemStatementValidator;
use Wikibase\Repo\RestApi\Application\UseCases\UseCaseError;
use Wikibase\Repo\RestApi\Application\Validation\EditMetadataValidator;
use Wikibase\Repo\RestApi\Application\Validation\ItemIdValidator;
use Wikibase\Repo\RestApi\Application\Validation\StatementValidator;
use Wikibase\Repo\RestApi\Application\Validation\ValidationError;

/**
 * @covers \Wikibase\Repo\RestApi\Application\UseCases\AddItemStatement\AddItemStatementValidator
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class AddItemStatementValidatorTest extends TestCase {

	private StatementValidator $statementValidator;
	private EditMetadataValidator $editMetadataValidator;

	protected function setUp(): void {
		parent::setUp();

		$this->statementValidator = $this->createStub( StatementValidator::class );
		$this->statementValidator->method( 'validate' )->willReturn( null );

		$this->editMetadataValidator = $this->createStub( EditMetadataValidator::class );
		$this->editMetadataValidator->method( 'validateEditTags' )->willReturn( null );
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function testValidatePass(): void {
		$this->newAddItemStatementValidator()->assertValidRequest(
			new AddItemStatementRequest( 'Q42', [ 'valid' => 'statement' ], [], false, null, null )
		);
	}

	public function testWithInvalidItemId(): void {
		$itemId = 'X123';
		try {
			$this->newAddItemStatementValidator()->assertValidRequest(
				new AddItemStatementRequest( $itemId, [ 'valid' => 'statement' ], [], false, null, null )
			);
			$this->fail( 'this should not be reached' );
		} catch ( UseCaseError $e ) {
			$this->assertSame( UseCaseError::INVALID_ITEM_ID, $e->getErrorCode() );
			$this->assertSame( 'Not a valid item ID: X123', $e->getErrorMessage() );
		}
	}

	public function testWithInvalidStatement(): void {
		$invalidStatement = [ 'this is' => 'not a valid statement' ];
		$expectedError = new ValidationError(
			StatementValidator::CODE_INVALID_FIELD,
			[
				StatementValidator::CONTEXT_FIELD_NAME => 'some-field',
				StatementValidator::CONTEXT_FIELD_VALUE => 'foo',
			]
		);
		$this->statementValidator = $this->createMock( StatementValidator::class );
		$this->statementValidator->method( 'validate' )
			->with( $invalidStatement )
			->willReturn( $expectedError );

		try {
			$this->newAddItemStatementValidator()->assertValidRequest(
				new AddItemStatementRequest( 'Q42', $invalidStatement, [], false, null, null )
			);
			$this->fail( 'this should not be reached' );
		} catch ( UseCaseError $e ) {
			$this->assertSame( UseCaseError::STATEMENT_DATA_INVALID_FIELD, $e->getErrorCode() );
			$this->assertSame( "Invalid input for 'some-field'", $e->getErrorMessage() );
			$this->assertSame(
				[ AddItemStatementValidator::CONTEXT_PATH => 'some-field', AddItemStatementValidator::CONTEXT_VALUE => 'foo' ],
				$e->getErrorContext()
			);
		}
	}

	public function testWithMissingStatementField(): void {
		$invalidStatement = [ 'this is' => 'a statement with a missing field' ];
		$expectedError = new ValidationError(
			StatementValidator::CODE_MISSING_FIELD,
			[ StatementValidator::CONTEXT_FIELD_NAME => 'some-field' ]
		);
		$this->statementValidator = $this->createMock( StatementValidator::class );
		$this->statementValidator->method( 'validate' )
			->with( $invalidStatement )
			->willReturn( $expectedError );

		try {
			$this->newAddItemStatementValidator()->assertValidRequest(
				new AddItemStatementRequest( 'Q42', $invalidStatement, [], false, null, null )
			);
			$this->fail( 'this should not be reached' );
		} catch ( UseCaseError $e ) {
			$this->assertSame( UseCaseError::STATEMENT_DATA_MISSING_FIELD, $e->getErrorCode() );
			$this->assertSame(
				'Mandatory field missing in the statement data: some-field',
				$e->getErrorMessage()
			);
			$this->assertSame( [ AddItemStatementValidator::CONTEXT_PATH => 'some-field' ], $e->getErrorContext() );
		}
	}

	public function testWithCommentTooLong(): void {
		$comment = str_repeat( 'x', CommentStore::COMMENT_CHARACTER_LIMIT + 1 );
		$expectedError = new ValidationError(
			EditMetadataValidator::CODE_COMMENT_TOO_LONG,
			[ EditMetadataValidator::CONTEXT_COMMENT_MAX_LENGTH => CommentStore::COMMENT_CHARACTER_LIMIT ]
		);

		$this->editMetadataValidator = $this->createMock( EditMetadataValidator::class );
		$this->editMetadataValidator->method( 'validateComment' )
			->with( $comment )
			->willReturn( $expectedError );

		try {
			$this->newAddItemStatementValidator()->assertValidRequest(
				new AddItemStatementRequest( 'Q42', [ 'valid' => 'statement' ], [], false, $comment, null )
			);
			$this->fail( 'this should not be reached' );
		} catch ( UseCaseError $e ) {
			$this->assertSame( UseCaseError::COMMENT_TOO_LONG, $e->getErrorCode() );
			$this->assertSame(
				'Comment must not be longer than ' . CommentStore::COMMENT_CHARACTER_LIMIT . ' characters.',
				$e->getErrorMessage()
			);
		}
	}

	public function testWithInvalidEditTags(): void {
		$invalidTags = [ 'bad', 'tags' ];
		$expectedError = new ValidationError(
			EditMetadataValidator::CODE_INVALID_TAG,
			[ EditMetadataValidator::CONTEXT_TAG_VALUE => json_encode( $invalidTags ) ]
		);

		$this->editMetadataValidator = $this->createMock( EditMetadataValidator::class );
		$this->editMetadataValidator->method( 'validateEditTags' )
			->with( $invalidTags )
			->willReturn( $expectedError );

		try {
			$this->newAddItemStatementValidator()->assertValidRequest(
				new AddItemStatementRequest( 'Q42', [ 'valid' => 'statement' ], $invalidTags, false, null, null )
			);
			$this->fail( 'this should not be reached' );
		} catch ( UseCaseError $e ) {
			$this->assertSame( UseCaseError::INVALID_EDIT_TAG, $e->getErrorCode() );
			$this->assertSame( 'Invalid MediaWiki tag: ["bad","tags"]', $e->getErrorMessage() );
		}
	}

	private function newAddItemStatementValidator(): AddItemStatementValidator {
		return new AddItemStatementValidator(
			new ItemIdValidator(),
			$this->statementValidator,
			$this->editMetadataValidator
		);
	}
}
