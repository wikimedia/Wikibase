<?php

namespace Wikibase\Repo\Store;

use Exception;
use Onoi\MessageReporter\MessageReporter;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Services\EntityId\SeekableEntityIdPager;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\TermStore\ItemTermStore;
use Wikibase\TermStore\PropertyTermStore;
use Wikimedia\Rdbms\LBFactory;

/**
 * @license GPL-2.0-or-later
 */
class PropertyTermsRebuilder {

	private $propertyTermStore;
	private $idPager;
	private $progressReporter;
	private $errorReporter;
	private $loadBalancerFactory;
	private $entityLookup;
	private $batchSize;
	private $batchSpacingInSeconds;

	/**
	 * @param PropertyTermStore $propertyTermStore,
	 * @param SeekableEntityIdPager $idPager,
	 * @param MessageReporter $progressReporter,
	 * @param MessageReporter $errorReporter,
	 * @param LBFactory $loadBalancerFactory,
	 * @param EntityLookup $entityLookup,
	 * @param int $batchSize,
	 * @param int $batchSpacingInSeconds
	 */
	public function __construct(
		PropertyTermStore $propertyTermStore,
		SeekableEntityIdPager $idPager,
		MessageReporter $progressReporter,
		MessageReporter $errorReporter,
		LBFactory $loadBalancerFactory,
		EntityLookup $entityLookup,
		$batchSize,
		$batchSpacingInSeconds
	) {
		$this->propertyTermStore = $propertyTermStore;
		$this->idPager = $idPager;
		$this->progressReporter = $progressReporter;
		$this->errorReporter = $errorReporter;
		$this->loadBalancerFactory = $loadBalancerFactory;
		$this->entityLookup = $entityLookup;
		$this->batchSize = $batchSize;
		$this->batchSpacingInSeconds = $batchSpacingInSeconds;
	}

	public function rebuild() {
		$ticket = $this->loadBalancerFactory->getEmptyTransactionTicket( __METHOD__ );

		while ( true ) {
			$propertyIds = $this->idPager->fetchIds( $this->batchSize );

			if ( !$propertyIds ) {
				break;
			}

			$this->rebuildTermsForBatch( $propertyIds );

			$this->loadBalancerFactory->commitAndWaitForReplication( __METHOD__, $ticket );

			$this->progressReporter->reportMessage(
				'Processed up to page '
				. $this->idPager->getPosition() . ' (' . end( $propertyIds ) . ')'
			);

			if ( $this->batchSpacingInSeconds > 0 ) {
				sleep( $this->batchSpacingInSeconds );
			}
		}
	}

	private function rebuildTermsForBatch( array $propertyIds ) {
		foreach ( $propertyIds as $propertyId ) {
			$this->saveTerms(
				$this->entityLookup->getEntity( $propertyId )
			);
		}
	}

	private function saveTerms( Property $property ) {
		try {
			$this->propertyTermStore->storeTerms( $property->getId(), $property->getFingerprint() );
		} catch ( Exception $ex ) {
			$this->loadBalancerFactory->rollbackMasterChanges( __METHOD__ );
			$this->errorReporter->reportMessage(
				'Failed to save terms of property: ' . $property->getId()->getSerialization()
			);
		}
	}

}
