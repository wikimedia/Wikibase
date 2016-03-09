<?php

namespace Wikibase\Client\Hooks;

use Diff\DiffOp\DiffOp;
use Diff\DiffOp\DiffOpAdd;
use Diff\DiffOp\DiffOpChange;
use EchoEvent;
use MWNamespace;
use Title;
use User;
use Wikibase\Change;
use Wikibase\Client\Notifications\PageConnectionPresentationModel;
use Wikibase\Client\Notifications\PageConnectionFormatter;
use Wikibase\Client\RepoLinker;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\ItemChange;
use Wikibase\SettingsArray;
use WikiPage;

/**
 * Handlers for client Echo notifications
 * TODO: needs unit test
 *
 * @since 0.5
 */
class EchoNotificationsHandlers {

	/**
	 * Type of notification
	 */
	const NOTIFICATION_TYPE = 'page-connection';

	/**
	 * @var RepoLinker
	 */
	private $repoLinker;

	/**
	 * @var SettingsArray
	 */
	private $settings;

	/**
	 * @param RepoLinker
	 * @param SettingsArray
	 */
	public function __construct( RepoLinker $repoLinker, SettingsArray $settings ) {
		$this->repoLinker = $repoLinker;
		$this->settings = $settings;
	}

	/**
	 * @return self
	 */
	public static function newFromGlobalState() {
		$wikibaseClient = WikibaseClient::getDefaultInstance();

		return new self(
			$wikibaseClient->newRepoLinker(),
			$wikibaseClient->getSettings()
		);
	}

	/**
	 * Handler for BeforeCreateEchoEvent hook
	 * @see https://www.mediawiki.org/wiki/Notifications/Developer_guide
	 * @see https://www.mediawiki.org/wiki/Extension:Echo/BeforeCreateEchoEvent
	 *
	 * @param array &$notifications
	 * @param array &$notificationCategories
	 * @param array &$icons
	 */
	public static function onBeforeCreateEchoEvent(
		array &$notifications,
		array &$notificationCategories,
		array &$icons
	) {
		$notificationCategories['wikibase-action'] = [
			'priority' => 5, // really?
			'tooltip' => 'echo-pref-tooltip-page-connection',
		];

		$notifications[self::NOTIFICATION_TYPE] = [
			'user-locators' => [
				'EchoUserLocator::locateArticleCreator',
			],
			'category' => 'wikibase-action',
			'group' => 'neutral',
			'section' => 'alert',
			'presentation-model' => PageConnectionPresentationModel::class,
			'formatter-class' => PageConnectionFormatter::class,
			'bundle' => [ 'web' => true, 'email' => false ],
			'email-subject-message' => 'notification-page-connection-email-subject',
			'email-subject-params' => [ 'user', 'agent', 'page-count' ],
			'email-body-batch-message' => 'notification-page-connection-email-batch-body',
			'email-body-batch-params' => [ 'title', 'agent', 'item' ],
			'icon' => self::NOTIFICATION_TYPE,
		];

		$wikibaseClient = WikibaseClient::getDefaultInstance();
		$settings = $wikibaseClient->getSettings();

		$icon = $settings->getSetting( 'repoIcon' );
		if ( !empty( $icon ) ) {
			$icons[self::NOTIFICATION_TYPE] = $icon;
		}
	}

	/**
	 * Handler for EchoGetBundleRules hook
	 * @see https://www.mediawiki.org/wiki/Notifications/Developer_guide#Bundled_notifications
	 *
	 * @param EchoEvent $event
	 * @param string &$bundleString
	 */
	public static function onEchoGetBundleRules( EchoEvent $event, &$bundleString ) {
		if ( $event->getType() == self::NOTIFICATION_TYPE ) {
			$bundleString = self::NOTIFICATION_TYPE;
		}
		return;
	}

	/**
	 * Handler for WikibaseHandleChange hook
	 * @see doWikibaseHandleChange
	 *
	 * @param Change $change
	 */
	public static function onWikibaseHandleChange( Change $change ) {
		$self = self::newFromGlobalState();
		$self->doWikibaseHandleChange( $change );
	}

	/**
	 * @param Change $change
	 */
	public function doWikibaseHandleChange( Change $change ) {
		if ( !( $change instanceof ItemChange ) ) {
			return false;
		}

		$settings = $this->settings;
		if ( $settings->getSetting( 'sendEchoNotification' ) !== true ) {
			return false;
		}

		$siteLinkDiff = $change->getSiteLinkDiff();
		if ( $siteLinkDiff->isEmpty() ) {
			return false;
		}

		$siteId = $settings->getSetting( 'siteGlobalID' );
		if ( !isset( $siteLinkDiff[$siteId] ) || !isset( $siteLinkDiff[$siteId]['name'] ) ) {
			return false;
		}

		$siteLinkDiffOp = $siteLinkDiff[$siteId]['name'];

		$title = $this->getTitleForNotification( $siteLinkDiffOp );
		if ( $title !== false ) {
			$metadata = $change->getMetadata();
			$entityId = $change->getEntityId();
			$agent = User::newFromName( $metadata['user_text'], false );
			EchoEvent::create( [
				'agent' => $agent,
				'extra' => [
					'entityId' => $entityId,
					'url' => $this->repoLinker->getEntityUrl( $entityId ),
					// maybe also add diff link?
				],
				'title' => $title,
				'type' => self::NOTIFICATION_TYPE
			] );
			return true;
		}

		return false;
	}

	/**
	 * Determines whether the change was a real sitelink addition
	 * and returns either title, or false
	 *
	 * @param DiffOp $siteLinkDiffOp
	 *
	 * @return Title|false
	 */
	private function getTitleForNotification( DiffOp $siteLinkDiffOp ) {
		if ( $siteLinkDiffOp instanceof DiffOpAdd ) {
			$new = $siteLinkDiffOp->getNewValue();
			$newTitle = Title::newFromText( $new );
			return $this->canNotifyForTitle( $newTitle ) ? $newTitle : false;
		}

		// if it's a sitelink change, make sure it wasn't triggered by a page move
		if ( $siteLinkDiffOp instanceof DiffOpChange ) {
			$new = $siteLinkDiffOp->getNewValue();
			$newTitle = Title::newFromText( $new );

			if ( !$this->canNotifyForTitle( $newTitle ) ) {
				return false;
			}

			$old = $siteLinkDiffOp->getOldValue();
			$oldTitle = Title::newFromText( $old );

			// it's more likely that someone didn't create the redirect or deleted it
			// meanwhile than someone replaced a deleted sitelink by a valid one
			if ( !$oldTitle->exists() ) {
				return false;
			}

			// even if the old page is a redirect, make sure it redirects to the new title
			if ( $oldTitle->isRedirect() ) {
				$page = WikiPage::factory( $oldTitle );
				$targetTitle = $page->getRedirectTarget();
				if ( $targetTitle && $targetTitle->equals( $newTitle ) ) {
					return false;
				}
			}

			return $newTitle;
		}

		return false;
	}

	/**
	 * Whether it's reasonable to send a notification for the title
	 *
	 * @param Title $title
	 *
	 * @return boolean
	 */
	private function canNotifyForTitle( Title $title ) {
		return (bool)$title && $title->exists() && !$title->isRedirect()
			&& MWNamespace::isContent( $title->getNamespace() );
	}

}
