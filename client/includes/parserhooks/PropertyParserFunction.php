<?php

namespace Wikibase;

use Wikibase\Client\WikibaseClient;
use Wikibase\Lib\SnakFormatter;

/**
 * Handler of the {{#property}} parser function.
 *
 * TODO: cleanup injection of dependencies
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
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class PropertyParserFunction {

	/* @var \Language */
	protected $language;

	/* @var EntityLookup */
	protected $entityLookup;

	/* @var PropertyLookup */
	protected $propertyLookup;

	/* @var ParserErrorMessageFormatter */
	protected $errorFormatter;

	/* @var SnakFormatter */
	protected $snaksFormatter;

	/**
	 * @since    0.4
	 *
	 * @param \Language                   $language
	 * @param EntityLookup                $entityLookup
	 * @param PropertyLookup              $propertyLookup
	 * @param ParserErrorMessageFormatter $errorFormatter
	 * @param Lib\SnakFormatter           $snaksFormatter
	 */
	public function __construct( \Language $language,
		EntityLookup $entityLookup, PropertyLookup $propertyLookup,
		ParserErrorMessageFormatter $errorFormatter, SnakFormatter $snaksFormatter ) {
		$this->language = $language;
		$this->entityLookup = $entityLookup;
		$this->propertyLookup = $propertyLookup;
		$this->errorFormatter = $errorFormatter;
		$this->snaksFormatter = $snaksFormatter;
	}

	/**
	 * Returns such Claims from $entity that have a main Snak for the property that
	 * is specified by $propertyLabel.
	 *
	 * @param Entity $entity The Entity from which to get the clams
	 * @param string $propertyLabel A property label (in the wiki's content language) or a prefixed property ID.
	 *
	 * @return Claims The claims for the given property.
	 */
	private function getClaimsForProperty( Entity $entity, $propertyLabel ) {
		$propertyIdToFind = EntityId::newFromPrefixedId( $propertyLabel );

		if ( $propertyIdToFind !== null ) {
			$allClaims = new Claims( $entity->getClaims() );
			$claims = $allClaims->getClaimsForProperty( $propertyIdToFind->getNumericId() );
		} else {
			$langCode = $this->language->getCode();
			$claims = $this->propertyLookup->getClaimsByPropertyLabel( $entity, $propertyLabel, $langCode );
		}

		return $claims;
	}

	/**
	 * @since 0.4
	 *
	 * @param Snak[] $snaks
	 *
	 * @return string - wikitext format
	 */
	private function formatSnakList( $snaks ) {
		$formattedValues = $this->snaksFormatter->formatSnaks( $snaks );
		return $this->language->commaList( $formattedValues );
	}

	/**
	 * @since 0.4
	 *
	 * @param EntityId $entityId
	 * @param string   $propertyLabel
	 *
	 * @return string - wikitext format
	 */
	public function renderForEntityId( EntityId $entityId, $propertyLabel ) {
		wfProfileIn( __METHOD__ );

		$entity = $this->entityLookup->getEntity( $entityId );

		if ( !$entity ) {
			wfProfileOut( __METHOD__ );
			return '';
		}

		$claims = $this->getClaimsForProperty( $entity, $propertyLabel );

		if ( $claims->isEmpty() ) {
			wfProfileOut( __METHOD__ );
			return '';
		}

		$snakList = $claims->getMainSnaks();
		$text = $this->formatSnakList( $snakList, $propertyLabel );

		wfProfileOut( __METHOD__ );
		return $text;
	}

	/**
	 * @since 0.4
	 *
	 * @param \Parser &$parser
	 *
	 * @return string
	 */
	public static function render( \Parser $parser, $propertyLabel ) {
		wfProfileIn( __METHOD__ );
		$site = \Sites::singleton()->getSite( Settings::get( 'siteGlobalID' ) );

		$siteLinkLookup = WikibaseClient::getDefaultInstance()->getStore()->newSiteLinkTable();
		$entityId = $siteLinkLookup->getEntityIdForSiteLink(
			new SiteLink( $site, $parser->getTitle()->getFullText() )
		);

		// @todo handle when site link is not there, such as site link / entity has been deleted...
		if ( $entityId === null ) {
			wfProfileOut( __METHOD__ );
			return '';
		}

		$targetLanguage = $parser->getTargetLanguage();
		$errorFormatter = new ParserErrorMessageFormatter( $targetLanguage );

		$wikibaseClient = WikibaseClient::newInstance();

		$entityLookup = $wikibaseClient->getStore()->getEntityLookup();
		$propertyLookup = $wikibaseClient->getStore()->getPropertyLookup();
		$formatter = $wikibaseClient->newSnakFormatter();

		$instance = new self( $targetLanguage,
			$entityLookup, $propertyLookup,
			$errorFormatter, $formatter );

		$result = array(
			$instance->renderForEntityId( $entityId, $propertyLabel ),
			'noparse' => false,
			'nowiki' => true,
		);

		wfProfileOut( __METHOD__ );
		return $result;
	}

}
