<?php

namespace Wikibase\Client\Tests\RecentChanges;

use PHPUnit_Framework_TestCase;
use Wikibase\Client\Usage\NullSubscriptionManager;

/**
 * @covers Wikibase\Client\Usage\NullSubscriptionManager
 *
 * @group WikibaseClient
 * @group Wikibase
 *
 * @license GPL-2.0+
 * @author Thiemo Mättig
 */
class NullSubscriptionManagerTest extends PHPUnit_Framework_TestCase {

	public function testSubscribe() {
		$instance = new NullSubscriptionManager();
		$this->assertNull( $instance->subscribe( '', array() ) );
	}

	public function testUnsubscribe() {
		$instance = new NullSubscriptionManager();
		$this->assertNull( $instance->unsubscribe( '', array() ) );
	}

}
