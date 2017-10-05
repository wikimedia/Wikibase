<?php

namespace Wikibase\Client\Hooks;

use Diff\DiffOp\DiffOp;
use Diff\DiffOp\DiffOpAdd;
use Diff\DiffOp\DiffOpChange;
use EchoAttributeManager;
use EchoEvent;
use EchoUserLocator;
use Title;
use User;
use Wikibase\Change;
use Wikibase\Client\NamespaceChecker;
use Wikibase\Client\Notifications\PageConnectionPresentationModel;
use Wikibase\Client\RepoLinker;
use Wikibase\Client\WikibaseClient;
use Wikibase\ItemChange;
use WikiPage;

/**
 * Handlers for hooks (e.g. BeforeCreateEchoEvent) called when Echo extension
 * is initialized, so on every page load.
 *
 * @license GPL-2.0+
 * @author Matěj Suchánek
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class EchoSetupHookHandlers {

	/**
	 * Type of notification
	 */
	const NOTIFICATION_TYPE = 'page-connection';

	/**
	 * @var bool
	 */
	private $sendEchoNotification;

	/**
	 * @var array|false
	 */
	private $echoIcon;

	/**
	 * @param bool $sendEchoNotification
	 * @param array|false $echoIcon
	 */
	public function __construct( $sendEchoNotification, $echoIcon ) {
		$this->sendEchoNotification = $sendEchoNotification;
		$this->echoIcon = $echoIcon;
	}

	/**
	 * @return self
	 */
	public static function newFromGlobalState() {
		$wikibaseClient = WikibaseClient::getDefaultInstance();
		$settings = $wikibaseClient->getSettings();

		return new self(
			$settings->getSetting( 'sendEchoNotification' ),
			$settings->getSetting( 'echoIcon' )
		);
	}

	/**
	 * Handler for BeforeCreateEchoEvent hook
	 * @see https://www.mediawiki.org/wiki/Extension:Echo/BeforeCreateEchoEvent
	 * @see doBeforeCreateEchoEvent
	 *
	 * @param array[] &$notifications
	 * @param array[] &$notificationCategories
	 * @param array[] &$icons
	 */
	public static function onBeforeCreateEchoEvent(
		array &$notifications,
		array &$notificationCategories,
		array &$icons
	) {
		$self = self::newFromGlobalState();
		$self->doBeforeCreateEchoEvent( $notifications, $notificationCategories, $icons );
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Notifications/Developer_guide
	 *
	 * @param array[] &$notifications
	 * @param array[] &$notificationCategories
	 * @param array[] &$icons
	 */
	public function doBeforeCreateEchoEvent(
		array &$notifications,
		array &$notificationCategories,
		array &$icons
	) {
		if ( $this->sendEchoNotification !== true ) {
			return;
		}

		$notificationCategories['wikibase-action'] = [
			'priority' => 5,
			'tooltip' => 'echo-pref-tooltip-wikibase-action',
		];

		$notifications[self::NOTIFICATION_TYPE] = [
			EchoAttributeManager::ATTR_LOCATORS => [
				EchoUserLocator::class . '::locateArticleCreator',
			],
			'category' => 'wikibase-action',
			'group' => 'neutral',
			'section' => 'message',
			'presentation-model' => PageConnectionPresentationModel::class,
			'bundle' => [ 'web' => true, 'email' => false ],
		];

		if ( !empty( $this->echoIcon ) ) {
			$icons[self::NOTIFICATION_TYPE] = $this->echoIcon;
		} else {
			preg_match( '+/extensions/(.*)+', __DIR__, $remoteExtPath );
			$iconPath = $remoteExtPath[1] . '/../../resources/images/echoIcon.svg';
			$icons[self::NOTIFICATION_TYPE] = [ 'path' => $iconPath ];
		}
	}

}
