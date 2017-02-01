<?php

namespace Wikibase\Repo\Validators;

use ValueValidators\Result;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Term\AliasGroupList;
use Wikibase\DataModel\Term\LabelsProvider;
use Wikibase\DataModel\Term\TermList;
use Wikibase\LabelDescriptionDuplicateDetector;

/**
 * Validator for checking that entity labels and aliases are unique (per language).
 * This is used to make sure that Properties have unique labels and aliases.
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class LabelUniquenessValidator implements EntityValidator, FingerprintValidator {

	/**
	 * @var LabelDescriptionDuplicateDetector
	 */
	private $duplicateDetector;

	/**
	 * @param LabelDescriptionDuplicateDetector $duplicateDetector
	 */
	public function __construct( LabelDescriptionDuplicateDetector $duplicateDetector ) {
		$this->duplicateDetector = $duplicateDetector;
	}

	/**
	 * @see EntityValidator::validate()
	 *
	 * @param EntityDocument $entity
	 *
	 * @return Result
	 */
	public function validateEntity( EntityDocument $entity ) {
		if ( $entity instanceof LabelsProvider ) {
			return $this->duplicateDetector->detectLabelConflicts(
				$entity->getType(),
				$entity->getLabels()->toTextArray(),
				// insert again when T104393 is resolved
				null, //$entity->getFingerprint()->getAliasGroups()->toTextArray(),
				$entity->getId()
			);
		}

		return Result::newSuccess();
	}

	/**
	 * @see FingerprintValidator::validateFingerprint()
	 *
	 * @param TermList $labels
	 * @param TermList $descriptions
	 * @param AliasGroupList $aliasGroups
	 * @param EntityId $entityId
	 * @param string[]|null $languageCodes
	 *
	 * @return Result
	 */
	public function validateFingerprint(
		TermList $labels,
		TermList $descriptions,
		AliasGroupList $aliasGroups,
		EntityId $entityId,
		array $languageCodes = null
	) {
		$labels = $labels->toTextArray();
		$aliases = $aliasGroups->toTextArray();

		if ( $languageCodes !== null ) {
			$languageKeys = array_flip( $languageCodes );
			$labels = array_intersect_key( $labels, $languageKeys );
			$aliases = array_intersect_key( $aliases, $languageKeys );
		}

		// Nothing to do if there are no labels AND no aliases.
		if ( empty( $labels ) && empty( $aliases ) ) {
			return Result::newSuccess();
		}

		return $this->duplicateDetector->detectLabelConflicts(
			$entityId->getEntityType(),
			$labels,
			// insert again when T104393 is resolved
			null, //$aliases,
			$entityId
		);
	}

}
