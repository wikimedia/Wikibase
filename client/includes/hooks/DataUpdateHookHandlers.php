<?php

namespace Wikibase\Client\Hooks;

use Content;
use ManualLogEntry;
use Title;
use User;
use Wikibase\Client\Store\UsageUpdater;
use Wikibase\Client\Usage\ParserOutputUsageAccumulator;
use Wikibase\Client\WikibaseClient;
use Wikibase\NamespaceChecker;
use WikiPage;

/**
 * Hook handlers for triggering data updates.
 *
 * This class has a static interface for use with MediaWiki's hook mechanism; the static
 * handler functions will create a new instance of DataUpdateHookHandlers and then call the
 * corresponding member function on that.
 *
 * @since 0.5.
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class DataUpdateHookHandlers {

	/**
	 * @var NamespaceChecker
	 */
	private $namespaceChecker;

	/**
	 * @var UsageUpdater
	 */
	private $usageUpdater;

	public static function newFromGlobalState() {
		$wikibaseClient = WikibaseClient::getDefaultInstance();
		$settings = $wikibaseClient->getSettings();

		$namespaceChecker = $wikibaseClient->getNamespaceChecker();
		$usageUpdater = new UsageUpdater(
			$settings->getSetting( 'siteGlobalID' ),
			$wikibaseClient->getStore()->getUsageTracker(),
			$wikibaseClient->getStore()->getUsageLookup(),
			$wikibaseClient->getStore()->getSubscriptionManager()
		);

		return new DataUpdateHookHandlers(
			$namespaceChecker,
			$usageUpdater
		);
	}

	/**
	 * Static handler for the ArticleEditUpdates hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ArticleEditUpdates
	 * @see doArticleEditUpdates
	 *
	 * @param WikiPage $page The WikiPage object managing the edit
	 * @param object $editInfo The current edit info object.
	 *        $editInfo->output is an ParserOutput object.
	 * @param bool $changed False if this is a null edit
	 */
	public static function onArticleEditUpdates( WikiPage $page, &$editInfo, $changed ) {
		$handler = self::newFromGlobalState();
		$handler->doArticleEditUpdates( $page, $editInfo, $changed );
	}

	/**
	 * Static handler for ArticleDeleteComplete
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ArticleDeleteComplete
	 * @see doArticleDeleteComplete
	 *
	 * @param WikiPage $article
	 * @param User $user
	 * @param string $reason
	 * @param int $id id of the article that was deleted
	 * @param Content $content
	 * @param ManualLogEntry $logEntry
	 *
	 * @return bool
	 */
	public static function onArticleDeleteComplete(
		WikiPage &$article,
		User &$user,
		$reason,
		$id,
		Content $content,
		ManualLogEntry $logEntry
	) {
		$title = $article->getTitle();

		$handler = self::newFromGlobalState();
		$handler->doArticleDeleteComplete( $title->getNamespace(), $id );
	}

	public function __construct(
		NamespaceChecker $namespaceChecker,
		UsageUpdater $usageUpdater
	) {

		$this->namespaceChecker = $namespaceChecker;
		$this->usageUpdater = $usageUpdater;
	}

	/**
	 * Hook run after a new revision was stored
	 *
	 * @param WikiPage $page The WikiPage object managing the edit
	 * @param object $editInfo The current edit info object.
	 *        $editInfo->output is an ParserOutput object.
	 * @param bool $changed False if this is a null edit
	 */
	public function doArticleEditUpdates( WikiPage $page, &$editInfo, $changed ) {
		$title = $page->getTitle();

		if ( !$this->namespaceChecker->isWikibaseEnabled( $title->getNamespace() ) ) {
			// shorten out
			return;
		}

		$usageAcc = new ParserOutputUsageAccumulator( $editInfo->output );

		$this->usageUpdater->updateUsageForPage(
			$title->getArticleId(),
			$usageAcc->getUsages()
		);
	}

	/**
	 * Hook run after a page was deleted.
	 *
	 * @param int $namespaceId
	 * @param int $pageId
	 */
	public function doArticleDeleteComplete( $namespace, $pageId ) {
		if ( !$this->namespaceChecker->isWikibaseEnabled( $namespace ) ) {
			// shorten out
			return;
		}

		$this->usageUpdater->updateUsageForPage(
			$pageId,
			array()
		);
	}

}
