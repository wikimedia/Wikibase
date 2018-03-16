<?php

namespace WikibaseSearchElastic\Fields;

use WikibaseSearchElastic\Fields\FieldDefinitions;
use WikibaseSearchElastic\Fields\StatementCountField;
use WikibaseSearchElastic\Fields\StatementsField;
use WikibaseSearchElastic\Fields\WikibaseIndexField;

/**
 * Fields for an object that has statements.
 *
 * @license GPL-2.0-or-later
 * @author Stas Malyshev
 */
class StatementProviderFieldDefinitions implements FieldDefinitions {

	/**
	 * List of properties to index.
	 * @var string[]
	 */
	private $propertyIds;

	/**
	 * @var callable[]
	 */
	private $searchIndexDataFormatters;

	/**
	 * @param string[] $propertyIds List of properties to index
	 * @param callable[] $searchIndexDataFormatters
	 */
	public function __construct( array $propertyIds, array $searchIndexDataFormatters ) {
		$this->propertyIds = $propertyIds;
		$this->searchIndexDataFormatters = $searchIndexDataFormatters;
	}

	/**
	 * Get the list of definitions
	 * @return WikibaseIndexField[] key is field name, value is WikibaseIndexField
	 */
	public function getFields() {
		return [
			StatementsField::NAME => new StatementsField(
				$this->propertyIds,
				$this->searchIndexDataFormatters
			),
			StatementCountField::NAME => new StatementCountField(),
		];
	}

}
