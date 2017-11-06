<?php

namespace Wikibase\Repo\Api;

use ApiBase;
use ApiMain;
use DataValues\IllegalValueException;
use Deserializers\Deserializer;
use Diff\Comparer\ComparableComparer;
use Diff\Differ\OrderedListDiffer;
use InvalidArgumentException;
use LogicException;
use MediaWiki\MediaWikiServices;
use OutOfBoundsException;
use ApiUsageException;
use Wikibase\Repo\ChangeOp\StatementChangeOpFactory;
use Wikibase\ClaimSummaryBuilder;
use Wikibase\DataModel\Services\Statement\StatementGuidParser;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikibase\Repo\Diff\ClaimDiffer;
use Wikibase\Summary;

/**
 * API module for creating or updating an entire Claim.
 *
 * @license GPL-2.0+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 * @author Addshore
 */
class SetClaim extends ApiBase {

	/**
	 * @var StatementChangeOpFactory
	 */
	private $statementChangeOpFactory;

	/**
	 * @var ApiErrorReporter
	 */
	private $errorReporter;

	/**
	 * @var Deserializer
	 */
	private $statementDeserializer;

	/**
	 * @var StatementModificationHelper
	 */
	private $modificationHelper;

	/**
	 * @var StatementGuidParser
	 */
	private $guidParser;

	/**
	 * @var ResultBuilder
	 */
	private $resultBuilder;

	/**
	 * @var EntitySavingHelper
	 */
	private $entitySavingHelper;

	/**
	 * @var SetClaimRequestParser
	 */
	private $requestParser;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param ApiErrorReporter $errorReporter
	 * @param StatementModificationHelper $modificationHelper
	 * @param callable $resultBuilderInstantiator
	 * @param callable $entitySavingHelperInstantiator
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		ApiErrorReporter $errorReporter,
		SetClaimRequestParser $requestParser,
		StatementModificationHelper $modificationHelper,
		callable $resultBuilderInstantiator,
		callable $entitySavingHelperInstantiator
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->errorReporter = $errorReporter;
		$this->requestParser = $requestParser;
		$this->modificationHelper = $modificationHelper;
		$this->resultBuilder = $resultBuilderInstantiator( $this );
		$this->entitySavingHelper = $entitySavingHelperInstantiator( $this );
	}

	/**
	 * @see ApiBase::execute
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$request = $this->requestParser->parse( $params );

		$entityId = $request->getEntityId();
		$entity = $this->entitySavingHelper->loadEntity( $entityId );

		if ( !( $entity instanceof StatementListProvider ) ) {
			$this->errorReporter->dieError( 'The given entity cannot contain statements', 'not-supported' );
			throw new LogicException( 'ApiErrorReporter::dieError did not throw an exception' );
		}

		$statement = $request->getStatement();
		$summary = $this->getSummary( $params, $statement, $entity->getStatements() ); // TODO: move to servicce

		$changeop = $request->getChangeOp();
		$this->modificationHelper->applyChangeOp( $changeop, $entity, $summary );

		$status = $this->entitySavingHelper->attemptSaveEntity( $entity, $summary );
		$this->resultBuilder->addRevisionIdFromStatusToResult( $status, 'pageinfo' );
		$this->resultBuilder->markSuccess();
		$this->resultBuilder->addStatement( $statement );

		$stats = MediaWikiServices::getInstance()->getStatsdDataFactory();
		$stats->increment( 'wikibase.repo.api.wbsetclaim.total' );
		if ( isset( $params['index'] ) ) { // TODO: move to Request?
			$stats->increment( 'wikibase.repo.api.wbsetclaim.index' );
		}
	}

	/**
	 * @param array $params
	 * @param Statement $statement
	 * @param StatementList $statementList
	 *
	 * @throws InvalidArgumentException
	 * @return Summary
	 *
	 * @todo this summary builder is ugly and summary stuff needs to be refactored
	 */
	private function getSummary( array $params, Statement $statement, StatementList $statementList ) {
		$claimSummaryBuilder = new ClaimSummaryBuilder(
			$this->getModuleName(),
			new ClaimDiffer( new OrderedListDiffer( new ComparableComparer() ) )
		);

		$summary = $claimSummaryBuilder->buildClaimSummary(
			$statementList->getFirstStatementWithGuid( $statement->getGuid() ),
			$statement
		);

		if ( isset( $params['summary'] ) ) {
			$summary->setUserSummary( $params['summary'] );
		}

		return $summary;
	}

	/**
	 * @param array $params
	 *
	 * @throws IllegalValueException
	 * @throws ApiUsageException
	 * @throws LogicException
	 * @return Statement
	 */
	private function getStatementFromParams( array $params ) {
		try {
			$serializedStatement = json_decode( $params['claim'], true );
			if ( !is_array( $serializedStatement ) ) {
				throw new IllegalValueException( 'Failed to get statement from Serialization' );
			}
			$statement = $this->statementDeserializer->deserialize( $serializedStatement );
			if ( !( $statement instanceof Statement ) ) {
				throw new IllegalValueException( 'Failed to get statement from Serialization' );
			}
			return $statement;
		} catch ( InvalidArgumentException $invalidArgumentException ) {
			$this->errorReporter->dieError(
				'Failed to get claim from claim Serialization ' . $invalidArgumentException->getMessage(),
				'invalid-claim'
			);
		} catch ( OutOfBoundsException $outOfBoundsException ) {
			$this->errorReporter->dieError(
				'Failed to get claim from claim Serialization ' . $outOfBoundsException->getMessage(),
				'invalid-claim'
			);
		}

		// Note: since dieUsage() never returns, this should be unreachable!
		throw new LogicException( 'ApiErrorReporter::dieError did not throw an exception' );
	}

	/**
	 * @see ApiBase::isWriteMode
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * @see ApiBase::needsToken
	 *
	 * @return string
	 */
	public function needsToken() {
		return 'csrf';
	}

	/**
	 * @see ApiBase::getAllowedParams
	 */
	protected function getAllowedParams() {
		return array_merge(
			[
				'claim' => [
					self::PARAM_TYPE => 'text',
					self::PARAM_REQUIRED => true,
				],
				'index' => [
					self::PARAM_TYPE => 'integer',
				],
				'summary' => [
					self::PARAM_TYPE => 'string',
				],
				'token' => null,
				'baserevid' => [
					self::PARAM_TYPE => 'integer',
				],
				'bot' => false,
			],
			parent::getAllowedParams()
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages
	 */
	protected function getExamplesMessages() {
		return [
			'action=wbsetclaim&claim={"id":"Q2$5627445f-43cb-ed6d-3adb-760e85bd17ee",'
				. '"type":"claim","mainsnak":{"snaktype":"value","property":"P1",'
				. '"datavalue":{"value":"City","type":"string"}}}'
				=> 'apihelp-wbsetclaim-example-1',
			'action=wbsetclaim&claim={"id":"Q2$5627445f-43cb-ed6d-3adb-760e85bd17ee",'
				. '"type":"claim","mainsnak":{"snaktype":"value","property":"P1",'
				. '"datavalue":{"value":"City","type":"string"}}}&index=0'
				=> 'apihelp-wbsetclaim-example-2',
			'action=wbsetclaim&claim={"id":"Q2$5627445f-43cb-ed6d-3adb-760e85bd17ee",'
				. '"type":"statement","mainsnak":{"snaktype":"value","property":"P1",'
				. '"datavalue":{"value":"City","type":"string"}},'
				. '"references":[{"snaks":{"P2":[{"snaktype":"value","property":"P2",'
				. '"datavalue":{"value":"The Economy of Cities","type":"string"}}]},'
				. '"snaks-order":["P2"]}],"rank":"normal"}'
				=> 'apihelp-wbsetclaim-example-3',
		];
	}

}
