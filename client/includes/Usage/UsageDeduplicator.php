<?php

namespace Wikibase\Client\Usage;

/**
 * This class de-duplicates entity usages for performance and storage reasons
 *
 * @license GPL-2.0+
 * @author Amir Sarabadani
 */
class UsageDeduplicator {

	/**
	 * @param EntityUsage[] $usages
	 *
	 * @return EntityUsage[]
	 */
	public function deduplicate( array $usages ) {
		return $this->flattenStructuredUsages(
			$this->deduplicateStructuredUsages(
				$this->structureUsages( $usages )
			)
		);
	}

	/**
	 * @param EntityUsage[] $usages
	 *
	 * @return array[] three-dimensional array of
	 *  [ $entityId => [ $aspectKey => [ EntityUsage $usage, … ], … ], … ]
	 */
	private function structureUsages( array $usages ) {
		$structuredUsages = [];

		foreach ( $usages as $usage ) {
			$entityId = $usage->getEntityId()->getSerialization();
			$aspect = $usage->getAspect();
			$structuredUsages[$entityId][$aspect][] = $usage;
		}

		return $structuredUsages;
	}

	/**
	 * @param array[] $structuredUsages
	 *
	 * @return array[]
	 */
	private function deduplicateStructuredUsages( array $structuredUsages ) {
		foreach ( $structuredUsages as &$usagesPerEntity ) {
			/** @var EntityUsage[] $usagesPerType */
			foreach ( $usagesPerEntity as &$usagesPerType ) {
				foreach ( $usagesPerType as $usage ) {
					if ( $usage->getModifier() === null ) {
						// This intentionally flattens the array to a single value
						$usagesPerType = $usage;
						break;
					}
				}
			}
		}

		return $structuredUsages;
	}

	/**
	 * @param array[] $structuredUsages
	 *
	 * @return EntityUsage[]
	 */
	private function flattenStructuredUsages( array $structuredUsages ) {
		$usages = [];

		array_walk_recursive(
			$structuredUsages,
			function ( EntityUsage $usage ) use ( &$usages ) {
				$usages[$usage->getIdentityString()] = $usage;
			}
		);

		return $usages;
	}

}
