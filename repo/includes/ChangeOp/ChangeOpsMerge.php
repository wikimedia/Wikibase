<?php

namespace Wikibase\ChangeOp;

use InvalidArgumentException;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Claim\Statement;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Reference;
use Wikibase\ItemContent;
use Wikibase\LabelDescriptionDuplicateDetector;
use Wikibase\Lib\ClaimGuidGenerator;
use Wikibase\SiteLinkCache;
use Wikibase\StoreFactory;
use Wikibase\Term;

/**
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Adam Shorland
 */
class ChangeOpsMerge {

	private $fromItemContent;
	private $toItemContent;
	private $fromChangeOps;
	private $toChangeOps;
	/** @var array */
	private $ignoreConflicts;
	/** @var LabelDescriptionDuplicateDetector */
	private $labelDescriptionDuplicateDetector;
	/** @var SitelinkCache */
	private $sitelinkCache;

	/**
	 * @param ItemContent $fromItemContent
	 * @param ItemContent $toItemContent
	 * @param LabelDescriptionDuplicateDetector $labelDescriptionDuplicateDetector
	 * @param SitelinkCache $sitelinkCache
	 * @param array $ignoreConflicts list of elements to ignore conflicts for
	 *   can only contain 'label' and or 'description' and or 'sitelink'
	 */
	public function __construct(
		ItemContent $fromItemContent,
		ItemContent $toItemContent,
		LabelDescriptionDuplicateDetector $labelDescriptionDuplicateDetector,
		SitelinkCache $sitelinkCache,
		$ignoreConflicts = array()
	) {
		$this->fromItemContent = $fromItemContent;
		$this->toItemContent = $toItemContent;
		$this->fromChangeOps = new ChangeOps();
		$this->toChangeOps = new ChangeOps();
		$this->assertValidIgnoreConflictValues( $ignoreConflicts );
		$this->ignoreConflicts = $ignoreConflicts;
		$this->labelDescriptionDuplicateDetector = $labelDescriptionDuplicateDetector;
		$this->sitelinkCache = $sitelinkCache;
	}

	/**
	 * @param array $ignoreConflicts can contain strings 'label', 'description', 'sitelink'
	 *
	 * @throws InvalidArgumentException
	 */
	private function assertValidIgnoreConflictValues( $ignoreConflicts ) {
		if( !is_array( $ignoreConflicts ) ){
			throw new InvalidArgumentException( '$ignoreConflicts must be an array' );
		}
		foreach( $ignoreConflicts as $ignoreConflict ){
			if( $ignoreConflict !== 'label' && $ignoreConflict !== 'description' && $ignoreConflict !== 'sitelink' ){
				throw new InvalidArgumentException( '$ignoreConflicts array can only contain "label", "description" and or "sitelink" values' );
			}
		}
	}

	public function apply() {
		$this->generateChangeOps();
		$this->fromChangeOps->apply( $this->fromItemContent->getItem() );
		$this->toChangeOps->apply( $this->toItemContent->getItem() );
		$this->applyConstraintChecks();
	}

	private function generateChangeOps() {
		$this->generateLabelsChangeOps();
		$this->generateDescriptionsChangeOps();
		$this->generateAliasesChangeOps();
		$this->generateSitelinksChangeOps();
		$this->generateClaimsChangeOps();
	}

	private function generateLabelsChangeOps() {
		foreach( $this->fromItemContent->getItem()->getLabels() as $langCode => $label ){
			$toLabel = $this->toItemContent->getItem()->getLabel( $langCode );
			if( $toLabel === false || $toLabel === $label ){
				$this->fromChangeOps->add( new ChangeOpLabel( $langCode, null ) );
				$this->toChangeOps->add( new ChangeOpLabel( $langCode, $label ) );
			} else {
				if( !in_array( 'label', $this->ignoreConflicts ) ){
					throw new ChangeOpException( "Conflicting labels for language {$langCode}" );
				}
			}
		}
	}

	private function generateDescriptionsChangeOps() {
		foreach( $this->fromItemContent->getItem()->getDescriptions() as $langCode => $desc ){
			$toDescription = $this->toItemContent->getItem()->getDescription( $langCode );
			if( $toDescription === false || $toDescription === $desc ){
				$this->fromChangeOps->add( new ChangeOpDescription( $langCode, null ) );
				$this->toChangeOps->add( new ChangeOpDescription( $langCode, $desc ) );
			} else {
				if( !in_array( 'description', $this->ignoreConflicts ) ){
					throw new ChangeOpException( "Conflicting descriptions for language {$langCode}" );
				}
			}
		}
	}

	private function generateAliasesChangeOps() {
		foreach( $this->fromItemContent->getItem()->getAllAliases() as $langCode => $aliases ){
			$this->fromChangeOps->add( new ChangeOpAliases( $langCode, $aliases, 'remove' ) );
			$this->toChangeOps->add( new ChangeOpAliases( $langCode, $aliases, 'add' ) );
		}
	}

	private function generateSitelinksChangeOps() {
		foreach( $this->fromItemContent->getItem()->getSiteLinks() as $simpleSiteLink ){
			$siteId = $simpleSiteLink->getSiteId();
			if( !$this->toItemContent->getItem()->hasLinkToSite( $siteId ) ){
				$this->fromChangeOps->add( new ChangeOpSiteLink( $siteId, null ) );
				$this->toChangeOps->add( new ChangeOpSiteLink( $siteId, $simpleSiteLink->getPageName() ) );
			} else {
				if( !in_array( 'sitelink', $this->ignoreConflicts ) ){
					throw new ChangeOpException( "Conflicting sitelinks for {$siteId}" );
				}
			}
		}
	}

	private function generateClaimsChangeOps() {
		foreach( $this->fromItemContent->getItem()->getClaims() as $fromClaim ) {
			$this->fromChangeOps->add( new ChangeOpClaimRemove( $fromClaim->getGuid() ) );

			$toClaim = clone $fromClaim;
			$toClaim->setGuid( null );
			$toMergeToClaim = false;

			if( $toClaim instanceof Statement ) {
				$toMergeToClaim = $this->findEquivalentClaim( $toClaim );
			}

			if( $toMergeToClaim ) {
				$this->generateReferencesChangeOps( $toClaim, $toMergeToClaim->getGuid() );
			} else {
				$this->toChangeOps->add( new ChangeOpClaim(
					$toClaim,
					new ClaimGuidGenerator( $this->toItemContent->getItem()->getId() )
				) );
			}
		}
	}

	/**
	 * Finds a claim in the target entity with the same main snak and qualifiers as the given $fromStatement
	 *
	 * @param Statement $fromStatement
	 *
	 * @return Claim|bool Claim to merge reference into or false
	 */
	private function findEquivalentClaim( $fromStatement ) {
		/** @var $claim Claim */
		foreach( $this->toItemContent->getItem()->getClaims() as $claim ) {
			$fromHash = $this->getClaimHash( $fromStatement );
			$toHash = $this->getClaimHash( $claim );
			if( $toHash === $fromHash ) {
				return $claim;
			}
		}
		return false;
	}

	/**
	 * @param Statement $statement
	 *
	 * @return string combined hash of the Mainsnak and Qualifiers
	 */
	private function getClaimHash( Statement $statement ) {
		return $statement->getMainSnak()->getHash() . $statement->getQualifiers()->getHash();
	}

	/**
	 * @param Statement $statement statement to take references from
	 * @param string $claimGuid claim guid to add the references to
	 */
	private function generateReferencesChangeOps( Statement $statement, $claimGuid ) {
		/** @var $reference Reference */
		foreach ( $statement->getReferences() as $reference ) {
			$this->toChangeOps->add( new ChangeOpReference(
				$claimGuid,
				$reference,
				''
			) );
		}
	}

	/**
	 * Throws an exception if it would not be possible to save the second item
	 * @throws ChangeOpException
	 */
	private function applyConstraintChecks() {
		$conflictingTerms = $this->labelDescriptionDuplicateDetector->getConflictingTerms(
			$this->toItemContent->getItem()
		);
		$conflictingSitelinks = $this->sitelinkCache->getConflictsForItem( $this->toItemContent->getItem() );

		$conflictString = '';
		if( $conflictingTerms !== array() ) {
			$conflictString .= $this->getConflictStringForTerms( $conflictingTerms );
		}
		if( $conflictingSitelinks !== array() ) {
			$conflictString .= $this->getConflictStringForSitelinks( $conflictingSitelinks );
		}

		if( $conflictString !== '' ) {
			throw new ChangeOpException( 'Item being merged to has conflicting terms: ' . $conflictString );
		}
	}

	/**
	 * @param Term[] $conflictingTerms
	 *
	 * @return string
	 */
	private function getConflictStringForTerms( $conflictingTerms ) {
		$conflictString = '';
		foreach( $conflictingTerms as $term ) {
			$conflictString .= $this->getConflictStringForTerm( $term );
		}
		return $conflictString;
	}

	/**
	 * @param Term $term
	 *
	 * @return string
	 */
	private function getConflictStringForTerm( $term ) {
		$itemId = ItemId::newFromNumber( $term->getEntityId() );
		if( !$itemId->equals( $this->fromItemContent->getItem()->getId() ) ) {
			return '(' .
				$itemId->getSerialization() . ' => ' .
				$term->getLanguage() . ' => ' .
				$term->getType() . ' => ' .
				$term->getText() . ') ';
		}
		return '';
	}

	/**
	 * @param array $conflictingSitelinks array of arrays each with the keys:
	 *     - itemId => integer
	 *     - siteId => string
	 *     - sitePage => string
	 * @return string
	 */
	private function getConflictStringForSitelinks( $conflictingSitelinks ) {
		$conflictString = '';
		foreach( $conflictingSitelinks as $sitelink ) {
			$conflictString .= $this->getConflictStringForSitelink( $sitelink );
		}
		return $conflictString;
	}

	/**
	 * @param array $sitelink array with the keys:
	 *     - itemId => integer
	 *     - siteId => string
	 *     - sitePage => string
	 *
	 * @return string
	 */
	private function getConflictStringForSitelink( $sitelink ) {
		$itemId = ItemId::newFromNumber( $sitelink['itemId'] );
		if( !$itemId->equals( $this->fromItemContent->getItem()->getId() ) ) {
			return '(' .
				$itemId->getSerialization() . ' => ' .
				$sitelink['siteId'] . ' => ' .
				$sitelink['sitePage'] . ') ';
		}
		return '';
	}

}