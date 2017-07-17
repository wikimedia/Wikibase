<?php

namespace Wikibase\Repo\Api;

use ApiMain;
use Wikibase\Repo\ChangeOp\ChangeOp;
use Wikibase\Repo\ChangeOp\ChangeOpDescription;
use Wikibase\Repo\ChangeOp\FingerprintChangeOpFactory;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Term\DescriptionsProvider;
use Wikibase\Summary;

/**
 * API module for the language attributes for a Wikibase entity.
 * Requires API write mode to be enabled.
 *
 * @license GPL-2.0+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author John Erling Blad < jeblad@gmail.com >
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 */
class SetDescription extends ModifyTerm {

	/**
	 * @var FingerprintChangeOpFactory
	 */
	private $termChangeOpFactory;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param FingerprintChangeOpFactory $termChangeOpFactory
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		FingerprintChangeOpFactory $termChangeOpFactory
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->termChangeOpFactory = $termChangeOpFactory;
	}

	/**
	 * @see ModifyEntity::modifyEntity
	 *
	 * @param EntityDocument &$entity
	 * @param ChangeOp $changeOp
	 * @param array $params
	 *
	 * @return Summary
	 */
	protected function modifyEntity( EntityDocument &$entity, ChangeOp $changeOp, array $params ) {
		if ( !( $entity instanceof DescriptionsProvider ) ) {
			$this->errorReporter->dieError( 'The given entity cannot contain descriptions', 'not-supported' );
		}

		$summary = $this->createSummary( $params );
		$language = $params['language'];

		$this->applyChangeOp( $changeOp, $entity, $summary );

		$descriptions = $entity->getDescriptions();
		$resultBuilder = $this->getResultBuilder();

		if ( $descriptions->hasTermForLanguage( $language ) ) {
			$termList = $descriptions->getWithLanguages( [ $language ] );
			$resultBuilder->addDescriptions( $termList, 'entity' );
		} else {
			$resultBuilder->addRemovedDescription( $language, 'entity' );
		}

		return $summary;
	}

	/**
	 * @param array $params
	 * @param EntityDocument $entity
	 *
	 * @return ChangeOpDescription
	 */
	protected function getChangeOp( array $params, EntityDocument $entity ) {
		$description = "";
		$language = $params['language'];

		if ( isset( $params['value'] ) ) {
			$description = $this->stringNormalizer->trimToNFC( $params['value'] );
		}

		if ( $description === "" ) {
			$op = $this->termChangeOpFactory->newRemoveDescriptionOp( $language );
		} else {
			$op = $this->termChangeOpFactory->newSetDescriptionOp( $language, $description );
		}

		return $op;
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
	 * @see ApiBase::getExamplesMessages
	 */
	protected function getExamplesMessages() {
		return [
			'action=wbsetdescription&id=Q42&language=en&value=An%20encyclopedia%20that%20everyone%20can%20edit'
				=> 'apihelp-wbsetdescription-example-1',
			'action=wbsetdescription&site=enwiki&title=Wikipedia&language=en&value=An%20encyclopedia%20that%20everyone%20can%20edit'
				=> 'apihelp-wbsetdescription-example-2',
		];
	}

}
