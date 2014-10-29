<?php

namespace Wikibase\Lib\Store;

use Wikibase\DataModel\Entity\EntityId;

/**
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
interface TermLookup {

	/**
	 * Gets the label of an Entity with the specified EntityId and language code.
	 *
	 * @param EntityId $entityId
	 * @param string $languageCode
	 *
	 * @return string
	 */
	public function getLabel( EntityId $entityId, $languageCode );

	/**
	 * Gets terms of an Entity with the specified EntityId and term type.
	 *
	 * @param EntityId $entityId
	 * @param string $termType
	 *
	 * @return string[]
	 */
	public function getTermsOfType( EntityId $entityId, $termType );

}
