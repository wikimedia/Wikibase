<?php

namespace Wikibase\Repo\Api;
use MWException;

use ApiBase;

use Wikibase\Entity;
use Wikibase\EntityContent;
use Wikibase\Claim;
use Wikibase\EntityId;
use Wikibase\EntityContentFactory;
use Wikibase\Summary;

/**
 * API module for creating or updating an entire Claim.
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
 * @ingroup WikibaseRepo
 * @ingroup API
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class SetClaim extends \Wikibase\ApiModifyClaim {

	// TODO: rights

	/**
	 * @see ApiBase::execute
	 *
	 * @since 0.4
	 */
	public function execute() {
		$summary = $this->createSummary( $this->extractRequestParams() );
		$summary->addAutoCommentArgs( 1 ); // one claim updated

		$claim = $this->getClaimFromRequest();
		$summary->addAutoSummaryArgs( $claim->getPropertyId()->getPrefixedId() );

		$entityId = $this->getEntityIdForClaim( $claim );

		$content = $this->getEntityContent( $entityId );

		$this->setClaim( $content->getEntity(), $claim );

		$this->saveChanges( $content, $summary );

		$this->outputClaim( $claim );
	}

	/**
	 * @param Claim $claim
	 *
	 * @return EntityId
	 */
	protected function getEntityIdForClaim( Claim $claim ) {
		$guid = $claim->getGuid();

		if ( $guid === null ) {
			$this->dieUsage( 'The ID of the claim needs to be set', 'setclaim-no-guid' );
		}

		try {
			$entityId = Entity::getIdFromClaimGuid( $guid );
		}
		catch ( MWException $exception ) {
			$this->dieUsage( $exception->getMessage(), 'setclaim-invalid-guid' );
		}

		$libRegistry = new \Wikibase\LibRegistry( \Wikibase\Settings::singleton() );
		$idParser = $libRegistry->getEntityIdParser();

		$parseResult = $idParser->parse( $entityId );

		if ( $parseResult->isValid() ) {
			$entityId = $parseResult->getValue();
			assert( $entityId instanceof EntityId );
			return $entityId;
		}

		$this->dieUsage( $parseResult->getError()->getText(), 'setclaim-invalid-guid' );
	}

	/**
	 * @since 0.4
	 *
	 * @return Claim
	 */
	protected function getClaimFromRequest() {
		$serializerFactory = new \Wikibase\Lib\Serializers\SerializerFactory();
		$unserializer = $serializerFactory->newUnserializerForClass( 'Wikibase\Claim' );

		$params = $this->extractRequestParams();
		$claim = $unserializer->newFromSerialization( \FormatJson::decode( $params['claim'], true ) );

		assert( $claim instanceof Claim );

		return $claim;
	}

	/**
	 * @since 0.4
	 *
	 * @param Entity $entity
	 * @param Claim $claim
	 */
	protected function setClaim( Entity $entity, Claim $claim ) {
		$claims = new \Wikibase\Claims( $entity->getClaims() );
		$claims->addClaim( $claim );
		$entity->setClaims( $claims );
	}

	/**
	 * @see ApiBase::getAllowedParams
	 *
	 * @since 0.4
	 *
	 * @return array
	 */
	public function getAllowedParams() {
		return array_merge( parent::getAllowedParams(), array(
			'claim' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
		) );
	}

	/**
	 * @see ApiBase::getParamDescription
	 *
	 * @since 0.4
	 *
	 * @return array
	 */
	public function getParamDescription() {
		return array_merge( parent::getParamDescription(), array(
			'claim' => 'Claim serialization',
		) );
	}

	/**
	 * @see ApiBase::getDescription
	 *
	 * @since 0.4
	 *
	 * @return string
	 */
	public function getDescription() {
		return array(
			'API module for creating or updating an entire Claim.'
		);
	}

	/**
	 * @see ApiBase::getExamples
	 *
	 * @since 0.4
	 *
	 * @return array
	 */
	protected function getExamples() {
		return array(
			'api.php?action=setclaim&claim={json-stuff}&baserevid=9042&token=foobar'
			// 'ex' => 'desc'
		);
	}

	/**
	 * @see ApiBase::getHelpUrls
	 *
	 * @since 0.4
	 *
	 * @return string
	 */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:Wikibase/API#wbsetclaimvalue';
	}

	/**
	 * @see ApiBase::getVersion
	 *
	 * @since 0.4
	 *
	 * @return string
	 */
	public function getVersion() {
		return __CLASS__ . '-' . WB_VERSION;
	}

}
