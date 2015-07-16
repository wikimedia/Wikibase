<?php

namespace Wikibase\Test;

use HashBagOStuff;
use PHPUnit_Framework_TestCase;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\SiteLink;
use Wikibase\Lib\Store\CachingSiteLinkLookup;

/**
 * @covers Wikibase\Lib\Store\CachingSiteLinkLookup
 *
 * @group WikibaseLib
 * @group WikibaseStore
 * @group Wikibase
 *
 * @license GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 */
class CachingSiteLinkLookupTest extends PHPUnit_Framework_TestCase {

	public function testGetItemIdForLink_cacheHit() {
		$cache = new HashBagOStuff();
		$cache->set( 'wikibase:sitelinks-by-page:foowiki:bar', 'Q42' );

		$cachingSiteLinkLookup = new CachingSiteLinkLookup(
			$this->getMock( 'Wikibase\Lib\Store\SiteLinkLookup' ),
			$cache
		);

		$this->assertSame(
			'Q42',
			$cachingSiteLinkLookup->getItemIdForLink( 'foowiki', 'bar' )->getSerialization()
		);
	}

	public function testGetItemIdForLink_cacheMiss() {
		$cache = new HashBagOStuff();
		$lookup = $this->getMock( 'Wikibase\Lib\Store\SiteLinkLookup' );
		$lookup->expects( $this->once() )
			->method( 'getItemIdForLink' )
			->with( 'foowiki', 'bar' )
			->will( $this->returnValue( new ItemId( 'Q42' ) ) );

		$cachingSiteLinkLookup = new CachingSiteLinkLookup(
			$lookup,
			$cache
		);

		$this->assertSame(
			'Q42',
			$cachingSiteLinkLookup->getItemIdForLink( 'foowiki', 'bar' )->getSerialization()
		);

		// Make sure the new value also made it into the cache
		$this->assertSame(
			'Q42',
			$cache->get( 'wikibase:sitelinks-by-page:foowiki:bar' )
		);
	}

	public function testGetItemIdForSiteLink_cacheHit() {
		$siteLink = new SiteLink( 'foowiki', 'bar' );
		$cache = new HashBagOStuff();
		$cache->set( 'wikibase:sitelinks-by-page:foowiki:bar', 'Q42' );

		$cachingSiteLinkLookup = new CachingSiteLinkLookup(
			$this->getMock( 'Wikibase\Lib\Store\SiteLinkLookup' ),
			$cache
		);

		$this->assertSame(
			'Q42',
			$cachingSiteLinkLookup->getItemIdForSiteLink( $siteLink )->getSerialization()
		);
	}

	public function testGetItemIdForSiteLink_cacheMiss() {
		$siteLink = new SiteLink( 'foowiki', 'bar' );
		$cache = new HashBagOStuff();
		$lookup = $this->getMock( 'Wikibase\Lib\Store\SiteLinkLookup' );
		$lookup->expects( $this->once() )
			->method( 'getItemIdForLink' )
			->with( 'foowiki', 'bar' )
			->will( $this->returnValue( new ItemId( 'Q42' ) ) );

		$cachingSiteLinkLookup = new CachingSiteLinkLookup(
			$lookup,
			$cache
		);

		$this->assertSame(
			'Q42',
			$cachingSiteLinkLookup->getItemIdForSiteLink( $siteLink )->getSerialization()
		);

		// Make sure the new value also made it into the cache
		$this->assertSame(
			'Q42',
			$cache->get( 'wikibase:sitelinks-by-page:foowiki:bar' )
		);
	}

	public function testGetSiteLinksForItem_cacheHit() {
		$siteLinks = array( new SiteLink( 'foowiki', 'bar' ) );

		$cache = new HashBagOStuff();
		$cache->set( 'wikibase:sitelinks:Q42', $siteLinks );

		$cachingSiteLinkLookup = new CachingSiteLinkLookup(
			$this->getMock( 'Wikibase\Lib\Store\SiteLinkLookup' ),
			$cache
		);

		$this->assertSame(
			$siteLinks,
			$cachingSiteLinkLookup->getSiteLinksForItem( new ItemId( 'Q42' ) )
		);
	}

	public function testGetSiteLinksForItem_cacheMiss() {
		$siteLinks = array( new SiteLink( 'foowiki', 'bar' ) );
		$q42 = new ItemId( 'Q42' );

		$cache = new HashBagOStuff();
		$lookup = $this->getMock( 'Wikibase\Lib\Store\SiteLinkLookup' );
		$lookup->expects( $this->once() )
			->method( 'getSiteLinksForItem' )
			->with( $q42 )
			->will( $this->returnValue( $siteLinks ) );

		$cachingSiteLinkLookup = new CachingSiteLinkLookup( $lookup, $cache );

		$this->assertSame(
			$siteLinks,
			$cachingSiteLinkLookup->getSiteLinksForItem( new ItemId( 'Q42' ) )
		);

		// Make sure the new value also made it into the cache
		$this->assertSame( $siteLinks, $cache->get( 'wikibase:sitelinks:Q42' ) );
	}

	public function testGetLinks() {
		// getLinks is a simple pass through
		$lookup = $this->getMock( 'Wikibase\Lib\Store\SiteLinkLookup' );
		$lookup->expects( $this->once() )
			->method( 'getLinks' )
			->with( array( 1 ), array( 'a' ), array( 'b' ) )
			->will( $this->returnValue( 'bar' ) );

		$cachingSiteLinkLookup = new CachingSiteLinkLookup( $lookup, new HashBagOStuff() );

		$this->assertSame(
			'bar',
			$cachingSiteLinkLookup->getLinks( array( 1 ), array( 'a' ), array( 'b' ) )
		);
	}

}
