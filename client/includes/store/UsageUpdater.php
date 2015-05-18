<?php

namespace Wikibase\Client\Store;

use InvalidArgumentException;
use Wikibase\Client\Usage\EntityUsage;
use Wikibase\Client\Usage\SubscriptionManager;
use Wikibase\Client\Usage\UsageLookup;
use Wikibase\Client\Usage\UsageTracker;
use Wikibase\DataModel\Entity\EntityId;

/**
 * Service for updating usage tracking and associated change subscription information.
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class UsageUpdater {

	/**
	 * @var string
	 */
	private $clientId;

	/**
	 * @var UsageTracker
	 */
	private $usageTracker;

	/**
	 * @var UsageLookup
	 */
	private $usageLookup;

	/**
	 * @var SubscriptionManager
	 */
	private $subscriptionManager;

	/**
	 * @param string $clientId
	 * @param UsageTracker $usageTracker
	 * @param UsageLookup $usageLookup
	 * @param SubscriptionManager $subscriptionManager
	 */
	public function __construct(
		$clientId,
		UsageTracker $usageTracker,
		UsageLookup $usageLookup,
		SubscriptionManager $subscriptionManager
	) {

		$this->clientId = $clientId;
		$this->usageTracker = $usageTracker;
		$this->usageLookup = $usageLookup;
		$this->subscriptionManager = $subscriptionManager;
	}

	/**
	 * Updates entity usage information for the given page, and automatically adjusts
	 * any subscriptions based on that usage.
	 *
	 * @param int $pageId The ID of the page the entities are used on.
	 * @param EntityUsage[] $usages A list of EntityUsage objects.
	 * See docs/usagetracking.wiki for details.
	 * @param string $touched timestamp. Timestamp of the update that contained the given usages.
	 *
	 * @see UsageTracker::trackUsedEntities
	 *
	 * @throws InvalidArgumentException
	 */
	public function addUsagesForPage( $pageId, array $usages, $touched ) {
		if ( !is_int( $pageId ) ) {
			throw new InvalidArgumentException( '$pageId must be an int!' );
		}

		if ( !is_string( $touched ) || $touched === '' ) {
			throw new InvalidArgumentException( '$touched must be a timestamp string!' );
		}

		$this->usageTracker->trackUsedEntities( $pageId, $usages, $touched );
		$currentlyUsedEntities = $this->getEntityIds( $usages );

		// Subscribe to anything that was added
		if ( !empty( $currentlyUsedEntities ) ) {
			$this->subscriptionManager->subscribe( $this->clientId, $currentlyUsedEntities );
		}
	}

	/**
	 * Removes stale usage information for the given page, and removes
	 * any subscriptions that have become unnecessary.
	 *
	 * @param int $pageId The ID of the page the entities are used on.
	 * @param string $lastUpdatedBefore timestamp. Entries older than this timestamp will be removed.
	 *
	 * @see UsageTracker::trackUsedEntities
	 *
	 * @throws InvalidArgumentException
	 */
	public function pruneUsagesForPage( $pageId, $lastUpdatedBefore = '00000000000000' ) {
		if ( !is_int( $pageId ) ) {
			throw new InvalidArgumentException( '$pageId must be an int!' );
		}

		if ( !is_string( $lastUpdatedBefore ) || $lastUpdatedBefore === '' ) {
			throw new InvalidArgumentException( '$lastUpdatedBefore must be a timestamp string!' );
		}

		$prunedUsages = $this->usageTracker->pruneStaleUsages( $pageId, $lastUpdatedBefore );

		$prunedEntityIds = $this->getEntityIds( $prunedUsages );
		$unusedIds =  $this->usageLookup->getUnusedEntities( $prunedEntityIds );

		if ( !empty( $unusedIds ) ) {
			// Unsubscribe from anything that was pruned and is otherwise unused.
			$this->subscriptionManager->unsubscribe( $this->clientId, $unusedIds );
		}
	}

	/**
	 * @param EntityUsage[] $entityUsages
	 *
	 * @return EntityId[]
	 */
	private function getEntityIds( array $entityUsages ) {
		$entityIds = array();

		foreach ( $entityUsages as $usage ) {
			$id = $usage->getEntityId();
			$key = $id->getSerialization();

			$entityIds[$key] = $id;
		}

		return $entityIds;
	}

}
