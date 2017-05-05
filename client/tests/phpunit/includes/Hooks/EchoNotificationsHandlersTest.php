<?php

namespace Wikibase\Client\Tests\Hooks;

use EchoEvent;
use MediaWikiTestCase;
use Title;
use Wikibase\ChangeRow;
use Wikibase\Client\Hooks\EchoNotificationsHandlers;
use Wikibase\Client\RepoLinker;
use Wikibase\Lib\Tests\Changes\TestChanges;
use Wikibase\SettingsArray;

/**
 * @covers Wikibase\Client\Hooks\EchoNotificationsHandlers
 *
 * @group Database
 * @group WikibaseClient
 * @group Wikibase
 */
class EchoNotificationsHandlersTestCase extends MediaWikiTestCase {

	/**
	 * @var RepoLinker
	 */
	private $repoLinker;

	protected function setUp() {
		parent::setUp();
		// if Echo is not loaded, skip this test
		if ( !class_exists( EchoEvent::class ) ) {
			$this->markTestSkipped( "Echo not loaded" );
		}

		$this->repoLinker = $this->getMockBuilder( RepoLinker::class )
			->disableOriginalConstructor()
			->getMock();
		$this->repoLinker
			->expects( $this->any() )
			->method( 'getEntityUrl' )
			->will( $this->returnValue( 'foo' ) );
	}

	/**
	 * @param SettingsArray $settings
	 *
	 * @return EchoNotificationsHandlers
	 */
	private function getHandlers( SettingsArray $settings ) {
		return new EchoNotificationsHandlers(
			$this->repoLinker,
			$settings->getSetting( 'siteGlobalID' ),
			$settings->getSetting( 'sendEchoNotification' ),
			$settings->getSetting( 'echoIcon' ),
			'repoSiteName'
		);
	}

	public function testWikibaseHandleChange() {
		/** @var ChangeRow[] $changes */
		$changes = TestChanges::getChanges();

		$settings = new SettingsArray();
		$settings->setSetting( 'siteGlobalID', 'enwiki' );
		$settings->setSetting( 'sendEchoNotification', true );
		$settings->setSetting( 'echoIcon', false );

		$handlers = $this->getHandlers( $settings );

		$special = [
			'change-dewiki-sitelink',
			'change-enwiki-sitelink',
			'set-enwiki-sitelink'
		];
		foreach ( $changes as $key => $change ) {
			if ( in_array( $key, $special ) ) {
				continue;
			}
			$this->assertFalse(
				$handlers->doWikibaseHandleChange( $change ),
				"Failed asserting that '$key' does not create an event"
			);
		}

		$setEn = $changes['set-enwiki-sitelink'];
		$changeEn = $changes['change-enwiki-sitelink'];

		Title::newFromText( 'Emmy' )->resetArticleID( 0 );
		$this->assertFalse(
			$handlers->doWikibaseHandleChange( $setEn ),
			"Failed asserting that non-existing 'Emmy' does not create an event"
		);

		$this->insertPage( 'Emmy' );
		$this->assertTrue(
			$handlers->doWikibaseHandleChange( $setEn ),
			"Failed asserting that 'Emmy' creates an event"
		);

		$settings->setSetting( 'siteGlobalID', 'dewiki' );
		$handlers = $this->getHandlers( $settings );

		$this->assertFalse(
			$handlers->doWikibaseHandleChange( $setEn ),
			"Failed asserting that 'dewiki' sitelink does not create an event"
		);

		$settings->setSetting( 'siteGlobalID', 'enwiki' );
		$handlers = $this->getHandlers( $settings );

		Title::newFromText( 'Emmy2' )->resetArticleID( 0 );
		$this->assertFalse(
			$handlers->doWikibaseHandleChange( $changeEn ),
			"Failed asserting that non-existing 'Emmy2' does not create an event"
		);

		$this->insertPage( 'Emmy2' );
		$this->assertTrue(
			$handlers->doWikibaseHandleChange( $changeEn ),
			"Failed asserting that 'Emmy2' creates an event"
		);

		$settings->setSetting( 'sendEchoNotification', false );
		$handlers = $this->getHandlers( $settings );
		$this->assertFalse(
			$handlers->doWikibaseHandleChange( $setEn ),
			"Failed asserting that configuration supresses creating an event"
		);

		$changeDe = $changes['change-dewiki-sitelink'];

		$settings->setSetting( 'siteGlobalID', 'dewiki' );
		$settings->setSetting( 'sendEchoNotification', true );
		$handlers = $this->getHandlers( $settings );

		Title::newFromText( 'Dummy2' )->resetArticleID( 0 );
		$this->assertFalse(
			$handlers->doWikibaseHandleChange( $changeDe ),
			"Failed asserting that 'Dummy' does not create an event"
		);

		$this->insertPage( 'Dummy2' );
		$this->assertFalse(
			$handlers->doWikibaseHandleChange( $changeDe ),
			"Failed asserting that 'Dummy2' does not create an event"
		);

		$this->insertPage( 'Dummy', '#REDIRECT [[Dummy2]]' );
		$this->assertFalse(
			$handlers->doWikibaseHandleChange( $changeDe ),
			"Failed asserting that 'Dummy2' redirected to by 'Dummy' does not create an event"
		);

		$this->insertPage( 'Dummy' );
		$this->assertTrue(
			$handlers->doWikibaseHandleChange( $changeDe ),
			"Failed asserting that 'Dummy2' creates an event"
		);
	}

	public function testBeforeCreateEchoEvent() {
		$settings = new SettingsArray();
		$settings->setSetting( 'siteGlobalID', 'enwiki' );
		$settings->setSetting( 'sendEchoNotification', false );
		$settings->setSetting( 'echoIcon', false );

		$notifications = [];
		$categories = [];
		$icons = [];

		$handlers = $this->getHandlers( $settings );
		$handlers->doBeforeCreateEchoEvent( $notifications, $categories, $icons );

		$this->assertFalse(
			isset( $notifications[$handlers::NOTIFICATION_TYPE] ),
			"Failed asserting that the notification type is registered to Echo"
		);

		$settings->setSetting( 'sendEchoNotification', true );
		$handlers = $this->getHandlers( $settings );
		$handlers->doBeforeCreateEchoEvent( $notifications, $categories, $icons );

		$this->assertArrayHasKey(
			$handlers::NOTIFICATION_TYPE,
			$notifications,
			"Failed asserting that the notification type is registered to Echo"
		);
		$this->assertArrayHasKey(
			'wikibase-action',
			$categories,
			"Failed asserting that the notification category is registered to Echo"
		);
		$this->assertArrayHasKey(
			$handlers::NOTIFICATION_TYPE,
			$icons,
			"Failed asserting that the notification icon is registered to Echo"
		);
		$this->assertEquals(
			[ 'path' => 'Wikibase/client/includes/Hooks/../../resources/images/echoIcon.svg' ],
			$icons[$handlers::NOTIFICATION_TYPE],
			"Failed asserting that missing echoIcon setting defaults to Echo's default"
		);

		$notifications = [];
		$categories = [];
		$icons = [];

		$echoIcon = [ 'url' => 'some_url_here' ];
		$settings->setSetting( 'echoIcon', $echoIcon );
		$handlers = $this->getHandlers( $settings );

		$handlers->doBeforeCreateEchoEvent( $notifications, $categories, $icons );
		$this->assertEquals(
			$echoIcon,
			$icons[$handlers::NOTIFICATION_TYPE],
			"Failed asserting that the notification icon is correctly registered to Echo"
		);
	}

}
