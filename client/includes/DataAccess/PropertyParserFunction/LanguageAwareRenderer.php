<?php

namespace Wikibase\Client\DataAccess\PropertyParserFunction;

use InvalidArgumentException;
use Language;
use Parser;
use Status;
use Wikibase\Client\DataAccess\StatementTransclusionInteractor;
use Wikibase\Client\PropertyLabelNotResolvedException;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Services\Lookup\EntityAccessLimitException;

/**
 * StatementGroupRenderer of the {{#property}} parser function.
 *
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 * @author Liangent < liangent@gmail.com >
 * @author Marius Hoch < hoo@online.de >
 */
class LanguageAwareRenderer implements StatementGroupRenderer {

	/**
	 * @var Language
	 */
	private $language;

	/**
	 * @var Parser
	 */
	private $parser;

	/**
	 * @var StatementTransclusionInteractor
	 */
	private $statementTransclusionInteractor;

	/**
	 * @param Language $language
	 * @param StatementTransclusionInteractor $statementTransclusionInteractor
	 * @param Parser $parser
	 */
	public function __construct(
		Language $language,
		StatementTransclusionInteractor $statementTransclusionInteractor
	) {
		$this->language = $language;
		$this->statementTransclusionInteractor = $statementTransclusionInteractor;
	}

	/**
	 * @param Parser $parser
	 */
	public function setParser( $parser ) {
		$this->parser = $parser;
	}


	/**
	 * @param EntityId $entityId
	 * @param string $propertyLabelOrId property label or ID (pXXX)
	 *
	 * @return string
	 */
	public function render( EntityId $entityId, $propertyLabelOrId ) {
		try {
			$status = Status::newGood(
				$this->statementTransclusionInteractor->render(
					$entityId,
					$propertyLabelOrId
				)
			);
		} catch ( PropertyLabelNotResolvedException $ex ) {
			// @fixme use ExceptionLocalizer
			if ( isset( $this->parser ) ) {
				$this->parser->addTrackingCategory( 'unresolved-property-category' );
			}
			$status = $this->getStatusForException( $propertyLabelOrId, $ex->getMessage() );
		} catch ( EntityAccessLimitException $ex ) {
			$status = $this->getStatusForException( $propertyLabelOrId, $ex->getMessage() );
		} catch ( InvalidArgumentException $ex ) {
			$status = $this->getStatusForException( $propertyLabelOrId, $ex->getMessage() );
		}

		if ( !$status->isGood() ) {
			$error = $status->getMessage()->inLanguage( $this->language )->text();
			return '<p class="error wikibase-error">' . $error . '</p>';
		}

		return $status->getValue();
	}

	/**
	 * @param string $propertyLabel
	 * @param string $message
	 *
	 * @return Status
	 */
	private function getStatusForException( $propertyLabel, $message ) {
		return Status::newFatal(
			'wikibase-property-render-error',
			$propertyLabel,
			$message
		);
	}

}
