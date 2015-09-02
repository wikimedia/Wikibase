<?php

namespace Wikibase;

use Action;
use BaseTemplate;
use ChangesList;
use FormOptions;
use IContextSource;
use Message;
use OutputPage;
use Parser;
use QuickTemplate;
use RecentChange;
use Skin;
use SpecialRecentChanges;
use SpecialWatchlist;
use Title;
use UnexpectedValueException;
use User;
use Wikibase\Client\Hooks\BaseTemplateAfterPortletHandler;
use Wikibase\Client\Hooks\BeforePageDisplayHandler;
use Wikibase\Client\Hooks\ChangesPageWikibaseFilterHandler;
use Wikibase\Client\Hooks\DeletePageNoticeCreator;
use Wikibase\Client\Hooks\InfoActionHookHandler;
use Wikibase\Client\Hooks\SpecialWatchlistQueryHandler;
use Wikibase\Client\RecentChanges\ChangeLineFormatter;
use Wikibase\Client\RecentChanges\ExternalChangeFactory;
use Wikibase\Client\RecentChanges\RecentChangesFilterOptions;
use Wikibase\Client\RepoItemLinkGenerator;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\SiteLink;

/**
 * File defining the hook handlers for the Wikibase Client extension.
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 *
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Jens Ohlig
 * @author Daniel Kinzler
 * @author Tobias Gritschacher
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Marius Hoch < hoo@online.de >
 * @author Bene* < benestar.wikimedia@gmail.com >
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
	 * Hook to add PHPUnit test cases.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UnitTestsList
	 *
	 * @since 0.1
	 *
	 * @param string[] &$paths
	 *
	 * @return bool
	 */
	public static function registerUnitTests( array &$paths ) {
		$paths[] = __DIR__ . '/tests/phpunit/';

		return true;
	}

	/**
	 * External library for Scribunto
	 *
	 * @since 0.4
	 *
	 * @param string $engine
	 * @param array $extraLibraries
	 * @return bool
	 */
	public static function onScribuntoExternalLibraries( $engine, array &$extraLibraries ) {
		$allowDataTransclusion = WikibaseClient::getDefaultInstance()->getSettings()->getSetting( 'allowDataTransclusion' );
		if ( $engine == 'lua' && $allowDataTransclusion === true ) {
			$extraLibraries['mw.wikibase'] = 'Wikibase\Client\DataAccess\Scribunto\Scribunto_LuaWikibaseLibrary';
			$extraLibraries['mw.wikibase.entity'] = 'Wikibase\Client\DataAccess\Scribunto\Scribunto_LuaWikibaseEntityLibrary';
		}

		return true;
	}

	/**
	 * Hook for modifying the query for fetching recent changes
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SpecialRecentChangesQuery
	 *
	 * @since 0.2
	 *
	 * @param array &$conds
	 * @param string[] &$tables
	 * @param array &$join_conds
	 * @param FormOptions $opts
	 * @param array &$query_options
	 * @param string[] &$fields
	 *
	 * @return bool
	 */
	public static function onSpecialRecentChangesQuery( array &$conds, array &$tables,
		array &$join_conds, FormOptions $opts, array &$query_options, array &$fields
	) {
		$rcFilterOpts = new RecentChangesFilterOptions( $opts );

		if ( $rcFilterOpts->showWikibaseEdits() === false ) {
			$conds[] = 'rc_type != ' . RC_EXTERNAL;
		}

		return true;
	}

	/**
	 * Hook for formatting recent changes linkes
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/OldChangesListRecentChangesLine
	 *
	 * @since 0.2
	 *
	 * @param ChangesList $changesList
	 * @param string $s
	 * @param RecentChange $rc
	 * @param string[] &$classes
	 *
	 * @return bool
	 */
	public static function onOldChangesListRecentChangesLine( ChangesList &$changesList, &$s,
		RecentChange $rc, &$classes = array() ) {

		$type = $rc->getAttribute( 'rc_type' );

		if ( $type == RC_EXTERNAL ) {
			$wikibaseClient = WikibaseClient::getDefaultInstance();
			$changeFactory = new ExternalChangeFactory(
				$wikibaseClient->getSettings()->getSetting( 'repoSiteId' )
			);

			try {
				$externalChange = $changeFactory->newFromRecentChange( $rc );
			} catch ( UnexpectedValueException $ex ) {
				// @fixme use rc_source column to better distinguish
				// Wikibase changes vs. non-wikibase, and unexpected
				// stuff in Wikibase changes.
				wfWarn( 'Invalid or not a Wikibase change.' );
				return false;
			}

			// fixme: inject formatter and flags into a changes list formatter
			$formatter = new ChangeLineFormatter(
				$changesList->getUser(),
				$changesList->getLanguage(),
				$wikibaseClient->newRepoLinker()
			);

			$flag = $changesList->recentChangesFlags( array( 'wikibase-edit' => true ), '' );
			$line = $formatter->format( $externalChange, $rc->getTitle(), $rc->counter, $flag );

			$classes[] = 'wikibase-edit';
			$s = $line;
		}

		// OutputPage will ignore multiple calls
		$changesList->getOutput()->addModuleStyles( 'wikibase.client.changeslist.css' );

		return true;
	}

	/**
	 * Modifies watchlist query to include external changes
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SpecialWatchlistQuery
	 *
	 * @since 0.2
	 *
	 * @param array &$conds
	 * @param array &$tables
	 * @param array &$join_conds
	 * @param array &$fields
	 * @param FormOptions|array|null $opts MediaWiki 1.22 used an array and MobileFrontend still does.
	 *
	 * @return bool
	 */
	public static function onSpecialWatchlistQuery( array &$conds, array &$tables,
		array &$join_conds, array &$fields, $opts
	) {
		$db = wfGetDB( DB_SLAVE );
		$settings = WikibaseClient::getDefaultInstance()->getSettings();
		$showExternalChanges = $settings->getSetting( 'showExternalRecentChanges' );

		$handler = new SpecialWatchlistQueryHandler( $GLOBALS['wgUser'], $db, $showExternalChanges );

		$conds = $handler->addWikibaseConditions( $GLOBALS['wgRequest'], $conds, $opts );

		return true;
	}

	/**
	 * Add Wikibase item link in toolbox
	 *
	 * @since 0.4
	 *
	 * @param BaseTemplate $baseTemplate
	 * @param array $toolbox
	 *
	 * @return bool
	 */
	public static function onBaseTemplateToolbox( BaseTemplate $baseTemplate, array &$toolbox ) {
		$wikibaseClient = WikibaseClient::getDefaultInstance();
		$skin = $baseTemplate->getSkin();
		$idString = $skin->getOutput()->getProperty( 'wikibase_item' );
		$entityId = null;

		if ( $idString !== null ) {
			$entityIdParser = $wikibaseClient->getEntityIdParser();
			$entityId = $entityIdParser->parse( $idString );
		} elseif ( Action::getActionName( $skin ) !== 'view' ) {
			// Try to load the item ID from Database, but only do so on non-article views,
			// (where the article's OutputPage isn't available to us).
			$entityId = self::getEntityIdForTitle( $skin->getTitle() );
		}

		if ( $entityId !== null ) {
			$repoLinker = $wikibaseClient->newRepoLinker();
			$toolbox['wikibase'] = array(
				'text' => $baseTemplate->getMsg( 'wikibase-dataitem' )->text(),
				'href' => $repoLinker->getEntityUrl( $entityId ),
				'id' => 't-wikibase'
			);
		}

		return true;
	}

	/**
	 * @param Title|null $title
	 *
	 * @return EntityId|null
	 */
	private static function getEntityIdForTitle( Title $title = null ) {
		if ( $title === null || !self::isWikibaseEnabled( $title->getNamespace() ) ) {
			return null;
		}

		$wikibaseClient = WikibaseClient::getDefaultInstance();
		$siteLinkLookup = $wikibaseClient->getStore()->getSiteLinkLookup();
		return $siteLinkLookup->getItemIdForSiteLink(
			new SiteLink(
				$wikibaseClient->getSettings()->getSetting( 'siteGlobalID' ),
				$title->getFullText()
			)
		);
	}

	/**
	 * Add the connected item prefixed id as a JS config variable, for gadgets etc.
	 *
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 *
	 * @since 0.4
	 *
	 * @return bool
	 */
	public static function onBeforePageDisplayAddJsConfig( OutputPage &$out, Skin &$skin ) {
		$prefixedId = $out->getProperty( 'wikibase_item' );

		if ( $prefixedId !== null ) {
			$out->addJsConfigVars( 'wgWikibaseItemId', $prefixedId );
		}

		return true;
	}

	/**
	 * Adds css for the edit links sidebar link or JS to create a new item
	 * or to link with an existing one.
	 *
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 *
	 * @since 0.1
	 *
	 * @return bool
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		$namespaceChecker = WikibaseClient::getDefaultInstance()->getNamespaceChecker();
		$beforePageDisplayHandler = new BeforePageDisplayHandler( $namespaceChecker );

		$actionName = Action::getActionName( $skin->getContext() );
		$beforePageDisplayHandler->addModules( $out, $actionName );

		return true;
	}

	/**
	 * Displays a list of links to pages on the central wiki at the end of the language box.
	 *
	 * @since 0.1
	 *
	 * @param Skin $skin
	 * @param QuickTemplate $template
	 *
	 * @return bool
	 */
	public static function onSkinTemplateOutputPageBeforeExec( Skin &$skin, QuickTemplate &$template ) {
		$title = $skin->getContext()->getTitle();
		$wikibaseClient = WikibaseClient::getDefaultInstance();

		if ( !self::isWikibaseEnabled( $title->getNamespace() ) ) {
			// shorten out
			return true;
		}

		$repoLinker = $wikibaseClient->newRepoLinker();
		$entityIdParser = $wikibaseClient->getEntityIdParser();

		$siteGroup = $wikibaseClient->getLangLinkSiteGroup();

		$languageUrls = $template->get( 'language_urls' );
		$hasLangLinks = $languageUrls !== false && !empty( $languageUrls );

		$langLinkGenerator = new RepoItemLinkGenerator(
			WikibaseClient::getDefaultInstance()->getNamespaceChecker(),
			$repoLinker,
			$entityIdParser,
			$siteGroup,
			$wikibaseClient->getSettings()->getSetting( 'siteGlobalID' )
		);

		$action = Action::getActionName( $skin->getContext() );

		$noExternalLangLinks = $skin->getOutput()->getProperty( 'noexternallanglinks' );
		$prefixedId = $skin->getOutput()->getProperty( 'wikibase_item' );

		$editLink = $langLinkGenerator->getLink( $title, $action, $hasLangLinks, $noExternalLangLinks, $prefixedId );

		// there will be no link in some situations, like add links widget disabled
		if ( $editLink ) {
			$template->set( 'wbeditlanglinks', $editLink );
		}

		// Needed to have "Other languages" section display, so we can add "add links".
		// Only force the section to display if we are going to actually add such a link:
		// Where external langlinks aren't suppressed and where action == 'view'.
		if ( $languageUrls === false && $title->exists()
			&& ( $noExternalLangLinks === null || !in_array( '*', $noExternalLangLinks ) )
			&& $action === 'view'
		) {
			$template->set( 'language_urls', array() );
		}

		return true;
	}

	/**
	 * Initialise beta feature preferences
	 *
	 * @since 0.5
	 *
	 * @param User $user
	 * @param array $betaPreferences
	 *
	 * @return bool
	 */
	public static function onGetBetaFeaturePreferences( User $user, array &$betaPreferences ) {
		global $wgExtensionAssetsPath;

		preg_match( '+' . preg_quote( DIRECTORY_SEPARATOR ) . '(?:vendor|extensions)'
			. preg_quote( DIRECTORY_SEPARATOR ) . '.*+', __DIR__, $remoteExtPath );

		$assetsPath = $wgExtensionAssetsPath . DIRECTORY_SEPARATOR . '..' . $remoteExtPath[0];

		$settings = WikibaseClient::getDefaultInstance()->getSettings();
		if ( !$settings->getSetting( 'otherProjectsLinksBeta' ) || $settings->getSetting( 'otherProjectsLinksByDefault' ) ) {
			return true;
		}

		$betaPreferences['wikibase-otherprojects'] = array(
			'label-message' => 'wikibase-otherprojects-beta-message',
			'desc-message' => 'wikibase-otherprojects-beta-description',
			'screenshot' => array(
				'ltr' => $assetsPath . '/resources/images/wb-otherprojects-beta-ltr.svg',
				'rtl' => $assetsPath . '/resources/images/wb-otherprojects-beta-rtl.svg'
			),
			'info-link' => 'https://www.mediawiki.org/wiki/Wikibase/Beta_Features/Other_projects_sidebar',
			'discussion-link' => 'https://www.mediawiki.org/wiki/Talk:Wikibase/Beta_Features/Other_projects_sidebar'
		);

		return true;
	}

	/**
	 * Adds a toggle for showing/hiding Wikidata entries in recent changes
	 *
	 * @param SpecialRecentChanges $special
	 * @param array &$filters
	 *
	 * @return bool
	 */
	public static function onSpecialRecentChangesFilters( SpecialRecentChanges $special, array &$filters ) {
		$hookHandler = new ChangesPageWikibaseFilterHandler(
			$special->getContext(),
			WikibaseClient::getDefaultInstance()->getSettings()->getSetting( 'showExternalRecentChanges' ),
			'hidewikidata',
			'rcshowwikidata',
			'wikibase-rc-hide-wikidata'
		);

		// @fixme remove wikidata-specific stuff!
		$filters = $hookHandler->addFilterIfEnabled( $filters );

		return true;
	}

	/**
	 * Modifies watchlist options to show a toggle for Wikibase changes
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SpecialWatchlistFilters
	 *
	 * @since 0.4
	 *
	 * @param SpecialWatchlist $special
	 * @param array $filters
	 *
	 * @return bool
	 */
	public static function onSpecialWatchlistFilters( $special, &$filters ) {
		$hookHandler = new ChangesPageWikibaseFilterHandler(
			$special->getContext(),
			WikibaseClient::getDefaultInstance()->getSettings()->getSetting( 'showExternalRecentChanges' ),
			'hideWikibase',
			'wlshowwikibase',
			'wikibase-rc-hide-wikidata'
		);

		$filters = $hookHandler->addFilterIfEnabled( $filters );

		return true;
	}

	/**
	 * Adds a preference for showing or hiding Wikidata entries in recent changes
	 *
	 * @param User $user
	 * @param array[] &$prefs
	 *
	 * @return bool
	 */
	public static function onGetPreferences( User $user, array &$prefs ) {
		$settings = WikibaseClient::getDefaultInstance()->getSettings();

		if ( !$settings->getSetting( 'showExternalRecentChanges' ) ) {
			return true;
		}

		$prefs['rcshowwikidata'] = array(
			'type' => 'toggle',
			'label-message' => 'wikibase-rc-show-wikidata-pref',
			'section' => 'rc/advancedrc',
		);

		$prefs['wlshowwikibase'] = array(
			'type' => 'toggle',
			'label-message' => 'wikibase-watchlist-show-changes-pref',
			'section' => 'watchlist/advancedwatchlist',
		);

		return true;
	}

	/**
	 * Register the parser functions.
	 *
	 * @param $parser Parser
	 *
	 * @return bool
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		WikibaseClient::getDefaultInstance()->getParserFunctionRegistrant()->register( $parser );

		return true;
	}

	/**
	 * Register the magic word.
	 */
	public static function onMagicWordwgVariableIDs( &$aCustomVariableIds ) {
		$aCustomVariableIds[] = 'noexternallanglinks';
		$aCustomVariableIds[] = 'wbreponame';

		return true;
	}

	/**
	 * Apply the magic word.
	 */
	public static function onParserGetVariableValueSwitch( Parser &$parser, &$cache, &$magicWordId, &$ret ) {
		if ( $magicWordId === 'noexternallanglinks' ) {
			NoLangLinkHandler::handle( $parser, '*' );
		} elseif ( $magicWordId === 'wbreponame' ) {
			// @todo factor out, with tests
			$wikibaseClient = WikibaseClient::getDefaultInstance();
			$settings = $wikibaseClient->getSettings();
			$repoSiteName = $settings->getSetting( 'repoSiteName' );

			$message = new Message( $repoSiteName );

			if ( $message->exists() ) {
				$lang = $parser->getTargetLanguage();
				$ret = $message->inLanguage( $lang )->parse();
			} else {
				$ret = $repoSiteName;
			}
		}

		return true;
	}

	/**
	 * Adds the Entity ID of the corresponding Wikidata item in action=info
	 *
	 * @param IContextSource $context
	 * @param array $pageInfo
	 *
	 * @return bool
	 */
	public static function onInfoAction( IContextSource $context, array &$pageInfo ) {
		$wikibaseClient = WikibaseClient::getDefaultInstance();
		$settings = $wikibaseClient->getSettings();

		$namespaceChecker = WikibaseClient::getDefaultInstance()->getNamespaceChecker();

		if ( !$namespaceChecker->isWikibaseEnabled( $context->getTitle()->getNamespace() ) ) {
			// shorten out
			return true;
		}

		$infoActionHookHandler = new InfoActionHookHandler(
			$namespaceChecker,
			$wikibaseClient->newRepoLinker(),
			$wikibaseClient->getStore()->getSiteLinkLookup(),
			$settings->getSetting( 'siteGlobalID' )
		);

		$pageInfo = $infoActionHookHandler->handle( $context, $pageInfo );

		return true;
	}

	/**
	 * Notify the user that we have automatically updated the repo or that they
	 * need to do that per hand.
	 *
	 * @param Title $title
	 * @param OutputPage $out
	 *
	 * @return bool
	 */
	public static function onArticleDeleteAfterSuccess( Title $title, OutputPage $out ) {
		$wikibaseClient = WikibaseClient::getDefaultInstance();
		$siteLinkLookup = $wikibaseClient->getStore()->getSiteLinkLookup();
		$repoLinker = $wikibaseClient->newRepoLinker();

		$deletePageNotice = new DeletePageNoticeCreator(
			$siteLinkLookup,
			$wikibaseClient->getSettings()->getSetting( 'siteGlobalID' ),
			$repoLinker
		);

		$html = $deletePageNotice->getPageDeleteNoticeHtml( $title );

		$out->addHTML( $html );

		return true;
	}

	/**
	 * @param BaseTemplate $skinTemplate
	 * @param string $name
	 * @param string &$html
	 *
	 * @return boolean
	 */
	public static function onBaseTemplateAfterPortlet( BaseTemplate $skinTemplate, $name, &$html ) {
		$handler = new BaseTemplateAfterPortletHandler();
		$link = $handler->getEditLink( $skinTemplate, $name );

		if ( $link ) {
			$html .= $link;
		}
	}

	public static function onwgQueryPages( &$queryPages ) {
		$queryPages[] = array( 'Wikibase\Client\Specials\SpecialUnconnectedPages', 'UnconnectedPages' );
		$queryPages[] = array( 'Wikibase\Client\Specials\SpecialPagesWithBadges', 'Badges' );
		return true;
	}

}
