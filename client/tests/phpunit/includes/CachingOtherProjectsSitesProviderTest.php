<?php

namespace Wikibase\Client\Tests;

use HashBagOStuff;
use Wikibase\Client\OtherProjectsSitesProvider;
use Wikibase\Client\CachingOtherProjectsSitesProvider;

/**
 * @covers Wikibase\Client\CachingOtherProjectsSitesProvider
 *
 * @since 0.5
 *
 * @group WikibaseClient
 * @group Wikibase
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 */
class CachingOtherProjectsSitesProviderTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @return OtherProjectsSitesProvider
	 */
	private function getOtherProjectsSitesProvider() {
		$otherProjectsSitesProvider = $this->getMock( 'Wikibase\Client\OtherProjectsSitesProvider' );

		$otherProjectsSitesProvider->expects( $this->once() )
			->method( 'getOtherProjectsSiteIds' )
			->will( $this->returnValue( array( 'dewikivoyage', 'commons' ) ) );

		return $otherProjectsSitesProvider;
	}

	public function testOtherProjectSiteIds() {
		$cachingOtherProjectsSitesProvider = new CachingOtherProjectsSitesProvider(
			$this->getOtherProjectsSitesProvider(),
			new HashBagOStuff(),
			100
		);

		$this->assertEquals(
			array( 'dewikivoyage', 'commons' ),
			$cachingOtherProjectsSitesProvider->getOtherProjectsSiteIds( array( 'wikivoyage', 'commons' ) )
		);

		// Call this again... self::getOtherProjectsSitesProvider makes sure we only compute
		// the value once.
		$this->assertEquals(
			array( 'dewikivoyage', 'commons' ),
			$cachingOtherProjectsSitesProvider->getOtherProjectsSiteIds( array( 'wikivoyage', 'commons' ) )
		);
	}
}
