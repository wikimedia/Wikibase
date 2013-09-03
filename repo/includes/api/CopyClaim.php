<?php

namespace Wikibase\Api;

use ApiBase;
use ValueParsers\ParseException;
use Wikibase\ChangeOps;
use Wikibase\Entity;
use Wikibase\Lib\ClaimGuidGenerator;
use Wikibase\Lib\Serializers\ClaimSerializer;
use Wikibase\Lib\Serializers\SerializerFactory;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Claims;
use Wikibase\ChangeOpClaim;
use Wikibase\ChangeOpException;
use Wikibase\Summary;

/**
 * API module to copy a claim from one item to another.
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
 * @since 0.5
 *
 * @ingroup WikibaseRepo
 * @ingroup API
 *
 * @licence GNU GPL v2+
 * @author Kunal Mehta < legoktm@gmail.com >
 */
class CopyClaim extends ModifyClaim {

	/**
	 * @see \ApiBase::execute
	 *
	 * @since 0.5
	 */
	public function execute() {
		wfProfileIn( __METHOD__ );

		$params = $this->extractRequestParams();

		$entityIdParser = WikibaseRepo::getDefaultInstance()->getEntityIdParser();
		try {
			$id = $entityIdParser->parse( $params['id'] );
		}
		catch ( ParseException $e ) {
			wfProfileOut( __METHOD__ );
			$this->dieUsage( 'You must provide a valid id', 'invalid-id' );
		}

		$entityContentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();
		$entityContent = $entityContentFactory->getFromId( $id );
		$item = $entityContent->getItem();

		$fromEntity = $this->claimModificationHelper->getEntityIdFromString(
			Entity::getIdFromClaimGuid( $params['claim'] )
		);

		$fromClaims = new Claims( $fromEntity->getClaims() );
		if ( !$fromClaims->hasClaimWithGuid( $params['claim'] ) ) {
			$this->dieUsage( 'No such claim exists', 'invalid-claim' );
		}

		$claimCopy = $fromClaims->getClaimWithGuid( $params['claim'] );
		$claimCopy->setGuid( null );
		// Reset the GUID so a new one will be generated
		$changeOp = new ChangeOps();
		$changeOp->add( new ChangeOpClaim( $claimCopy, 'add', new ClaimGuidGenerator(  $item->getID() ) ) );

		try {
			$changeOp->apply( $item );
		} catch ( ChangeOpException $e ) {
			wfProfileOut( __METHOD__ );
			$this->dieUsage( 'Change could not be applied to entity: ' . $e->getMessage(), 'failed-save' );
		}

		$summary = new Summary(
			$this->getModuleName(),
			isset( $params['move'] ) ? 'copy' : 'move'
		);
		if ( !is_null( $params['summary'] ) ) {
			$summary->setUserSummary( $params['summary'] );
		}

		// Is it ok to use the old $entityContent?
		$this->attemptSaveEntity( $entityContent, $summary );

		// Stolen from GetClaims.php
		$serializerFactory = new SerializerFactory();
		$serializer = $serializerFactory->newSerializerForObject( $claimCopy );

		$serializer->getOptions()->setIndexTags( $this->getResult()->getIsRawMode() );

		$serializedClaims = $serializer->getSerialized( $claimCopy );

		$this->getResult()->addValue(
			null,
			'claim',
			$serializedClaims
		);

		$this->getResult()->addValue(
			null,
			'sucess',
			1
		);

		wfProfileOut( __METHOD__ );
	}

	/**
	 * @see \ApiBase::getPossibleErrors()
	 */
	public function getPossibleErrors() {
		return array_merge(
			parent::getPossibleErrors(),
			$this->claimModificationHelper->getPossibleErrors(),
			array(
				array( 'code' => 'invalid-id', 'info' => 'The id provided is not valid' ),
				array( 'code' => 'invalid-claim', 'info' => 'The claim GUID provided is not valid' ),
				array( 'code' => 'failed-save', 'info' => 'The change could not be saved' ),
			)
		);
	}

	/**
	 * @see \ApiBase::getAllowedParams
	 */
	public function getAllowedParams() {
		return array_merge(
			parent::getAllowedParams(),
			array(
				'id' => array(
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true,
				),
				'claim' => array(
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true,
				),
				'move' => array(
					ApiBase::PARAM_TYPE => 'boolean',
				),
			)
		);
	}

	/**
	 * @see \ApiBase::getParamDescription
	 */
	public function getParamDescription() {
		return array_merge(
			parent::getParamDescription(),
			array(
				'id' => 'Id of the entity you are copying the claim to',
				'claim' => 'GUID of the claim you are copying',
				'move' => 'Whether to move the claim instead of copying it',
			)
		);
	}

	/**
	 * @see \ApiBase::getDescription
	 */
	public function getDescription() {
		return array(
			'API module for copying Wikibase claims.'
		);
	}

	/**
	 * @see \ApiBase::getExamples
	 */
	protected function getExamples() {
		return array(
			'api.php?action=wbcopyclaim&id=q42&claim=q441536$0F03FF04-4497-4A11-93C7-3D339A30B0EC' => 'Copies a claim from q441536 onto q42',
			'api.php?action=wbcopyclaim&id=q42&claim=q441536$0F03FF04-4497-4A11-93C7-3D339A30B0EC&move' => 'Moves a claim from q441536 onto q42',
		);
	}
}
