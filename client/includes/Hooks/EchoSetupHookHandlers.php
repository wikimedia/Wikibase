<?php

namespace Wikibase\Client\Hooks;

use EchoAttributeManager;
use EchoUserLocator;
use Wikibase\Client\Notifications\PageConnectionPresentationModel;
use Wikibase\Client\WikibaseClient;

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

		$notifications[EchoNotificationsHandlers::NOTIFICATION_TYPE] = [
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
			$icons[EchoNotificationsHandlers::NOTIFICATION_TYPE] = $this->echoIcon;
		} else {
			preg_match( '+/extensions/(.*)+', __DIR__, $remoteExtPath );
			$iconPath = $remoteExtPath[1] . '/../../resources/images/echoIcon.svg';
			$icons[EchoNotificationsHandlers::NOTIFICATION_TYPE] = [ 'path' => $iconPath ];
		}
	}

}
