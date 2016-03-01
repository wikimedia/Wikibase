<?php

namespace Wikibase\Repo\Search\Elastic\Fields;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Statement\StatementListProvider;

/**
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class StatementCountField implements SearchIndexField {

	/**
	 * @see SearchIndexField::getMapping
	 *
	 * @return array
	 */
	public function getMapping() {
		return array(
			'type' => 'integer'
		);
	}

	/**
	 * @see SearchIndexField::getFieldData
	 *
	 * @param EntityDocument $entity
	 *
	 * @return int
	 */
	public function getFieldData( EntityDocument $entity ) {
		if ( $entity instanceof StatementListProvider ) {
			return $entity->getStatements()->count();
		}

		return 0;
	}

}
