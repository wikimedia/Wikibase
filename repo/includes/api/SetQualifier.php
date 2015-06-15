<?php

namespace Wikibase\Api;

use ApiBase;
use ApiMain;
use Wikibase\ChangeOp\ChangeOpQualifier;
use Wikibase\ChangeOp\StatementChangeOpFactory;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\WikibaseRepo;

/**
 * API module for creating a qualifier or setting the value of an existing one.
 *
 * @since 0.3
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 */
class SetQualifier extends ModifyClaim {

	/**
	 * @var StatementChangeOpFactory
	 */
	private $statementChangeOpFactory;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param string $modulePrefix
	 */
	public function __construct( ApiMain $mainModule, $moduleName, $modulePrefix = '' ) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );

		$changeOpFactoryProvider = WikibaseRepo::getDefaultInstance()->getChangeOpFactoryProvider();
		$this->statementChangeOpFactory = $changeOpFactoryProvider->getStatementChangeOpFactory();
	}

	/**
	 * @see ApiBase::execute
	 *
	 * @since 0.3
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$this->validateParameters( $params );

		$entityId = $this->guidParser->parse( $params['claim'] )->getEntityId();
		$baseRevisionId = isset( $params['baserevid'] ) ? (int)$params['baserevid'] : null;
		$entityRevision = $this->loadEntityRevision( $entityId, $baseRevisionId );
		$entity = $entityRevision->getEntity();

		$summary = $this->modificationHelper->createSummary( $params, $this );

		$statement = $this->modificationHelper->getStatementFromEntity( $params['claim'], $entity );

		if ( isset( $params['snakhash'] ) ) {
			$this->validateQualifierHash( $statement, $params['snakhash'] );
		}

		$changeOp = $this->getChangeOp();
		$this->modificationHelper->applyChangeOp( $changeOp, $entity, $summary );

		$this->saveChanges( $entity, $summary );
		$this->getResultBuilder()->markSuccess();
		$this->getResultBuilder()->addClaim( $statement );
	}

	/**
	 * Checks if the required parameters are set and the ones that make no sense given the
	 * snaktype value are not set.
	 */
	private function validateParameters( array $params ) {
		if ( !( $this->modificationHelper->validateStatementGuid( $params['claim'] ) ) ) {
			$this->dieError( 'Invalid claim guid' , 'invalid-guid' );
		}

		if ( !isset( $params['snakhash'] ) ) {
			if ( !isset( $params['snaktype'] ) ) {
				$this->dieError( 'When creating a new qualifier (ie when not providing a snakhash) a snaktype should be specified', 'param-missing' );
			}

			if ( !isset( $params['property'] ) ) {
				$this->dieError( 'When creating a new qualifier (ie when not providing a snakhash) a property should be specified', 'param-missing' );
			}
		}

		if ( isset( $params['snaktype'] ) && $params['snaktype'] === 'value' && !isset( $params['value'] ) ) {
			$this->dieError( 'When setting a qualifier that is a PropertyValueSnak, the value needs to be provided', 'param-missing' );
		}
	}

	/**
	 * @param Statement $statement
	 * @param string $qualifierHash
	 */
	private function validateQualifierHash( Statement $statement, $qualifierHash ) {
		if ( !$statement->getQualifiers()->hasSnakHash( $qualifierHash ) ) {
			$this->dieError( "Claim does not have a qualifier with the given hash" , 'no-such-qualifier' );
		}
	}

	/**
	 * @return ChangeOpQualifier
	 */
	private function getChangeOp() {
		$params = $this->extractRequestParams();

		$guid = $params['claim'];

		$propertyId = $this->modificationHelper->getEntityIdFromString( $params['property'] );
		if ( !$propertyId instanceof PropertyId ) {
			$this->dieError(
				$propertyId->getSerialization() . ' does not appear to be a property ID',
				'param-illegal'
			);
		}
		$newQualifier = $this->modificationHelper->getSnakInstance( $params, $propertyId );

		$snakHash = isset( $params['snakhash'] ) ? $params['snakhash'] : '';
		$changeOp = $this->statementChangeOpFactory->newSetQualifierOp( $guid, $newQualifier, $snakHash );

		return $changeOp;
	}

	/**
	 * @see ApiBase::getAllowedParams
	 */
	protected function getAllowedParams() {
		return array_merge(
			array(
				'claim' => array(
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true,
				),
				'property' => array(
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => false,
				),
				'value' => array(
					ApiBase::PARAM_TYPE => 'text',
					ApiBase::PARAM_REQUIRED => false,
				),
				'snaktype' => array(
					ApiBase::PARAM_TYPE => array( 'value', 'novalue', 'somevalue' ),
					ApiBase::PARAM_REQUIRED => false,
				),
				'snakhash' => array(
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => false,
				),
			),
			parent::getAllowedParams()
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages
	 */
	protected function getExamplesMessages() {
		return array(
			'action=wbsetqualifier&claim=Q2$4554c0f4-47b2-1cd9-2db9-aa270064c9f3&property=P1&value=GdyjxP8I6XB3&snaktype=value&token=foobar' => 'apihelp-wbsetqualifier-example-1',
		);
	}

}
