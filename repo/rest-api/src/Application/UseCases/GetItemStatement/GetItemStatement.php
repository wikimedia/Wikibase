<?php declare( strict_types=1 );

namespace Wikibase\Repo\RestApi\Application\UseCases\GetItemStatement;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\DataModel\Services\Statement\StatementGuidParser;
use Wikibase\Repo\RestApi\Application\UseCases\GetLatestItemRevisionMetadata;
use Wikibase\Repo\RestApi\Application\UseCases\UseCaseError;
use Wikibase\Repo\RestApi\Domain\Services\ItemStatementRetriever;

/**
 * @license GPL-2.0-or-later
 */
class GetItemStatement {

	private ItemStatementRetriever $statementRetriever;
	private GetItemStatementValidator $validator;
	private GetLatestItemRevisionMetadata $getRevisionMetadata;

	public function __construct(
		GetItemStatementValidator $validator,
		ItemStatementRetriever $statementRetriever,
		GetLatestItemRevisionMetadata $getRevisionMetadata
	) {
		$this->statementRetriever = $statementRetriever;
		$this->getRevisionMetadata = $getRevisionMetadata;
		$this->validator = $validator;
	}

	/**
	 * @throws UseCaseError
	 */
	public function execute( GetItemStatementRequest $statementRequest ): GetItemStatementResponse {
		$this->validator->assertValidRequest( $statementRequest );

		$statementIdParser = new StatementGuidParser( new ItemIdParser() );
		$statementId = $statementIdParser->parse( $statementRequest->getStatementId() );
		$requestedItemId = $statementRequest->getItemId();
		/** @var ItemId $itemId */
		$itemId = $requestedItemId ? new ItemId( $requestedItemId ) : $statementId->getEntityId();
		'@phan-var ItemId $itemId';

		[ $revisionId, $lastModified ] = $this->getRevisionMetadata->execute( $itemId );

		if ( !$itemId->equals( $statementId->getEntityId() ) ) {
			$this->throwStatementNotFoundException( $statementRequest->getStatementId() );
		}

		$statement = $this->statementRetriever->getStatement( $statementId );
		if ( !$statement ) {
			$this->throwStatementNotFoundException( $statementRequest->getStatementId() );
		}

		return new GetItemStatementResponse( $statement, $lastModified, $revisionId );
	}

	/**
	 * @return never
	 * @throws UseCaseError
	 */
	private function throwStatementNotFoundException( string $statementId ): void {
		throw new UseCaseError(
			UseCaseError::STATEMENT_NOT_FOUND,
			"Could not find a statement with the ID: $statementId"
		);
	}
}
