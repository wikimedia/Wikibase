<?php
namespace Wikibase\Repo\Search\Elastic\Fields;

use SearchEngine;
use SearchIndexFieldDefinition;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Term\LabelsProvider;

/**
 * Field which contains per-language specific labels.
 */
class LabelsField extends TermIndexField {

	/**
	 * List of available languages
	 * @var array
	 */
	private $languages;

	public function __construct( $languages ) {
		$this->languages = $languages;
		parent::__construct( "", \SearchIndexField::INDEX_TYPE_NESTED );
	}

	/**
	 * @param SearchEngine $engine
	 * @return array
	 */
	public function getMapping( SearchEngine $engine ) {
		// Since we need a specially tuned field, we can not use
		// standard search engine types.
		if ( !( $engine instanceof \CirrusSearch ) ) {
			// For now only Cirrus/Elastic is supported
			return [];
		}

		$config = [
			'type' => 'object',
			'properties' => []
		];
		foreach ( $this->languages as $language ) {
			$langConfig = $this->getUnindexedField();

			$langConfig['fields']['prefix'] =
				$this->getSubfield( 'prefix_asciifolding', 'near_match_asciifolding' );
			$langConfig['fields']['near_match_folded'] =
				$this->getSubfield( 'near_match_asciifolding' );
			$langConfig['fields']['near_match'] = $this->getSubfield( 'near_match' );
			$langConfig['copy_to'] = 'labels_all';

			$config['properties'][$language] = $langConfig;
		}

		return $config;
	}

	/**
	 * @param EntityDocument $entity
	 *
	 * @return mixed Get the value of the field to be indexed when a page/document
	 *               is indexed. This might be an array with nested data, if the field
	 *               is defined with nested type or an int or string for simple field types.
	 */
	public function getFieldData( EntityDocument $entity ) {
		if ( !( $entity instanceof LabelsProvider ) ) {
			return [];
		}
		$data = [];
		foreach ( $entity->getLabels() as $language => $label ) {
			$data[$language] = $label->getText();
		}
		return $data;
	}

}
