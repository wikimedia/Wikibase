<?php

namespace Wikibase\Client;

use CirrusSearch\SearchConfig;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Skin;
use Wikibase\Client\DataAccess\Scribunto\WikibaseEntityLibrary;
use Wikibase\Client\DataAccess\Scribunto\WikibaseLibrary;
use Wikibase\Client\Hooks\SkinAfterBottomScriptsHandler;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\Lib\ContentLanguages;
use Wikibase\Lib\Formatters\AutoCommentFormatter;
use Wikibase\Lib\StaticContentLanguages;
use Wikibase\Lib\UnionContentLanguages;
use Wikibase\Lib\WikibaseContentLanguages;

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
		return WikibaseClient::getNamespaceChecker()->isWikibaseEnabled( $namespace );
	}

	/**
	 * External library for Scribunto
	 *
	 * @param string $engine
	 * @param string[] &$extraLibraries
	 */
	public static function onScribuntoExternalLibraries( $engine, array &$extraLibraries ) {
		$allowDataTransclusion = WikibaseClient::getSettings()->getSetting( 'allowDataTransclusion' );
		if ( $engine == 'lua' && $allowDataTransclusion === true ) {
			$extraLibraries['mw.wikibase'] = WikibaseLibrary::class;
			$extraLibraries['mw.wikibase.entity'] = WikibaseEntityLibrary::class;
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
		$repoId = WikibaseClient::getSettings()->getSetting( 'repoSiteId' );

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
		$title = $skin->getTitle();
		$idString = $skin->getOutput()->getProperty( 'wikibase_item' );
		$entityId = null;

		if ( $idString !== null ) {
			$entityIdParser = WikibaseClient::getEntityIdParser();
			$entityId = $entityIdParser->parse( $idString );
		} elseif ( $title &&
			$skin->getActionName() !== 'view' && $title->exists()
		) {
			// Try to load the item ID from Database, but only do so on non-article views,
			// (where the article's OutputPage isn't available to us).
			$entityId = self::getEntityIdForTitle( $title );
		}

		if ( $entityId !== null ) {
			$repoLinker = WikibaseClient::getRepoLinker();

			return [
				// Warning: This id is misleading; the 't' refers to the link's original place in the toolbox,
				// it now lives in the other projects section, but we must keep the 't' for compatibility with gadgets.
				'id' => 't-wikibase',
				'icon' => 'logoWikidata',
				'text' => $skin->msg( 'wikibase-dataitem' )->text(),
				'href' => $repoLinker->getEntityUrl( $entityId ),
			];
		}

		return null;
	}

	/**
	 * @param Title $title
	 * @return EntityId|null
	 */
	private static function getEntityIdForTitle( Title $title ): ?EntityId {
		if ( !self::isWikibaseEnabled( $title->getNamespace() ) ) {
			return null;
		}

		$entityIdLookup = WikibaseClient::getEntityIdLookup();
		return $entityIdLookup->getEntityIdForTitle( $title );
	}

	/**
	 * Adds a preference for showing or hiding Wikidata entries in recent changes
	 *
	 * @param User $user
	 * @param array[] &$prefs
	 */
	public static function onGetPreferences( User $user, array &$prefs ) {
		$settings = WikibaseClient::getSettings();

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
	 * @param SearchConfig $config
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
		$services = MediaWikiServices::getInstance();
		$enabledNamespaces = WikibaseClient::getSettings( $services )
			->getSetting( 'pageSchemaNamespaces' );

		$out = $skin->getOutput();
		$entityId = self::parseEntityId( $out->getProperty( 'wikibase_item' ) );
		$title = $out->getTitle();
		if (
			!$entityId ||
			!$title ||
			!in_array( $title->getNamespace(), $enabledNamespaces ) ||
			!$title->exists()
		) {
			return true;
		}

		$handler = new SkinAfterBottomScriptsHandler(
			$services->getContentLanguage()->getCode(),
			WikibaseClient::getRepoLinker( $services ),
			WikibaseClient::getTermLookup( $services ),
			$services->getRevisionLookup()
		);
		$revisionTimestamp = $out->getRevisionTimestamp();
		$html .= $handler->createSchemaElement(
			$title,
			$revisionTimestamp,
			$entityId
		);

		return true;
	}

	private static function parseEntityId( ?string $prefixedId ): ?EntityId {
		if ( !$prefixedId ) {
			return null;
		}

		try {
			return WikibaseClient::getEntityIdParser()->parse( $prefixedId );
		} catch ( EntityIdParsingException $ex ) {
			return null;
		}
	}

	/**
	 * Used to propagate configuration for the linkitem feature to JavaScript.
	 * This is used in the "wikibase.client.linkitem.init" module.
	 */
	public static function getLinkitemConfiguration(): array {
		$cache = MediaWikiServices::getInstance()->getLocalServerObjectCache();
		$key = $cache->makeKey(
			'wikibase-client',
			'siteConfiguration'
		);
		return $cache->getWithSetCallback(
			$key,
			$cache::TTL_DAY, // when changing the TTL, also update linkItemTags in options.md
			function () {
				$site = WikibaseClient::getSite();
				$currentSite = [
					'globalSiteId' => $site->getGlobalId(),
					'languageCode' => $site->getLanguageCode(),
					'langLinkSiteGroup' => WikibaseClient::getLangLinkSiteGroup(),
				];
				$value = [ 'currentSite' => $currentSite ];

				$tags = WikibaseClient::getSettings()->getSetting( 'linkItemTags' );
				if ( $tags !== [] ) {
					$value['tags'] = $tags;
				}

				return $value;
			}
		);
	}

	/** @param ContentLanguages[] &$contentLanguages */
	public static function onWikibaseContentLanguages( array &$contentLanguages ): void {
		if ( !WikibaseClient::getSettings()->getSetting( 'tmpEnableMulLanguageCode' ) ) {
			return;
		}

		if ( $contentLanguages[WikibaseContentLanguages::CONTEXT_TERM]->hasLanguage( 'mul' ) ) {
			return;
		}

		$contentLanguages[WikibaseContentLanguages::CONTEXT_TERM] = new UnionContentLanguages(
			$contentLanguages[WikibaseContentLanguages::CONTEXT_TERM],
			new StaticContentLanguages( [ 'mul' ] )
		);
	}

}
