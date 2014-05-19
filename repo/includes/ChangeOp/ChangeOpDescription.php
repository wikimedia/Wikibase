<?php

namespace Wikibase\ChangeOp;

use InvalidArgumentException;
use ValueValidators\Result;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermList;
use Wikibase\Summary;
use Wikibase\Validators\TermValidatorFactory;

/**
 * Class for description change operation
 *
 * @since 0.4
 * @licence GNU GPL v2+
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 * @author Daniel Kinzler
 */
class ChangeOpDescription extends ChangeOpBase {

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
	private $description;

	/**
	 * @since 0.5
	 *
	 * @var TermValidatorFactory
	 */
	private $termValidatorFactory;

	/**
	 * @since 0.4
	 *
	 * @param string $languageCode
	 * @param string|null $description
	 * @param TermValidatorFactory $termValidatorFactory
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct(
		$languageCode,
		$description,
		TermValidatorFactory $termValidatorFactory
	) {
		if ( !is_string( $languageCode ) ) {
			throw new InvalidArgumentException( 'Language code needs to be a string.' );
		}

		$this->languageCode = $languageCode;
		$this->description = $description;
		$this->termValidatorFactory = $termValidatorFactory;
	}

	/**
	 * @param Fingerprint $fingerprint
	 *
	 * @returns Fingerprint
	 */
	private function getUpdatedFingerprint( Fingerprint $fingerprint ) {
		$fingerprint = clone $fingerprint;

		if ( $this->description === null ) {
			$fingerprint->removeDescription( $this->languageCode );
		} else {
			$fingerprint->setDescription( new Term( $this->languageCode, $this->description ) );
		}

		return $fingerprint;
	}

	/**
	 * @see ChangeOp::apply()
	 */
	public function apply( Entity $entity, Summary $summary = null ) {
		$fingerprint = $entity->getFingerprint();

		if ( $fingerprint->getDescriptions()->hasTermForLanguage( $this->languageCode ) ) {
			if ( $this->description === null ) {
				$removedDescription = $fingerprint->getDescription( $this->languageCode )->getText();
				$this->updateSummary( $summary, 'remove', $this->languageCode, $removedDescription );
			} else {
				$this->updateSummary( $summary, 'set', $this->languageCode, $this->description );
			}
		} else {
			$this->updateSummary( $summary, 'add', $this->languageCode, $this->description );
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
		$termValidator = $this->termValidatorFactory->getDescriptionValidator( $entity->getType() );
		$fingerprintValidator = $this->termValidatorFactory->getFingerprintValidator( $entity->getType() );

		// check that the language is valid
		$result = $languageValidator->validate( $this->languageCode );

		if ( $result->isValid() && $this->description !== null ) {
			// Check that the new description is valid
			$result = $termValidator->validate( $this->description );
		}

		if ( !$result->isValid() ) {
			return $result;
		}

		// Check if the new fingerprint of the entity is valid (e.g. if the combination
		// of label and description  is still unique)
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
