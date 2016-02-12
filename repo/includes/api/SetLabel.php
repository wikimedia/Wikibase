<?php

namespace Wikibase\Repo\Api;

use ApiMain;
use InvalidArgumentException;
use Wikibase\ChangeOp\ChangeOpLabel;
use Wikibase\ChangeOp\FingerprintChangeOpFactory;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Term\FingerprintProvider;
use Wikibase\Repo\WikibaseRepo;

/**
 * API module to set the label for a Wikibase entity.
 * Requires API write mode to be enabled.
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author John Erling Blad < jeblad@gmail.com >
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 */
class SetLabel extends ModifyTerm {

	/**
	 * @var FingerprintChangeOpFactory
	 */
	private $termChangeOpFactory;

	/**
	 * @var ApiErrorReporter
	 */
	private $errorReporter;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param string $modulePrefix
	 */
	public function __construct( ApiMain $mainModule, $moduleName, $modulePrefix = '' ) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );

		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$apiHelperFactory = $wikibaseRepo->getApiHelperFactory( $this->getContext() );
		$changeOpFactoryProvider = $wikibaseRepo->getChangeOpFactoryProvider();

		$this->errorReporter = $apiHelperFactory->getErrorReporter( $this );
		$this->termChangeOpFactory = $changeOpFactoryProvider->getFingerprintChangeOpFactory();
	}

	/**
	 * @see ModifyEntity::modifyEntity
	 */
	protected function modifyEntity( EntityDocument &$entity, array $params, $baseRevId ) {
		if ( !( $entity instanceof FingerprintProvider ) ) {
			$this->errorReporter->dieError( 'The given entity cannot contain labels', 'not-supported' );
		}

		$summary = $this->createSummary( $params );
		$language = $params['language'];

		$changeOp = $this->getChangeOp( $params );
		$this->applyChangeOp( $changeOp, $entity, $summary );

		$resultBuilder = $this->getResultBuilder();
		if ( $entity->getFingerprint()->hasLabel( $language ) ) {
			$termList = $entity->getFingerprint()->getLabels()->getWithLanguages( array( $language ) );
			$resultBuilder->addLabels( $termList, 'entity' );
		} else {
			$resultBuilder->addRemovedLabel( $language, 'entity' );
		}

		return $summary;
	}

	/**
	 * @param array $params
	 *
	 * @return ChangeOpLabel
	 */
	private function getChangeOp( array $params ) {
		$label = "";
		$language = $params['language'];

		if ( isset( $params['value'] ) ) {
			$label = $this->stringNormalizer->trimToNFC( $params['value'] );
		}

		if ( $label === "" ) {
			$op = $this->termChangeOpFactory->newRemoveLabelOp( $language );
		} else {
			$op = $this->termChangeOpFactory->newSetLabelOp( $language, $label );
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
		return array(
			'action=wbsetlabel&id=Q42&language=en&value=Wikimedia&format=jsonfm'
				=> 'apihelp-wbsetlabel-example-1',
			'action=wbsetlabel&site=enwiki&title=Earth&language=en&value=Earth'
				=> 'apihelp-wbsetlabel-example-2',
		);
	}

}
