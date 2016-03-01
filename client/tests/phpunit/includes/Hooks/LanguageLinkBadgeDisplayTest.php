<?php

namespace Wikibase\Client\Tests\Hooks;

use Language;
use OutputPage;
use ParserOutput;
use PHPUnit_Framework_TestCase;
use RequestContext;
use Title;
use Wikibase\Client\Hooks\LanguageLinkBadgeDisplay;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\SiteLink;
use Wikibase\DataModel\Term\Term;

/**
 * @covers Wikibase\Client\Hooks\LanguageLinkBadgeDisplay
 *
 * @since 0.5
 *
 * @group WikibaseClient
 * @group Wikibase
 *
 * @license GPL-2.0+
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class LanguageLinkBadgeDisplayTest extends PHPUnit_Framework_TestCase {

	/**
	 * @return LanguageLinkBadgeDisplay
	 */
	private function getLanguageLinkBadgeDisplay() {
		$labelLookup = $this->getMockBuilder(
				'Wikibase\DataModel\Services\Lookup\LabelDescriptionLookup'
			)
			->disableOriginalConstructor()
			->getMock();

		$labelLookup->expects( $this->any() )
			->method( 'getLabel' )
			->will( $this->returnCallback( function( EntityId $entityId ) {
				if ( $entityId->getSerialization() === 'Q3' ) {
					return new Term( 'de', 'Lesenswerter Artikel' );
				} elseif ( $entityId->getSerialization() === 'Q4' ) {
					return new Term( 'de', 'Exzellenter Artikel' );
				}

				return null;
			} ) );

		$badgeClassNames = array( 'Q4' => 'foo', 'Q3' => 'bar' );

		return new LanguageLinkBadgeDisplay(
			$labelLookup,
			$badgeClassNames,
			Language::factory( 'de' )
		);
	}

	/**
	 * @dataProvider attachBadgesToOutputProvider
	 */
	public function testAttachBadgesToOutput( array $expected, array $languageLinks ) {
		$languageLinkBadgeDisplay = $this->getLanguageLinkBadgeDisplay();
		$parserOutput = new ParserOutput();

		$languageLinkBadgeDisplay->attachBadgesToOutput( $languageLinks, $parserOutput );

		$this->assertEquals( $expected, $parserOutput->getExtensionData( 'wikibase_badges' ) );
	}

	public function attachBadgesToOutputProvider() {
		$q2 = new ItemId( 'Q2' );
		$q3 = new ItemId( 'Q3' );
		$q4 = new ItemId( 'Q4' );

		$link0 = new SiteLink( 'jawiki', 'Bah' );
		$link1 = new SiteLink( 'dewiki', 'Foo', array( $q3, $q2 ) );
		$link2 = new SiteLink( 'enwiki', 'Bar', array( $q3, $q4 ) );

		$badge1 = array(
			'class' => 'badge-Q3 bar badge-Q2',
			'label' => 'Lesenswerter Artikel'
		);
		$badge2 = array(
			'class' => 'badge-Q3 bar badge-Q4 foo',
			'label' => 'Lesenswerter Artikel, Exzellenter Artikel'
		);

		return array(
			'empty' => array( array(), array() ),
			'no badges' => array( array(), array( $link0 ) ),
			'some badges' => array(
				array( 'dewiki' => $badge1, 'enwiki' => $badge2 ),
				array( 'jawiki' => $link0, 'dewiki' => $link1, 'enwiki' => $link2 )
			),
		);
	}

	public function testApplyBadges() {
		$badges = array(
			'en' => array(
				'class' => 'badge-Q3',
				'label' => 'Lesenswerter Artikel',
			)
		);

		$link = array(
			'href' => 'http://acme.com',
			'class' => 'foo',
		);

		$expected = array(
			'href' => 'http://acme.com',
			'class' => 'foo badge-Q3',
			'itemtitle' => 'Lesenswerter Artikel',
		);

		$languageLinkTitle = Title::makeTitle( NS_MAIN, 'Test', '', 'en' );

		$context = new RequestContext();
		$output = new OutputPage( $context );
		$output->setProperty( 'wikibase_badges', $badges );

		$languageLinkBadgeDisplay = $this->getLanguageLinkBadgeDisplay();
		$languageLinkBadgeDisplay->applyBadges( $link, $languageLinkTitle, $output );

		$this->assertEquals( $expected, $link );
	}

}
