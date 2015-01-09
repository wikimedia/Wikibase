<?php

namespace Wikibase\Client\Usage;

use Wikibase\DataModel\Entity\EntityId;

/**
 * Service interface for tracking subscriptions of clients to entity
 * change events generated on the repo.
 *
 * @see docs/usagetracking.wiki
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
interface SubscriptionManager {

	/**
	 * Subscribes the given subscriber to notificatiosn about changes on the specified entities.
	 *
	 * @param string $subscriber Global site ID of the subscriber
	 * @param EntityId[] $entityIds The entities to subscribe to.
	 *
	 * @throws UsageTrackerException
	 */
	public function subscribe( $subscriber, array $entityIds );

	/**
	 * Unsubscribes the given subscriber from notificatiosn about changes on the specified entities.
	 *
	 * @param string $subscriber Global site ID of the subscriber
	 * @param EntityId[] $entityIds The entities to subscribe to.
	 *
	 * @throws UsageTrackerException
	 */
	public function unsubscribe( $subscriber, array $entityIds );

}
