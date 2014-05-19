<?php

namespace Wikibase\ChangeOp;

use InvalidArgumentException;
use ValueValidators\Result;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\Term;
use Wikibase\Summary;
use Wikibase\Validators\TermValidatorFactory;

/**
 * Class for label change operation
 *
 * @since 0.4
 * @licence GNU GPL v2+
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 * @author Daniel Kinzler
 */
class ChangeOpLabel extends ChangeOpBase {

	/**
	 * @since 0.4
	 *
	 * @var string
	 */
	private $languageCode;

	/**
	 * @since 0.4
	 *
	 * @var string|null
	 */
	private $label;

	/**
	 * @since 0.5
	 *
	 * @var TermValidatorFactory
	 */
	private $termValidatorFactory;

	/**
	 * @since 0.5
	 *
	 * @param string $languageCode
	 * @param string|null $label
	 * @param TermValidatorFactory $termValidatorFactory
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct(
		$languageCode,
		$label,
		TermValidatorFactory $termValidatorFactory
	) {
		if ( !is_string( $languageCode ) ) {
			throw new InvalidArgumentException( 'Language code needs to be a string.' );
		}

		$this->languageCode = $languageCode;
		$this->label = $label;
		$this->termValidatorFactory = $termValidatorFactory;
	}

	/**
	 * @param Fingerprint $fingerprint
	 *
	 * @return Fingerprint
	 */
	private function getUpdatedFingerprint( Fingerprint $fingerprint ) {
		$fingerprint = clone $fingerprint;

		if ( $this->label === null ) {
			$fingerprint->removeLabel( $this->languageCode );
		} else {
			$fingerprint->setLabel( new Term( $this->languageCode, $this->label ) );
		}

		return $fingerprint;
	}

	/**
	 * @see ChangeOp::apply()
	 *
	 * @param Entity $entity
	 * @param Summary $summary
	 *
	 * @return bool
	 */
	public function apply( Entity $entity, Summary $summary = null ) {
		$fingerprint = $entity->getFingerprint();

		if ( $fingerprint->getLabels()->hasTermForLanguage( $this->languageCode ) ) {
			if ( $this->label === null ) {
				$oldLabel = $fingerprint->getLabel( $this->languageCode )->getText();
				$this->updateSummary( $summary, 'remove', $this->languageCode, $oldLabel );
			} else {
				$this->updateSummary( $summary, 'set', $this->languageCode, $this->label );
			}
		} else {
			$this->updateSummary( $summary, 'add', $this->languageCode, $this->label );
		}

		$fingerprint = $this->getUpdatedFingerprint( $fingerprint );
		$entity->setFingerprint( $fingerprint );

		return true;
	}

	/**
	 * Validates this ChangeOp
	 *
	 * @see ChangeOp::validate()
	 *
	 * @since 0.5
	 *
	 * @param Entity $entity
	 *
	 * @return Result
	 */
	public function validate( Entity $entity ) {
		$languageValidator = $this->termValidatorFactory->getLanguageValidator();
		$termValidator = $this->termValidatorFactory->getLabelValidator( $entity->getType() );
		$fingerprintValidator = $this->termValidatorFactory->getFingerprintValidator( $entity->getType() );

		// check that the language is valid
		$result = $languageValidator->validate( $this->languageCode );

		if ( $result->isValid() && $this->label !== null ) {
			// Check that the new label is valid
			$result = $termValidator->validate( $this->label );
		}

		if ( !$result->isValid() ) {
			return $result;
		}

		// Check if the new fingerprint of the entity is valid (e.g. if the label is unique)
		$fingerprint = $entity->getFingerprint();
		$fingerprint = $this->getUpdatedFingerprint( $fingerprint );

		$result = $fingerprintValidator->validateFingerprint(
			$fingerprint,
			$entity->getId(),
			array( $this->languageCode )
		);

		return $result;
	}

}
