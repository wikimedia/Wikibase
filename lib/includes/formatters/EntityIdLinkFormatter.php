<?php

namespace Wikibase\Lib;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdValue;

/**
 * Formats entity IDs by generating a wiki link to the corresponding page title.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class EntityIdLinkFormatter extends EntityIdTitleFormatter {

	/**
	 * @see EntityIdFormatter::formatEntityId
	 *
	 * @param EntityId $entityId
	 *
	 * @return string
	 */
	protected function formatEntityId( EntityId $entityId ) {
		$title = parent::formatEntityId( $entityId );

		return "[[$title]]";
	}

}
