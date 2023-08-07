<?php declare( strict_types=1 );

namespace Wikibase\Repo\Tests\RestApi\RouteHandlers;

use Generator;
use MediaWiki\ChangeTags\ChangeTagsStore;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\Reporter\ErrorReporter;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\Response;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWikiIntegrationTestCase;
use RuntimeException;
use Throwable;
use Wikibase\Repo\RestApi\Application\UseCases\ItemRedirect;
use Wikibase\Repo\RestApi\Application\UseCases\RemoveItemStatement\RemoveItemStatement;
use Wikibase\Repo\RestApi\Application\UseCases\UseCaseError;
use Wikibase\Repo\RestApi\RouteHandlers\RemoveStatementRouteHandler;

/**
 * @covers \Wikibase\Repo\RestApi\RouteHandlers\RemoveStatementRouteHandler
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class RemoveStatementRouteHandlerTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	protected function setUp(): void {
		parent::setUp();
		$changeTagsStore = $this->createMock( ChangeTagsStore::class );
		$changeTagsStore->method( 'listExplicitlyDefinedTags' )->willReturn( [] );
		$this->setService( 'ChangeTagsStore', $changeTagsStore );
	}

	public function testValidHttpResponse(): void {
		$this->setService( 'WbRestApi.RemoveItemStatement', $this->createStub( RemoveItemStatement::class ) );

		/** @var Response $response */
		$response = $this->newHandlerWithValidRequest()->execute();

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( [ 'application/json' ], $response->getHeader( 'Content-Type' ) );
		$this->assertSame( '"Statement deleted"', $response->getBody()->getContents() );
	}

	/**
	 * @dataProvider provideExceptionAndExpectedErrorCode
	 */
	public function testHandlesErrors( Throwable $exception, string $expectedErrorCode ): void {
		$useCase = $this->createStub( RemoveItemStatement::class );
		$useCase->method( 'execute' )->willThrowException( $exception );

		$this->setService( 'WbRestApi.RemoveItemStatement', $useCase );
		$this->setService( 'WbRestApi.ErrorReporter', $this->createStub( ErrorReporter::class ) );

		$response = $this->newHandlerWithValidRequest()->execute();
		$responseBody = json_decode( $response->getBody()->getContents() );

		$this->assertSame( [ 'en' ], $response->getHeader( 'Content-Language' ) );
		$this->assertSame( $expectedErrorCode, $responseBody->code );
	}

	public function provideExceptionAndExpectedErrorCode(): Generator {
		yield 'Error handled by ResponseFactory' => [
			new UseCaseError( UseCaseError::INVALID_STATEMENT_ID, '' ),
			UseCaseError::INVALID_STATEMENT_ID,
		];

		yield 'Item Not Found' => [
			new UseCaseError( UseCaseError::ITEM_NOT_FOUND, '' ),
			UseCaseError::STATEMENT_NOT_FOUND,
		];

		yield 'Item Redirect' => [ new ItemRedirect( 'Q123' ), UseCaseError::STATEMENT_NOT_FOUND ];

		yield 'Unexpected Error' => [ new RuntimeException(), UseCaseError::UNEXPECTED_ERROR ];
	}

	public function testReadWriteAccess(): void {
		$routeHandler = $this->newHandlerWithValidRequest();

		$this->assertTrue( $routeHandler->needsReadAccess() );
		$this->assertTrue( $routeHandler->needsWriteAccess() );
	}

	private function newHandlerWithValidRequest(): Handler {
		$routeHandler = RemoveStatementRouteHandler::factory();
		$this->initHandler(
			$routeHandler,
			new RequestData( [
				'method' => 'DELETE',
				'headers' => [
					'User-Agent' => 'PHPUnit Test',
					'Content-Type' => 'application/json',
				],
				'pathParams' => [
					RemoveStatementRouteHandler::STATEMENT_ID_PATH_PARAM => 'Q123$some-guid',
				],
				'bodyContents' => json_encode( [
					'tags' => [ 'edit', 'tags' ],
				] ),
			] )
		);
		$this->validateHandler( $routeHandler );

		return $routeHandler;
	}
}
