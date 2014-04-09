<?php

namespace Wikibase;

use Diff\DiffOp\Diff\Diff;
use Status;
use ValueValidators\ValueValidator;
use Wikibase\ChangeOp\ChangeOpValidationException;
use Wikibase\Validators\TermValidatorFactory;
use Wikibase\Validators\ValidatorErrorLocalizer;

/**
 * Encapsulates programmatic checks to perform before checking an item.
 *
 * @todo: Factor ChangeValidation into ChangeOps.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class PreSaveChecks {

	/**
	 * @param TermDuplicateDetector $duplicateDetector
	 * @param TermValidatorFactory $termValidatorFactory
	 * @param ValidatorErrorLocalizer $validatorErrorLocalizer
	 */
	public function __construct(
		TermDuplicateDetector $duplicateDetector,
		TermValidatorFactory $termValidatorFactory,
		ValidatorErrorLocalizer $validatorErrorLocalizer
	) {
		$this->duplicateDetector = $duplicateDetector;
		$this->termValidatorFactory = $termValidatorFactory;
		$this->validatorErrorLocalizer = $validatorErrorLocalizer;
	}

	/**
	 * Implements pre-safe checks.
	 *
	 * @note: This is a preliminary implementation. This entire class will be redundant
	 * once validation is done in ChangeOps.
	 *
	 * @param Entity $entity
	 * @param EntityDiff $entityDiff
	 *
	 * @return Status
	 */
	public function applyPreSaveChecks( Entity $entity, EntityDiff $entityDiff = null ) {
		if ( $entityDiff ) {
			$labelLanguagesToCheck = $this->getLanguagesToCheck( $entityDiff->getLabelsDiff() );
			$descriptionLanguagesToCheck = $this->getLanguagesToCheck( $entityDiff->getDescriptionsDiff() );
			$aliasLanguagesToCheck = $this->getLanguagesToCheck( $entityDiff->getAliasesDiff() );
		} else {
			$labelLanguagesToCheck = array_keys( $entity->getLabels() );
			$descriptionLanguagesToCheck = array_keys( $entity->getDescriptions() );
			$aliasLanguagesToCheck = array_keys( $entity->getAllAliases() );
		}

		$entityType = $entity->getType();

		$status = Status::newGood();

		try {
			$languageValidator = $this->termValidatorFactory->getLanguageValidator();

			$this->checkTerms( $labelLanguagesToCheck, $languageValidator );
			$this->checkTerms( $descriptionLanguagesToCheck,$languageValidator );
			$this->checkTerms( $aliasLanguagesToCheck, $languageValidator );

			$this->checkTerms(
				$entity->getLabels( $labelLanguagesToCheck ),
				$this->termValidatorFactory->getLabelValidator( $entityType )
			);

			$this->checkTerms(
				$entity->getDescriptions( $descriptionLanguagesToCheck ),
				$this->termValidatorFactory->getDescriptionValidator( $entityType )
			);

			$this->checkTerms(
				$entity->getAllAliases( $aliasLanguagesToCheck ),
				$this->termValidatorFactory->getAliasValidator( $entityType )
			);

			if ( count( $labelLanguagesToCheck )
				|| count( $descriptionLanguagesToCheck ) ) {

				$this->checkTermDuplicates( $entity );
			}
		} catch ( ChangeOpValidationException $ex ) {
			// NOTE: We use a ChangeOpValidationException here, since we plan
			// to move the validation into the ChangeOps anyway.
			$status = $this->validatorErrorLocalizer->getResultStatus( $ex->getValidationResult() );
		}

		return $status;
	}

	/**
	 * @param Entity $entity
	 *
	 * @throws ChangeOp\ChangeOpValidationException
	 */
	private function checkTermDuplicates( Entity $entity ) {
		if ( $entity->getType() === Property::ENTITY_TYPE ) {
			//FIXME: This is redundant, since it's also checked on every save as a hard constraint.
			//       We need a single place to define hard and soft contraints.
			$result = $this->duplicateDetector->detectLabelConflictsForEntity( $entity );
		} else {
			$result = $this->duplicateDetector->detectLabelDescriptionConflictsForEntity( $entity );
		}

		if ( !$result->isValid() ) {
			throw new ChangeOpValidationException( $result );
		}
	}

	/**
	 * Checks a list of terms using the given validator.
	 * If the entries in the list are arrays, the check is performed recursively.
	 *
	 * @param string[]|array $terms
	 * @param ValueValidator $validator
	 *
	 * @throws ChangeOp\ChangeOpValidationException
	 */
	private function checkTerms( array $terms, ValueValidator $validator ) {
		foreach ( $terms as $term ) {
			if ( is_array( $term ) ) {
				// check recursively
				$this->checkTerms( $term, $validator );
			} else {
				$result = $validator->validate( $term );

				if ( !$result->isValid() ) {
					throw new ChangeOpValidationException( $result );
				}
			}
		}
	}

	/**
	 * Gets the keys of all additions and other changes in the diff.
	 * Removals are ignored.
	 *
	 * @param Diff $diff
	 *
	 * @return string[]|int[]
	 */
	private function getLanguagesToCheck( Diff $diff ) {
		return array_unique( array_diff(
			array_keys( $diff->getOperations() ),
			array_keys( $diff->getRemovals() )
		) );
	}
}