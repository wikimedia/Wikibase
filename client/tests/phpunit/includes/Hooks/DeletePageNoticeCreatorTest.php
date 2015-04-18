<?php

namespace Wikibase\Client\Tests\Hooks;

use Language;
use Title;
use Wikibase\Client\Hooks\DeletePageNoticeCreator;
use Wikibase\Client\RepoLinker;
use Wikibase\DataModel\Entity\ItemId;

/**
 * @covers Wikibase\Client\Hooks\DeletePageNoticeCreator
 *
 * @group WikibaseClient
 * @group Wikibase
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 */
class DeletePageNoticeCreatorTest extends \MediaWikiTestCase {

	protected function setUp() {
		parent::setUp();

		$this->setMwGlobals( array(
			'wgLang' => Language::factory( 'de' )
		) );
	}

	protected function getRepoLinker() {
		$baseUrl = 'http://www.example.com';
		$articlePath = '/wiki/$1';
		$scriptPath = '';
		$repoNamespaces = array(
			'wikibase-item' => '',
			'wikibase-property' => 'Property:'
		);

		return new RepoLinker( $baseUrl, $articlePath, $scriptPath, $repoNamespaces );
	}

	/**
	 * @dataProvider getPageDeleteNoticeHtmlProvider
	 */
	public function testGetPageDeleteNoticeHtml( $expected, Title $title, $message ) {
		$siteLinkLookup = $this->getMock(
			'Wikibase\Lib\Store\SiteLinkTable',
			array( 'getItemIdForSiteLink' ),
			array( 'SiteLinkTable', true )
		);

		$siteLinkLookup->expects( $this->any() )
			->method( 'getItemIdForSiteLink' )
			->will( $this->returnValue( new ItemId( 'Q4880' ) ) );

		$deletePageNotice = new DeletePageNoticeCreator(
			$siteLinkLookup,
			'dewiki',
			$this->getRepoLinker()
		);

		$this->assertEquals(
			$expected,
			$deletePageNotice->getPageDeleteNoticeHtml( $title ),
			$message
		);
	}

	public function getPageDeleteNoticeHtmlProvider() {
		$title = Title::newFromText( 'New Amsterdam' );
		$expected = $this->getParsedMessage( 'wikibase-after-page-delete' );

		$title2 = Title::newFromText( 'New York' );
		$title2->wikibasePushedDeleteToRepo = true;
		$expected2 = $this->getParsedMessage( 'wikibase-after-page-delete-queued' );

		return array(
			array( $expected, $title, 'after page delete' ),
			array( $expected2, $title2, 'page delete queued' )
		);
	}

	protected function getParsedMessage( $messageKey ) {
		return '<div class="plainlinks">'
			. wfMessage( $messageKey, 'http://www.example.com/wiki/Q4880' )
				->inLanguage( 'de' )->parse()
			. '</div>';
	}

}
