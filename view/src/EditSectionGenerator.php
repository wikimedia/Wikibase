<?php

namespace Wikibase\View;

use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Statement\Statement;

/**
 * Generates HTML for a section edit link
 *
 * @since 0.5
 * @licence GNU GPL v2+
 *
 * @author Adrian Heine < adrian.heine@wikimedia.de >
 */
interface EditSectionGenerator {

	/**
	 * Returns HTML allowing to edit site links.
	 *
	 * @since 0.5
	 *
	 * @param EntityId|null $entityId
	 * @return string
	 */
	public function getSiteLinksEditSection( EntityId $entityId = null );

	/**
	 * Returns HTML allowing to edit label, description and aliases.
	 *
	 * @since 0.5
	 *
	 * @param string $languageCode
	 * @param EntityId|null $entityId
	 * @return string
	 */
	public function getLabelDescriptionAliasesEditSection( $languageCode, EntityId $entityId = null );

	/**
	 * Returns HTML allowing to edit a statement
	 *
	 * @since 0.5
	 *
	 * @param Statement $statement
	 * @return string
	 */
	public function getStatementEditSection( Statement $statement );

	/**
	 * Returns HTML allowing to add a statement to a statementgroup
	 *
	 * @since 0.5
	 *
	 * @param PropertyId $propertyId The property of the statement group
	 * @param EntityId|null $entityId The id of the entity on which to add a statement
	 * @return string
	 */
	public function getAddStatementToGroupSection( PropertyId $propertyId, EntityId $entityId = null );

}
