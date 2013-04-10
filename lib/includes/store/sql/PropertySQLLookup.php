<?php

namespace Wikibase;

/**
 * Property lookup by id and label
 *
 * @todo use terms table to do lookups, add caching and tests
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @since 0.4
 *
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Daniel Kinzler
 */
class PropertySQLLookup implements PropertyLookup {

	/* @var WikiPageEntityLookup */
	protected $entityLookup;

	/* @var array */
	protected $statementsByProperty;

	/* @var array */
	protected $propertiesByLabel;

	/**
	 * @since 0.4
	 *
	 * @param EntityLookup $entityLookup
	 */
	public function __construct( EntityLookup $entityLookup ) {
		$this->entityLookup = $entityLookup;
	}

	/**
	 * @since 0.4
	 *
	 * @param EntityId $entityId
	 */
	protected function indexPropertiesByLabel( EntityId $entityId, $langCode ) {
		wfProfileIn( __METHOD__ );

		$propertyList = array();
		$statementsByProperty = array();

		$entity = $this->entityLookup->getEntity( $entityId );

		foreach( $entity->getClaims() as $statement ) {
			$propertyId = $statement->getMainSnak()->getPropertyId();

			if ( $propertyId === null ) {
				continue;
			}

			$statementsByProperty[$propertyId->getNumericId()][] = $statement;

			$propertyLabel = $this->getPropertyLabel( $propertyId, $langCode );

			if ( $propertyLabel !== false ) {
				$id = $propertyId->getPrefixedId();
				$propertyList[$id] = $propertyLabel;
			}
		}

		$this->propertiesByLabel[$langCode] = $propertyList;
		$this->statementsByProperty = $statementsByProperty;

		wfProfileOut( __METHOD__ );
	}

	/**
	 * @since 0.4
	 *
	 * @param EntityId $propertyId
	 *
	 * @return string|false
	 */
	public function getPropertyLabel( EntityId $propertyId, $langCode ) {
		wfProfileIn( __METHOD__ );

		$property = $this->entityLookup->getEntity( $propertyId );
		$propertyLabel = false;

		if ( $property !== null ) {
			$propertyLabel = $property->getLabel( $langCode );
		}

		wfProfileOut( __METHOD__ );
		return $propertyLabel;
	}

	/**
	 * @since 0.4
	 *
	 * @param EntityId $propertyId
	 *
	 * @return Statement[]
	 */
	protected function getStatementsByProperty( EntityId $propertyId ) {
		wfProfileIn( __METHOD__ );
		$numericId = $propertyId->getNumericId();

		$statements = array_key_exists( $numericId, $this->statementsByProperty )
			? $this->statementsByProperty[$numericId] : array();

		wfProfileOut( __METHOD__ );
		return $statements;
	}

	/**
	 * @since 0.4
	 *
	 * @param string $propertyLabel
	 * @param string $langCode
	 *
	 * @return EntityId|null
	 */
	protected function getPropertyIdByLabel( $propertyLabel, $langCode ) {
		if ( $this->propertiesByLabel === null ) {
			return null;
		}

		wfProfileIn( __METHOD__ );
		$propertyId = array_search( $propertyLabel, $this->propertiesByLabel[$langCode] );
		if ( $propertyId !== false ) {
			$entityId = EntityId::newFromPrefixedId( $propertyId );

			wfProfileOut( __METHOD__ );
			return $entityId;
		}

		wfProfileOut( __METHOD__ );
		return null;
	}

	/**
	 * @since 0.4
	 *
	 * @param Entity $entity
	 * @param string $propertyLabel
	 * @param string $langCode
	 *
	 * @return Claims
	 */
	public function getClaimsByPropertyLabel( Entity $entity, $propertyLabel, $langCode ) {
		wfProfileIn( __METHOD__ );

		$claims = null;

		if ( defined( 'WB_EXPERIMENTAL_FEATURES' ) && WB_EXPERIMENTAL_FEATURES ) {
			$propertyId = $this->getPropertyIdByLabel( $propertyLabel, $langCode );

			if ( $propertyId === null ) {
				//NOTE: No negative caching. If we are looking up a label that can't be found
				//      in the item, we'll always try to re-index the item's properties.
				//      We just hope that this is rare, because people notice when a label
				//      doesn't work.
				$this->indexPropertiesByLabel( $entity->getId(), $langCode );
				$propertyId = $this->getPropertyIdByLabel( $propertyLabel, $langCode );
			}

			if ( $propertyId !== null ) {
				$allClaims = new Claims( $entity->getClaims() );
				$claims = $allClaims->getClaimsForProperty( $propertyId->getNumericId() );
			}
		}

		if ( $claims === null ) {
			$claims = new Claims();
		}

		wfProfileOut( __METHOD__ );
		return $claims;
	}

}
