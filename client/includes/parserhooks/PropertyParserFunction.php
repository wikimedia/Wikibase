<?php

namespace Wikibase;

/**
 * {{#property}} parser function
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
 * @ingroup WikibaseClient
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class PropertyParserFunction {

	protected $site;

	protected $entityId;

	protected $entityLookup;

	protected $siteLinkLookup;

	/**
	 * @since 0.4
	 *
	 * @param \Site $site
	 * @param EntityId $entityId
	 * @param WikiPageEntityLookup $entityLookup
	 */
	public function __construct( \Site $site, EntityId $entityId, WikiPageEntityLookup $entityLookup ) {
		$this->site = $site;
		$this->entityId = $entityId;
		$this->entityLookup = $entityLookup;
	}

	/**
	 * Formats an error message
	 * @todo is there really nothing like this function in core?
	 *
	 * @since 0.4
	 *
	 * @param string $messageKey
	 * @param $params[] message params
	 *
	 * @return string
	 */
	protected function error( $messageKey, $params = null ) {
		$msg = is_array( $params ) ? wfMessage( $messageKey, $params )->text() : wfMessage( $messageKey );
		return \Html::element(
			'span',
			array( 'class' => 'error' ),
			$msg
		);
	}

	/**
	 * Get data value for a property of item associated with client wiki page
	 *
	 * @since 0.4
	 *
	 * @param Entity $entity
	 * @param string $propertyLabel
	 *
	 * @return Snak
	 */
	public function getMainSnak( Entity $entity, $propertyLabel ) {
		$claimsByProperty = array();

		foreach( $entity->getClaims() as $claim ) {
			$propertyId = $claim->getMainSnak()->getPropertyId();
			$claimsByProperty[$propertyId->getNumericId()][] = $claim;
		}

		if ( $claimsByProperty !== array() ) {
			foreach( $claimsByProperty as $id => $claims ) {
				foreach( $claims as $claim ) {
					$mainSnak = $claim->getMainSnak();
					$property = $this->entityLookup->getEntity( $mainSnak->getPropertyId() );

					// @todo allow lookup by entity id, in addition to label?
					if ( $property->getLabel( $this->site->getLanguageCode() ) === $propertyLabel ) {
						return $mainSnak;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Get data value for snak
	 * @todo handle all property types!
	 *
	 * @since 0.4
	 *
	 * @param Snak $snak
	 * @param string $propertyLabel
	 *
	 * @return string
	 */
	protected function getSnakValue( Snak $snak, $propertyLabel ) {
		$propertyValue = $snak->getDataValue();

		if ( $propertyValue instanceof \Wikibase\EntityId ) {
			$langCode = $this->site->getLanguageCode();
			// @todo we could use the terms table to lookup label
			// we would need to have some store lookup code in WikibaseLib
			$entity = $this->entityLookup->getEntity( $propertyValue );
			$label = $entity->getLabel( $this->site->getLanguageCode() );

			// @todo ick! handle when there is no label...
			return $label !== false ? $label : $entity->getPrefixedId();
		}

		return $this->error( 'wikibase-property-notsupportedyet', array( $propertyLabel ) );
	}

	/**
	 * @since 0.4
	 *
	 * @param string $propertyLabel
	 *
	 * @return string
	 */
	public function evaluate( $propertyLabel ) {
		$snak = $this->getMainSnak(
			$this->entityLookup->getEntity( $this->entityId ),
			$propertyLabel
		);

		if ( $snak instanceof \Wikibase\Snak ) {
			return wfEscapeWikiText( $this->getSnakValue( $snak, $propertyLabel ) );
		}

		return $this->error( 'wikibase-property-notfound', array( $propertyLabel ) );
	}

	/**
	 * @since 0.4
	 *
	 * @param \Parser &$parser
	 *
	 * @return string
	 */
	public static function render( \Parser $parser, $propertyLabel ) {
		$site = \Sites::singleton()->getSite( Settings::get( 'siteGlobalID' ) );

		$siteLinkLookup = ClientStoreFactory::getStore()->newSiteLinkTable();
		$entityId = $siteLinkLookup->getEntityIdForSiteLink(
			new SiteLink( $site, $parser->getTitle()->getFullText() )
		);

		// @todo handle when site link is not there, such as site link / entity has been deleted...
		if ( $entityId === null ) {
			return '';
		}

		$entityLookup = ClientStoreFactory::getStore()->newEntityLookup();

		$instance = new self( $site, $entityId, $entityLookup );
		return $instance->evaluate( $propertyLabel );
	}

}
