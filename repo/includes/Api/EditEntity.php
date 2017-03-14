<?php

namespace Wikibase\Repo\Api;

use ApiMain;
use Deserializers\Deserializer;
use InvalidArgumentException;
use MWException;
use Title;
use ApiUsageException;
use Wikibase\ChangeOp\ChangeOp;
use Wikibase\ChangeOp\FingerprintChangeOpFactory;
use Wikibase\ChangeOp\SiteLinkChangeOpFactory;
use Wikibase\ChangeOp\StatementChangeOpFactory;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikibase\DataModel\Term\AliasesProvider;
use Wikibase\DataModel\Term\DescriptionsProvider;
use Wikibase\DataModel\Term\LabelsProvider;
use Wikibase\EntityFactory;
use Wikibase\Lib\ContentLanguages;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Repo\ChangeOp\Deserialization\ChangeOpDeserializationException;
use Wikibase\Repo\ChangeOp\EntityChangeOpProvider;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Summary;

/**
 * Derived class for API modules modifying a single entity identified by id xor a combination of
 * site and page title.
 *
 * @license GPL-2.0+
 */
class EditEntity extends ModifyEntity {

	/**
	 * @var ContentLanguages
	 */
	private $termsLanguages;

	/**
	 * @var FingerprintChangeOpFactory
	 */
	private $termChangeOpFactory;

	/**
	 * @var StatementChangeOpFactory
	 */
	private $statementChangeOpFactory;

	/**
	 * @var SiteLinkChangeOpFactory
	 */
	private $siteLinkChangeOpFactory;

	/**
	 * @var EntityRevisionLookup
	 */
	private $revisionLookup;

	/**
	 * @var Deserializer
	 */
	private $statementDeserializer;

	/**
	 * @var EntityIdParser
	 */
	private $idParser;

	/**
	 * @var EntityFactory
	 */
	private $entityFactory;

	/**
	 * @var EntityChangeOpProvider
	 */
	private $entityChangeOpProvider;

	/**
	 * @var callable[]
	 */
	private $changeOpDeserializerCallbacks;

	/**
	 * @see ModifyEntity::__construct
	 *
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param string $modulePrefix
	 *
	 * @throws MWException
	 */
	public function __construct( ApiMain $mainModule, $moduleName, $modulePrefix = '' ) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );

		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$this->termsLanguages = $wikibaseRepo->getTermsLanguages();
		$this->revisionLookup = $wikibaseRepo->getEntityRevisionLookup( 'uncached' );
		$this->idParser = $wikibaseRepo->getEntityIdParser();
		$this->entityFactory = $wikibaseRepo->getEntityFactory();
		$this->statementDeserializer = $wikibaseRepo->getExternalFormatStatementDeserializer();

		$changeOpFactoryProvider = $wikibaseRepo->getChangeOpFactoryProvider();
		$this->termChangeOpFactory = $changeOpFactoryProvider->getFingerprintChangeOpFactory();
		$this->statementChangeOpFactory = $changeOpFactoryProvider->getStatementChangeOpFactory();
		$this->siteLinkChangeOpFactory = $changeOpFactoryProvider->getSiteLinkChangeOpFactory();
		$this->entityChangeOpProvider = $wikibaseRepo->getEntityChangeOpProvider();
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
	 * @see ApiBase::isWriteMode()
	 *
	 * @return bool Always true.
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * @param EntityDocument $entity
	 *
	 * @throws InvalidArgumentException
	 * @return string[] A list of permissions
	 */
	protected function getRequiredPermissions( EntityDocument $entity ) {
		$permissions = $this->isWriteMode() ? array( 'read', 'edit' ) : array( 'read' );

		if ( !$this->entityExists( $entity->getId() ) ) {
			$permissions[] = 'createpage';

			switch ( $entity->getType() ) {
				case 'property':
					$permissions[] = $entity->getType() . '-create'; //property-create
					break;
			}
		}

		return $permissions;
	}

	/**
	 * @param EntityId $entityId
	 *
	 * @return bool
	 */
	private function entityExists( EntityId $entityId ) {
		$title = $entityId === null ? null : $this->getTitleLookup()->getTitleForId( $entityId );
		return ( $title !== null && $title->exists() );
	}

	/**
	 * @see ModifyEntity::validateParameters
	 *
	 * @param array $params
	 */
	protected function validateParameters( array $params ) {
		$hasId = isset( $params['id'] );
		$hasNew = isset( $params['new'] );
		$hasSiteLink = isset( $params['site'] ) && isset( $params['title'] );
		$hasSiteLinkPart = isset( $params['site'] ) || isset( $params['title'] );

		if ( !( $hasId xor $hasSiteLink xor $hasNew ) ) {
			$this->errorReporter->dieError(
				'Either provide the item "id" or pairs of "site" and "title" or a "new" type for'
					. ' an entity',
				'param-missing'
			);
		}
		if ( $hasId && $hasSiteLink ) {
			$this->errorReporter->dieError(
				'Parameter "id" and "site", "title" combination are not allowed to be both set in'
					. ' the same request',
				'param-illegal'
			);
		}
		if ( ( $hasId || $hasSiteLinkPart ) && $hasNew ) {
			$this->errorReporter->dieError(
				'Parameters "id", "site", "title" and "new" are not allowed to be both set in the'
					. ' same request',
				'param-illegal'
			);
		}
	}

	/**
	 * @see ModifyEntity::modifyEntity
	 *
	 * @param EntityDocument &$entity
	 * @param array $params
	 * @param int $baseRevId
	 *
	 * @return Summary
	 */
	protected function modifyEntity( EntityDocument &$entity, array $params, $baseRevId ) {
		$this->validateDataParameter( $params );
		$data = json_decode( $params['data'], true );
		$this->validateDataProperties( $data, $entity, $baseRevId );

		$exists = $this->entityExists( $entity->getId() );

		if ( $params['clear'] ) {
			if ( $params['baserevid'] && $exists ) {
				$latestRevision = $this->revisionLookup->getLatestRevisionId(
					$entity->getId(),
					EntityRevisionLookup::LATEST_FROM_MASTER
				);

				if ( !$baseRevId === $latestRevision ) {
					$this->errorReporter->dieError(
						'Tried to clear entity using baserevid of entity not equal to current revision',
						'editconflict'
					);
				}
			}

			$entity = $this->clearEntity( $entity );
		}

		// if we create a new property, make sure we set the datatype
		if ( !$exists && $entity instanceof Property ) {
			if ( !isset( $data['datatype'] ) ) {
				$this->errorReporter->dieError( 'No datatype given', 'param-illegal' );
			} else {
				$entity->setDataTypeId( $data['datatype'] );
			}
		}

		$changeOps = $this->getChangeOp( $data, $entity );

		$this->applyChangeOp( $changeOps, $entity );

		$this->buildResult( $entity );
		return $this->getSummary( $params );
	}

	/**
	 * @param EntityDocument $entity
	 *
	 * @return EntityDocument
	 */
	private function clearEntity( EntityDocument $entity ) {
		$newEntity = $this->entityFactory->newEmpty( $entity->getType() );
		$newEntity->setId( $entity->getId() );

		// FIXME how to avoid special case handling here?
		if ( $entity instanceof Property ) {
			/** @var Property $newEntity */
			$newEntity->setDataTypeId( $entity->getDataTypeId() );
		}

		return $newEntity;
	}

	/**
	 * @param array $params
	 *
	 * @return Summary
	 */
	private function getSummary( array $params ) {
		//TODO: Construct a nice and meaningful summary from the changes that get applied!
		//      Perhaps that could be based on the resulting diff?
		$summary = $this->createSummary( $params );
		if ( isset( $params['id'] ) xor ( isset( $params['site'] ) && isset( $params['title'] ) ) ) {
			$summary->setAction( $params['clear'] === false ? 'update' : 'override' );
		} else {
			$summary->setAction( 'create' );
		}
		return $summary;
	}

	/**
	 * @param array $changeRequest an array of data to apply. For example:
	 *        [ 'label' => [ 'zh' => [ 'remove' ], 'de' => [ 'value' => 'Foo' ] ] ]
	 * @param EntityDocument $entity
	 *
	 * @throws ApiUsageException
	 * @return ChangeOp
	 */
	private function getChangeOp( array $changeRequest, EntityDocument $entity ) {
		try {
			return $this->entityChangeOpProvider->newEntityChangeOp( $entity->getType(), $changeRequest );
		} catch ( ChangeOpDeserializationException $exception ) {
			$this->errorReporter->dieException( $exception, $exception->getErrorCode() );
		}
	}

	/**
	 * @param EntityDocument $entity
	 */
	private function buildResult( EntityDocument $entity ) {
		$builder = $this->getResultBuilder();

		if ( $entity instanceof LabelsProvider ) {
			$builder->addLabels( $entity->getLabels(), 'entity' );
		}

		if ( $entity instanceof DescriptionsProvider ) {
			$builder->addDescriptions( $entity->getDescriptions(), 'entity' );
		}

		if ( $entity instanceof AliasesProvider ) {
			$builder->addAliasGroupList( $entity->getAliasGroups(), 'entity' );
		}

		if ( $entity instanceof Item ) {
			$builder->addSiteLinkList( $entity->getSiteLinkList(), 'entity' );
		}

		if ( $entity instanceof StatementListProvider ) {
			$builder->addStatements( $entity->getStatements(), 'entity' );
		}
	}

	/**
	 * @param array $params
	 */
	private function validateDataParameter( array $params ) {
		if ( !isset( $params['data'] ) ) {
			$this->errorReporter->dieError( 'No data to operate upon', 'no-data' );
		}
	}

	/**
	 * @param mixed $data
	 * @param EntityDocument $entity
	 * @param int $revisionId
	 */
	private function validateDataProperties( $data, EntityDocument $entity, $revisionId = 0 ) {
		$entityId = $entity->getId();
		$title = $entityId === null ? null : $this->getTitleLookup()->getTitleForId( $entityId );

		$this->checkValidJson( $data );
		$this->checkEntityId( $data, $entityId );
		$this->checkEntityType( $data, $entity );
		$this->checkPageIdProp( $data, $title );
		$this->checkNamespaceProp( $data, $title );
		$this->checkTitleProp( $data, $title );
		$this->checkRevisionProp( $data, $revisionId );
	}

	/**
	 * @param mixed $data
	 */
	private function checkValidJson( $data ) {
		if ( is_null( $data ) ) {
			$this->errorReporter->dieError( 'Invalid json: The supplied JSON structure could not be parsed or '
				. 'recreated as a valid structure', 'invalid-json' );
		}

		// NOTE: json_decode will decode any JS literal or structure, not just objects!
		$this->assertArray( $data, 'Top level structure must be a JSON object' );

		foreach ( $data as $prop => $args ) {
			// Catch json_decode returning an indexed array (list).
			$this->assertString( $prop, 'Top level structure must be a JSON object (no keys found)' );

			if ( $prop === 'remove' ) {
				$this->errorReporter->dieError(
					'"remove" should not be a top-level key',
					'not-recognized'
				);
			}
		}
	}

	/**
	 * @param array $data
	 * @param Title|null $title
	 */
	private function checkPageIdProp( array $data, Title $title = null ) {
		if ( isset( $data['pageid'] )
			&& ( $title === null || $title->getArticleID() !== $data['pageid'] )
		) {
			$this->errorReporter->dieError(
				'Illegal field used in call, "pageid", must either be correct or not given',
				'param-illegal'
			);
		}
	}

	/**
	 * @param array $data
	 * @param Title|null $title
	 */
	private function checkNamespaceProp( array $data, Title $title = null ) {
		// not completely convinced that we can use title to get the namespace in this case
		if ( isset( $data['ns'] )
			&& ( $title === null || $title->getNamespace() !== $data['ns'] )
		) {
			$this->errorReporter->dieError(
				'Illegal field used in call: "namespace", must either be correct or not given',
				'param-illegal'
			);
		}
	}

	/**
	 * @param array $data
	 * @param Title|null $title
	 */
	private function checkTitleProp( array $data, Title $title = null ) {
		if ( isset( $data['title'] )
			&& ( $title === null || $title->getPrefixedText() !== $data['title'] )
		) {
			$this->errorReporter->dieError(
				'Illegal field used in call: "title", must either be correct or not given',
				'param-illegal'
			);
		}
	}

	/**
	 * @param array $data
	 * @param int|null $revisionId
	 */
	private function checkRevisionProp( array $data, $revisionId ) {
		if ( isset( $data['lastrevid'] )
			&& ( !is_int( $revisionId ) || $revisionId !== $data['lastrevid'] )
		) {
			$this->errorReporter->dieError(
				'Illegal field used in call: "lastrevid", must either be correct or not given',
				'param-illegal'
			);
		}
	}

	/**
	 * @param array $data
	 * @param EntityId|null $entityId
	 */
	private function checkEntityId( array $data, EntityId $entityId = null ) {
		if ( isset( $data['id'] ) ) {
			if ( !$entityId ) {
				$this->errorReporter->dieError(
					'Illegal field used in call: "id", must not be given when creating a new entity',
					'param-illegal'
				);
			}

			$dataId = $this->idParser->parse( $data['id'] );
			if ( !$entityId->equals( $dataId ) ) {
				$this->errorReporter->dieError(
					'Invalid field used in call: "id", must match id parameter',
					'param-invalid'
				);
			}
		}
	}

	/**
	 * @param array $data
	 * @param EntityDocument $entity
	 */
	private function checkEntityType( array $data, EntityDocument $entity ) {
		if ( isset( $data['type'] )
			&& $entity->getType() !== $data['type']
		) {
			$this->errorReporter->dieError(
				'Invalid field used in call: "type", must match type associated with id',
				'param-invalid'
			);
		}
	}

	/**
	 * @see ModifyEntity::getAllowedParams
	 */
	protected function getAllowedParams() {
		return array_merge(
			parent::getAllowedParams(),
			array(
				'data' => array(
					self::PARAM_TYPE => 'text',
					self::PARAM_REQUIRED => true,
				),
				'clear' => array(
					self::PARAM_TYPE => 'boolean',
					self::PARAM_DFLT => false
				),
			)
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages
	 */
	protected function getExamplesMessages() {
		return array(
			// Creating new entites
			'action=wbeditentity&new=item&data={}'
				=> 'apihelp-wbeditentity-example-1',
			'action=wbeditentity&new=item&data={"labels":{'
				. '"de":{"language":"de","value":"de-value"},'
				. '"en":{"language":"en","value":"en-value"}}}'
				=> 'apihelp-wbeditentity-example-2',
			'action=wbeditentity&new=property&data={'
				. '"labels":{"en-gb":{"language":"en-gb","value":"Propertylabel"}},'
				. '"descriptions":{"en-gb":{"language":"en-gb","value":"Propertydescription"}},'
				. '"datatype":"string"}'
				=> 'apihelp-wbeditentity-example-3',
			// Clearing entities
			'action=wbeditentity&clear=true&id=Q42&data={}'
				=> 'apihelp-wbeditentity-example-4',
			'action=wbeditentity&clear=true&id=Q42&data={'
				. '"labels":{"en":{"language":"en","value":"en-value"}}}'
				=> 'apihelp-wbeditentity-example-5',
			// Adding term
			'action=wbeditentity&id=Q42&data='
				. '{"labels":[{"language":"no","value":"Bar","add":""}]}'
				=> 'apihelp-wbeditentity-example-11',
			// Removing term
			'action=wbeditentity&id=Q42&data='
				. '{"labels":[{"language":"en","value":"Foo","remove":""}]}'
				=> 'apihelp-wbeditentity-example-12',
			// Setting stuff
			'action=wbeditentity&id=Q42&data={'
				. '"sitelinks":{"nowiki":{"site":"nowiki","title":"København"}}}'
				=> 'apihelp-wbeditentity-example-6',
			'action=wbeditentity&id=Q42&data={'
				. '"descriptions":{"nb":{"language":"nb","value":"nb-Description-Here"}}}'
				=> 'apihelp-wbeditentity-example-7',
			'action=wbeditentity&id=Q42&data={"claims":[{"mainsnak":{"snaktype":"value",'
				. '"property":"P56","datavalue":{"value":"ExampleString","type":"string"}},'
				. '"type":"statement","rank":"normal"}]}'
				=> 'apihelp-wbeditentity-example-8',
			'action=wbeditentity&id=Q42&data={"claims":['
				. '{"id":"Q42$D8404CDA-25E4-4334-AF13-A3290BCD9C0F","remove":""},'
				. '{"id":"Q42$GH678DSA-01PQ-28XC-HJ90-DDFD9990126X","remove":""}]}'
				=> 'apihelp-wbeditentity-example-9',
			'action=wbeditentity&id=Q42&data={"claims":[{'
				. '"id":"Q42$GH678DSA-01PQ-28XC-HJ90-DDFD9990126X","mainsnak":{"snaktype":"value",'
				. '"property":"P56","datavalue":{"value":"ChangedString","type":"string"}},'
				. '"type":"statement","rank":"normal"}]}'
				=> 'apihelp-wbeditentity-example-10',
		);
	}

	/**
	 * @param mixed $value
	 * @param string $message
	 */
	private function assertArray( $value, $message ) {
		$this->assertType( 'array', $value, $message );
	}

	/**
	 * @param mixed $value
	 * @param string $message
	 */
	private function assertString( $value, $message ) {
		$this->assertType( 'string', $value, $message );
	}

	/**
	 * @param string $type
	 * @param mixed $value
	 * @param string $message
	 */
	private function assertType( $type, $value, $message ) {
		if ( gettype( $value ) !== $type ) {
			$this->errorReporter->dieError( $message, 'not-recognized-' . $type );
		}
	}

}
