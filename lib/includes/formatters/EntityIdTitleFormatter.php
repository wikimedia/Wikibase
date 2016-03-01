<?php

namespace Wikibase\Lib;

use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use Wikibase\Lib\Store\EntityTitleLookup;

/**
 * Formats entity IDs by generating the corresponding page title.
 *
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class EntityIdTitleFormatter implements EntityIdFormatter {

	/**
	 * @var EntityTitleLookup
	 */
	protected $titleLookup;

	/**
	 * @param EntityTitleLookup $titleLookup
	 */
	public function __construct( EntityTitleLookup $titleLookup ) {
		$this->titleLookup = $titleLookup;
	}

	/**
	 * @see EntityIdFormatter::formatEntityId
	 *
	 * @param EntityId $entityId
	 *
	 * @return string Plain text
	 */
	public function formatEntityId( EntityId $entityId ) {
		$title = $this->titleLookup->getTitleForId( $entityId );
		return $title->getFullText();
	}

}
