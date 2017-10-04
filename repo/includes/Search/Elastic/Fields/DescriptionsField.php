<?php
namespace Wikibase\Repo\Search\Elastic\Fields;

use CirrusSearch;
use SearchEngine;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Term\DescriptionsProvider;

/**
 * Field which contains per-language specific descriptions.
 */
class DescriptionsField extends TermIndexField {

	/**
	 * List of available languages
	 * @var string[]
	 */
	private $languages;

	/**
	 * @param string[] $languages Available languages list.
	 */
	public function __construct( array $languages ) {
		$this->languages = $languages;
		parent::__construct( "description", \SearchIndexField::INDEX_TYPE_NESTED );
	}

	/**
	 * @param SearchEngine $engine
	 * @return null|array
	 */
	public function getMapping( SearchEngine $engine ) {
		// Since we need a specially tuned field, we can not use
		// standard search engine types.
		if ( !( $engine instanceof CirrusSearch ) ) {
			// For now only Cirrus/Elastic is supported
			return [];
		}

		$config = [
			'type' => 'object',
			'properties' => []
		];
		foreach ( $this->languages as $language ) {
			// TODO: here we probably will need better language-specific analyzers
			$langConfig = $this->getTokenizedSubfield( $engine->getConfig(), $language . '_text',
					$language . '_text_search' );
			$langConfig['fields']['plain'] = $this->getTokenizedSubfield( $engine->getConfig(), $language . '_plain',
					$language . '_plain_search' );
			$config['properties'][$language] = $langConfig;
		}

		return $config;
	}

	/**
	 * @param EntityDocument $entity
	 *
	 * @return string[] Array of descriptions in available languages.
	 */
	public function getFieldData( EntityDocument $entity ) {
		if ( !( $entity instanceof DescriptionsProvider ) ) {
			return [];
		}
		$data = [];
		foreach ( $entity->getDescriptions() as $language => $desc ) {
			$data[$language] = $desc->getText();
		}
		return $data;
	}

	/**
	 * Set engine hints.
	 * Specifically, sets noop hint so that descriptions would be compared
	 * as arrays and removal of description would be processed correctly.
	 * @param SearchEngine $engine
	 * @return array
	 */
	public function getEngineHints( SearchEngine $engine ) {
		if ( !( $engine instanceof CirrusSearch ) ) {
			// For now only Cirrus/Elastic is supported
			return [];
		}
		return [ \CirrusSearch\Search\CirrusIndexField::NOOP_HINT => "equals" ];
	}

}
