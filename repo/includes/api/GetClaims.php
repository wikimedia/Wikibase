<?php

namespace Wikibase\Api;

use ApiBase;
use ApiMain;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementGuidParser;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikibase\Lib\ClaimGuidValidator;
use Wikibase\Lib\Serializers\ClaimSerializer;
use Wikibase\Lib\Serializers\SerializationOptions;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Repo\WikibaseRepo;

/**
 * API module for getting claims.
 *
 * @since 0.3
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Adam Shorland
 */
class GetClaims extends ApiWikibase {

	/**
	 * @var ClaimGuidValidator
	 */
	private $guidValidator;

	/**
	 * @var StatementGuidParser
	 */
	private $guidParser;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param string $modulePrefix
	 *
	 * @see ApiBase::__construct
	 */
	public function __construct( ApiMain $mainModule, $moduleName, $modulePrefix = '' ) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );

		//TODO: provide a mechanism to override the services
		$this->guidValidator = WikibaseRepo::getDefaultInstance()->getClaimGuidValidator();
		$this->guidParser = WikibaseRepo::getDefaultInstance()->getStatementGuidParser();
	}

	/**
	 * @see \ApiBase::execute
	 *
	 * @since 0.3
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$this->validateParameters( $params );

		list( $idString, $guid ) = $this->getIdentifiers( $params );

		try {
			$entityId = $this->getIdParser()->parse( $idString );
		} catch ( EntityIdParsingException $e ) {
			$this->dieException( $e, 'param-invalid' );
		}

		$entityRevision = $entityId ? $this->loadEntityRevision( $entityId, EntityRevisionLookup::LATEST_FROM_SLAVE ) : null;
		$entity = $entityRevision->getEntity();

		if ( $params['ungroupedlist'] ) {
			$this->getResultBuilder()->getOptions()
				->setOption(
					SerializationOptions::OPT_GROUP_BY_PROPERTIES,
					array()
				);
		}

		$claims = $this->getClaims( $entity, $guid );
		$this->getResultBuilder()->addClaims( $claims, null );
	}

	private function validateParameters( array $params ) {
		if ( !isset( $params['entity'] ) && !isset( $params['claim'] ) ) {
			$this->dieError( 'Either the entity parameter or the claim parameter need to be set', 'param-missing' );
		}
	}

	/**
	 * @param EntityDocument $entity
	 * @param string|null $guid
	 *
	 * @return Statement[]
	 */
	private function getClaims( EntityDocument $entity, $guid = null ) {
		if ( !( $entity instanceof StatementListProvider ) ) {
			return array();
		}

		if ( $guid === null ) {
			return $this->getMatchingStatements( $entity->getStatements() );
		}

		$statement = $entity->getStatements()->getFirstStatementWithGuid( $guid );
		return $statement === null ? array() : array( $statement );
	}

	private function getMatchingStatements( StatementList $statementList ) {
		$statements = array();

		foreach ( $statementList->toArray() as $statement ) {
			if ( $this->statementMatchesFilters( $statement ) ) {
				$statements[] = $statement;
			}
		}

		return $statements;
	}

	private function statementMatchesFilters( Statement $statement ) {
		return $this->rankMatchesFilter( $statement->getRank() )
			&& $this->propertyMatchesFilter( $statement->getPropertyId() );
	}

	private function rankMatchesFilter( $rank ) {
		if ( $rank === null ) {
			return true;
		}
		$params = $this->extractRequestParams();

		if ( isset( $params['rank'] ) ) {
			$unserializedRank = ClaimSerializer::unserializeRank( $params['rank'] );
			$matchFilter = $rank === $unserializedRank;
			return $matchFilter;
		}

		return true;
	}

	private function propertyMatchesFilter( EntityId $propertyId ) {
		$params = $this->extractRequestParams();

		if ( isset( $params['property'] ) ) {
			try {
				$parsedProperty = $this->getIdParser()->parse( $params['property'] );
			} catch ( EntityIdParsingException $e ) {
				$this->dieException( $e, 'param-invalid' );
			}

			return $propertyId->equals( $parsedProperty );
		}

		return true;
	}

	/**
	 * Obtains the id of the entity for which to obtain claims and the claim GUID
	 * in case it was also provided.
	 *
	 * @param array $params
	 *
	 * @return array
	 * First element is a prefixed entity id string.
	 * Second element is either null or a statements GUID.
	 */
	private function getIdentifiers( array $params ) {
		$guid = null;

		if ( isset( $params['claim'] ) ) {
			$guid = $params['claim'];
			$idString = $this->getEntityIdFromStatementGuid( $params['claim'] );

			if ( isset( $params['entity'] ) && $idString !== $params['entity'] ) {
				$this->dieError( 'If both entity id and claim key are provided they need to point to the same entity', 'param-illegal' );
			}
		} else {
			$idString = $params['entity'];
		}

		return array( $idString, $guid );
	}

	private function getEntityIdFromStatementGuid( $guid ) {
		if ( $this->guidValidator->validateFormat( $guid ) === false ) {
			$this->dieError( 'Invalid claim guid', 'invalid-guid' );
		}

		return $this->guidParser->parse( $guid )->getEntityId()->getSerialization();
	}

	/**
	 * @see ApiBase::getAllowedParams
	 */
	protected function getAllowedParams() {
		return array(
			'entity' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'property' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'claim' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'rank' => array(
				ApiBase::PARAM_TYPE => ClaimSerializer::getRanks(),
			),
			'props' => array(
				ApiBase::PARAM_TYPE => array(
					'references',
				),
				ApiBase::PARAM_DFLT => 'references',
			),
			'ungroupedlist' => array(
				ApiBase::PARAM_TYPE => 'boolean',
				ApiBase::PARAM_DFLT => false,
			),
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages
	 */
	protected function getExamplesMessages() {
		return array(
			"action=wbgetclaims&entity=Q42" =>
				"apihelp-wbgetclaims-example-1",
			"action=wbgetclaims&entity=Q42&property=P2" =>
				"apihelp-wbgetclaims-example-2",
			"action=wbgetclaims&entity=Q42&rank=normal" =>
				"apihelp-wbgetclaims-example-3",
			'action=wbgetclaims&claim=Q42$D8404CDA-25E4-4334-AF13-A3290BCD9C0F' =>
				'apihelp-wbgetclaims-example-4',
		);
	}

}
