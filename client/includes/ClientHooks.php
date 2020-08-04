<?php

namespace Wikibase\Client;

use Action;
use MediaWiki\MediaWikiServices;
use Skin;
use Title;
use User;
use Wikibase\Client\DataAccess\Scribunto\Scribunto_LuaWikibaseEntityLibrary;
use Wikibase\Client\DataAccess\Scribunto\Scribunto_LuaWikibaseLibrary;
use Wikibase\Client\Hooks\SkinAfterBottomScriptsHandler;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\Lib\Formatters\AutoCommentFormatter;

/**
 * File defining the hook handlers for the Wikibase Client extension.
 *
 * @license GPL-2.0-or-later
 */
final class ClientHooks {

	/**
	 * @see NamespaceChecker::isWikibaseEnabled
	 *
	 * @param int $namespace
	 *
	 * @return bool
	 */
	protected static function isWikibaseEnabled( $namespace ) {
		return WikibaseClient::getDefaultInstance()->getNamespaceChecker()->isWikibaseEnabled( $namespace );
	}

	/**
	 * External library for Scribunto
	 *
	 * @param string $engine
	 * @param string[] &$extraLibraries
	 */
	public static function onScribuntoExternalLibraries( $engine, array &$extraLibraries ) {
		$allowDataTransclusion = WikibaseClient::getDefaultInstance()->getSettings()->getSetting( 'allowDataTransclusion' );
		if ( $engine == 'lua' && $allowDataTransclusion === true ) {
			$extraLibraries['mw.wikibase'] = Scribunto_LuaWikibaseLibrary::class;
			$extraLibraries['mw.wikibase.entity'] = Scribunto_LuaWikibaseEntityLibrary::class;
		}
	}

	/**
	 * Handler for the FormatAutocomments hook, implementing localized formatting
	 * for machine readable autocomments generated by SummaryFormatter.
	 *
	 * @param string &$comment reference to the autocomment text
	 * @param bool $pre true if there is content before the autocomment
	 * @param string $auto the autocomment unformatted
	 * @param bool $post true if there is content after the autocomment
	 * @param Title|null $title use for further information
	 * @param bool $local shall links be generated locally or globally
	 * @param string|null $wikiId The ID of the wiki the comment applies to, if not the local wiki.
	 */
	public static function onFormat( &$comment, $pre, $auto, $post, $title, $local, $wikiId = null ) {
		$wikibaseClient = WikibaseClient::getDefaultInstance();
		$repoId = $wikibaseClient->getSettings()->getSetting( 'repoSiteId' );

		// Only do special formatting for comments from a wikibase repo.
		// XXX: what to do if the local wiki is the repo? For entity pages, RepoHooks has a handler.
		// But what to do for other pages? Note that if the local wiki is the repo, $repoId will be
		// false, and $wikiId will be null.
		if ( $wikiId !== $repoId ) {
			return;
		}

		$formatter = new AutoCommentFormatter(
			MediaWikiServices::getInstance()->getContentLanguage(),
			[ 'wikibase-entity' ]
		);
		$formattedComment = $formatter->formatAutoComment( $auto );

		if ( is_string( $formattedComment ) ) {
			$comment = $formatter->wrapAutoComment( $pre, $formattedComment, $post );
		}
	}

	/**
	 * Build 'Wikidata item' link for later addition to the toolbox section of the sidebar
	 *
	 * @param Skin $skin
	 *
	 * @return string[]|null Array of link elements or Null if link cannot be created.
	 */
	public static function buildWikidataItemLink( Skin $skin ): ?array {
		$wbClient = WikibaseClient::getDefaultInstance();
		$title = $skin->getTitle();
		$idString = $skin->getOutput()->getProperty( 'wikibase_item' );
		$entityId = null;

		if ( $idString !== null ) {
			$entityIdParser = $wbClient->getEntityIdParser();
			$entityId = $entityIdParser->parse( $idString );
		} elseif ( $title &&
			Action::getActionName( $skin->getContext() ) !== 'view' && $title->exists()
		) {
			// Try to load the item ID from Database, but only do so on non-article views,
			// (where the article's OutputPage isn't available to us).
			$entityId = self::getEntityIdForTitle( $title, $wbClient );
		}

		if ( $entityId !== null ) {
			$repoLinker = $wbClient->newRepoLinker();

			return [
				'id' => 't-wikibase',
				'text' => $skin->msg( 'wikibase-dataitem' )->text(),
				'href' => $repoLinker->getEntityUrl( $entityId ),
			];
		}

		return null;
	}

	/**
	 * @param Title $title
	 * @param WikibaseClient $wbClient
	 *
	 * @return EntityId|null
	 */
	private static function getEntityIdForTitle( Title $title, WikibaseClient $wbClient ): ?EntityId {
		if ( !self::isWikibaseEnabled( $title->getNamespace() ) ) {
			return null;
		}

		$entityIdLookup = $wbClient->getEntityIdLookup();
		return $entityIdLookup->getEntityIdForTitle( $title );
	}

	/**
	 * Adds a preference for showing or hiding Wikidata entries in recent changes
	 *
	 * @param User $user
	 * @param array[] &$prefs
	 */
	public static function onGetPreferences( User $user, array &$prefs ) {
		$settings = WikibaseClient::getDefaultInstance()->getSettings();

		if ( !$settings->getSetting( 'showExternalRecentChanges' ) ) {
			return;
		}

		$prefs['rcshowwikidata'] = [
			'type' => 'toggle',
			'label-message' => 'wikibase-rc-show-wikidata-pref',
			'section' => 'rc/advancedrc',
		];

		$prefs['wlshowwikibase'] = [
			'type' => 'toggle',
			'label-message' => 'wikibase-watchlist-show-changes-pref',
			'section' => 'watchlist/advancedwatchlist',
		];
	}

	/**
	 * Add morelikewithwikibase keyword.
	 * @param $config
	 * @param array &$extraFeatures
	 */
	public static function onCirrusSearchAddQueryFeatures(
		$config,
		array &$extraFeatures
	) {
		$extraFeatures[] = new MoreLikeWikibase( $config );
	}

	/**
	 * Injects a Wikidata inline JSON-LD script schema for search engine optimization.
	 *
	 * @param Skin $skin
	 * @param string &$html
	 *
	 * @return bool Always true.
	 */
	public static function onSkinAfterBottomScripts( Skin $skin, &$html ) {
		$client = WikibaseClient::getDefaultInstance();
		$enabledNamespaces = $client->getSettings()->getSetting( 'pageSchemaNamespaces' );

		$out = $skin->getOutput();
		$entityId = self::parseEntityId( $client, $out->getProperty( 'wikibase_item' ) );
		$title = $out->getTitle();
		if (
			!$entityId ||
			!$title ||
			!in_array( $title->getNamespace(), $enabledNamespaces ) ||
			!$title->exists()
		) {
			return true;
		}

		$handler = new SkinAfterBottomScriptsHandler( $client, $client->newRepoLinker() );
		$revisionTimestamp = $out->getRevisionTimestamp();
		$html .= $handler->createSchemaElement(
			$title,
			$revisionTimestamp,
			$entityId
		);

		return true;
	}

	/**
	 * @param WikibaseClient $client
	 * @param string|null $prefixedId
	 *
	 * @return EntityId|null
	 */
	private static function parseEntityId( WikibaseClient $client, $prefixedId = null ) {
		if ( !$prefixedId ) {
			return null;
		}

		try {
			return $client->getEntityIdParser()->parse( $prefixedId );
		} catch ( EntityIdParsingException $ex ) {
			return null;
		}
	}

	/**
	 * Used to propagate information about the current site to JavaScript.
	 * This is used in "wikibase.client.linkitem.init" module
	 */
	public static function getSiteConfiguration() {
		$cache = MediaWikiServices::getInstance()->getLocalServerObjectCache();
		$key = $cache->makeKey(
			'wikibase-client',
			'siteConfiguration'
		);
		return $cache->getWithSetCallback(
			$key,
			$cache::TTL_DAY,
			function () {
				$wikibaseClient = WikibaseClient::getDefaultInstance();

				$site = $wikibaseClient->getSite();
				$currentSite = [
					'globalSiteId' => $site->getGlobalId(),
					'languageCode' => $site->getLanguageCode(),
					'langLinkSiteGroup' => $wikibaseClient->getLangLinkSiteGroup()
				];

				return [ 'currentSite' => $currentSite ];
			},
			// @fixme These options only exist in WanObjectCache, but this code is using BagOStuff!
			// @phan-suppress-next-line PhanTypeMismatchArgument
			[ 'lockTSE' => 10, 'pcTTL' => $cache::TTL_PROC_LONG ]
		);
	}

}
