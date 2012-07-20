<?php

namespace Wikibase\Test;
use \Wikibase\SiteLink as SiteLink;
use \Wikibase\Site as Site;
use \Wikibase\Sites as Sites;

/**
 * Tests for the Wikibase\SiteLink class.
 *
 * @file
 * @since 0.1
 *
 * @ingroup Wikibase
 * @ingroup Test
 *
 * @group Wikibase
 * @group WikibaseItem
 * @group WikibaseLib
 *
 * This test needs the Sites table and sets it up, so we need the database:
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler <daniel.kinzler@wikimedia.de>
 */
class SiteLinkTest extends \MediaWikiTestCase {

	public function setUp() {
		parent::setUp();

		static $hasSites = false;

		if ( !$hasSites ) {
			\Wikibase\Utils::insertSitesForTests();
			$hasSites = true;
		}
	}

	public function testConstructor() {
		$link = new SiteLink( Sites::singleton()->getSiteByGlobalId( 'enwiki' ), "Foo" );
	}

	public function testGetPage() {
		$link = new SiteLink( Sites::singleton()->getSiteByGlobalId( 'enwiki' ), "Foo" );

		$this->assertEquals( "Foo", $link->getPage() );
	}

	public function testGetDBKey() {
		$link = new SiteLink( Sites::singleton()->getSiteByGlobalId( 'enwiki' ), "Foo Bar" );

		$this->assertEquals( "Foo_Bar", $link->getDBKey() );
	}

	public function testGetSite() {
		$link = new SiteLink( Sites::singleton()->getSiteByGlobalId( 'enwiki' ), "Foo" );

		$expected = Sites::singleton()->getSiteByGlobalId( "enwiki" );
		$this->assertEquals( $expected, $link->getSite() );
	}

	public function testGetSiteID() {
		$link = new SiteLink( "enwiki", "Foo" );

		$this->assertEquals( 'enwiki', $link->getSiteID() );
	}

	public function testUrl() {
		$link = new SiteLink( Sites::singleton()->getSiteByGlobalId( 'enwiki' ), "Foo Bar?/notes" );

		$this->assertEquals( "https://en.wikipedia.org/wiki/Foo_Bar%3F%2Fnotes", $link->getUrl() );
	}

	public function testToString() {
		$link = new SiteLink( Sites::singleton()->getSiteByGlobalId( 'enwiki' ), "Foo Bar" );

		$this->assertEquals( "enwiki:Foo_Bar", "$link" );
	}

	public function testNormalizePageTitle() {
		//NOTE: this does not actually call out to the enwiki site to perform the normalization,
		//      but uses a local Title object to do so. This is hardcoded on SiteLink::normalizePageTitle
		//      for the case that MW_PHPUNIT_TEST is set.
		$site = Sites::singleton()->getSiteByGlobalId( 'enwiki' );
		$title = SiteLink::normalizePageTitle( $site, " foo " );

		$this->assertEquals( "Foo", $title );
	}

	public function testNewFromText() {
		//NOTE: this does not actually call out to the enwiki site to perform the normalization,
		//      but uses a local Title object to do so. This is hardcoded on SiteLink::normalizePageTitle
		//      for the case that MW_PHPUNIT_TEST is set.
		$link = SiteLink::newFromText( "enwiki", " foo " );

		$this->assertEquals( "Foo", $link->getPage() );
	}

}
