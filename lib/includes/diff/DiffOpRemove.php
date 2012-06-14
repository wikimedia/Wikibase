<?php

namespace Wikibase;

/**
 * Represents a removal.
 * This means the value is not present in the "new" object but was in the old.
 *
 * @since 0.1
 *
 * @file
 * @ingroup WikibaseLib
 * @ingroup WikibaseDiff
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class DiffOpRemove extends DiffOp {

	protected $oldValue;

	public function getType() {
		return 'remove';
	}

	public function __construct( $oldValue ) {
		$this->oldValue = $oldValue;
	}

	public function getOldValue() {
		return $this->oldValue;
	}

	public function toArray() {
		return array(
			$this->getType(),
			$this->oldValue,
		);
	}

}