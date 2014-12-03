<?php

namespace Wikibase\Test;

use Wikibase\ChangesTable;
use Wikibase\Client\Store\EntityIdLookup;
use Wikibase\Client\Usage\NullUsageTracker;
use Wikibase\Client\Usage\SubscriptionManager;
use Wikibase\Client\Usage\UsageLookup;
use Wikibase\Client\Usage\UsageTracker;
use Wikibase\ClientStore;
use Wikibase\Lib\Store\EntityLookup;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\SiteLinkLookup;
use Wikibase\PropertyInfoStore;
use Wikibase\PropertyLabelResolver;
use Wikibase\TermIndex;

/**
 * (Incomplete) ClientStore mock
 *
 * @since 0.5
 *
 * @license GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 * @author Thiemo Mättig
 */
class MockClientStore implements ClientStore {

	/**
	 * @var MockRepository|null
	 */
	private $mockRepository = null;

	/**
	 * @var MockPropertyInfoStore|null
	 */
	private $mockPropertyInfoStore = null;

	/**
	 * @see ClientStore::getUsageLookup
	 *
	 * @return UsageLookup
	 */
	public function getUsageLookup() {
		return new NullUsageTracker();
	}

	/**
	 * @see ClientStore::getUsageTracker
	 *
	 * @return UsageTracker
	 */
	public function getUsageTracker() {
		return new NullUsageTracker();
	}

	/**
	 * @see ClientStore::getSubscriptionManager
	 *
	 * @return SubscriptionManager
	 */
	public function getSubscriptionManager() {
		return new SubscriptionManager();
	}

	/**
	 * @see ClientStore::getPropertyLabelResolver
	 *
	 * @return PropertyLabelResolver
	 */
	public function getPropertyLabelResolver() {
		// FIXME: Incomplete
	}

	/**
	 * @see ClientStore::getTermIndex
	 *
	 * @return TermIndex
	 */
	public function getTermIndex() {
		// FIXME: Incomplete
	}

	/**
	 * @see ClientStore::getEntityIdLookup
	 *
	 * @return EntityIdLookup
	 */
	public function getEntityIdLookup() {
		// FIXME: Incomplete
	}

	/**
	 * @see ClientStore::newChangesTable
	 *
	 * @return ChangesTable
	 */
	public function newChangesTable() {
		// FIXME: Incomplete
	}

	/**
	 * @see ClientStore::clear
	 */
	public function clear() {
	}

	/**
	 * @see ClientStore::rebuild
	 */
	public function rebuild() {
	}

	private function getMockRepository() {
		if ( $this->mockRepository === null ) {
			$this->mockRepository = new MockRepository();
		}

		return $this->mockRepository;
	}

	/**
	 * @see ClientStore::getEntityLookup
	 *
	 * @return EntityLookup
	 */
	public function getEntityLookup() {
		return $this->getMockRepository();
	}

	/**
	 * @see ClientStore::getEntityRevisionLookup
	 *
	 * @return EntityRevisionLookup
	 */
	public function getEntityRevisionLookup() {
		return $this->getMockRepository();
	}

	/**
	 * @see ClientStore::getSiteLinkLookup
	 *
	 * @return SiteLinkLookup
	 */
	public function getSiteLinkLookup() {
		return $this->getMockRepository();
	}

	/**
	 * @see ClientStore::getPropertyInfoStore
	 *
	 * @return PropertyInfoStore
	 */
	public function getPropertyInfoStore() {
		if ( $this->mockPropertyInfoStore === null ) {
			$this->mockPropertyInfoStore = new MockPropertyInfoStore();
		}

		return $this->mockPropertyInfoStore;
	}

}
