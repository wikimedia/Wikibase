<?php

namespace Wikibase\Lib\Units;

/**
 * Basic unit storage functionality.
 * Concrete classes need to fill in data loading.
 *
 * @license GPL-2.0-or-later
 * @author Stas Malyshev
 */
abstract class BaseUnitStorage implements UnitStorage {

	/**
	 * @var array[]
	 */
	private $storageData;

	/**
	 * Load data from concrete storage.
	 * The method should return array indexed by source unit.
	 * Each row should be either [<factor>, <unit>] or
	 * ['factor' => <factor>, 'unit' => <unit>]
	 * @return array[]|null null when loading failed.
	 */
	abstract protected function loadStorageData();

	/**
	 * Load data from storage.
	 */
	private function loadData() {
		if ( is_null( $this->storageData ) ) {
			$this->storageData = $this->loadStorageData();
			if ( is_null( $this->storageData ) ) {
				throw new \RuntimeException( "Failed to load unit storage" );
			}
		}
	}

	/**
	 * Check if certain unit is primary.
	 * @param string $unit
	 * @return bool
	 */
	public function isPrimaryUnit( $unit ) {
		if ( is_null( $this->storageData ) ) {
			$this->loadData();
		}
		if ( !isset( $this->storageData[$unit] ) ) {
			return false;
		}
		if ( isset( $this->storageData[$unit]['unit'] ) ) {
			return $this->storageData[$unit]['unit'] === $unit;
		} else {
			return $this->storageData[$unit][1] === $unit;
		}
	}

	/**
	 * Get conversion from this unit to primary unit
	 * @param string $unit
	 * @return array|null 'factor' => factor from this unit to primary unit
	 *                    'unit' => primary unit
	 */
	public function getConversion( $unit ) {
		if ( is_null( $this->storageData ) ) {
			$this->loadData();
		}
		if ( !isset( $this->storageData[$unit] ) ) {
			return null;
		}
		if ( isset( $this->storageData[$unit]['factor'] ) ) {
			return $this->storageData[$unit];
		} else {
			return [
				'factor' => $this->storageData[$unit][0],
				'unit' => $this->storageData[$unit][1]
			];
		}
	}

}
