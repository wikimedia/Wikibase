<?php

/**
 * Class registration file for the WikibaseClient component.
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
return call_user_func( function() {

	$classes = array(
		// Autoloading
		'Wikibase\ClientHooks' => 'WikibaseClient.hooks.php',

		'Wikibase\EntityIdPropertyUpdater' => 'includes/EntityIdPropertyUpdater.php',
		'Wikibase\InterwikiSorter' => 'includes/InterwikiSorter.php',
		'Wikibase\LangLinkHandler' => 'includes/LangLinkHandler.php',
		'Wikibase\ChangeHandler' => 'includes/ChangeHandler.php',
		'Wikibase\NamespaceChecker' => 'includes/NamespaceChecker.php',
		'Wikibase\ReferencedPagesFinder' => 'includes/ReferencedPagesFinder.php',
		'Wikibase\RepoItemLinkGenerator' => 'includes/RepoItemLinkGenerator.php',
		'Wikibase\RepoLinker' => 'includes/RepoLinker.php',
		'Wikibase\Client\WikibaseClient' => 'includes/WikibaseClient.php',
		'Wikibase\PageUpdater' => 'includes/PageUpdater.php',
		'Wikibase\SiteLinkCommentCreator' => 'includes/SiteLinkCommentCreator.php',
		'Wikibase\WikiPageUpdater' => 'includes/WikiPageUpdater.php',
		'Wikibase\UpdateRepo' => 'includes/UpdateRepo.php',
		'Wikibase\UpdateRepoOnMove' => 'includes/UpdateRepoOnMove.php',

		// includes/api
		'Wikibase\ApiClientInfo' => 'includes/api/ApiClientInfo.php',

		// includes/hooks
		'Wikibase\Client\Hooks\InfoActionHookHandler' => 'includes/hooks/InfoActionHookHandler.php',
		'Wikibase\Client\MovePageNotice' => 'includes/hooks/MovePageNotice.php',

		// includes/modules
		'Wikibase\SiteModule'  => 'includes/modules/SiteModule.php',

		// include/parserhooks
		'Wikibase\NoLangLinkHandler' => 'includes/parserhooks/NoLangLinkHandler.php',
		'Wikibase\ParserErrorMessageFormatter' => 'includes/parserhooks/ParserErrorMessageFormatter.php',
		'Wikibase\PropertyParserFunction' => 'includes/parserhooks/PropertyParserFunction.php',
		'Wikibase\PropertyParserFunctionRenderer' => 'includes/parserhooks/PropertyParserFunctionRenderer.php',

		// includes/recentchanges
		'Wikibase\ChangeLineFormatter' => 'includes/recentchanges/ChangeLineFormatter.php',
		'Wikibase\ExternalChange' => 'includes/recentchanges/ExternalChange.php',
		'Wikibase\ExternalChangeFactory' => 'includes/recentchanges/ExternalChangeFactory.php',
		'Wikibase\ExternalRecentChange' => 'includes/recentchanges/ExternalRecentChange.php',
		'Wikibase\RecentChangesFilterOptions' => 'includes/recentchanges/RecentChangesFilterOptions.php',
		'Wikibase\RevisionData' => 'includes/recentchanges/RevisionData.php',

		// includes/specials
		'Wikibase\Client\Specials\SpecialUnconnectedPages' => 'includes/specials/SpecialUnconnectedPages.php',

		// includes/store
		'Wikibase\ClientStore' => 'includes/store/ClientStore.php',

		// includes/store/sql
		'Wikibase\DirectSqlStore' => 'includes/store/sql/DirectSqlStore.php',

		// includes/scribunto
		'Scribunto_LuaWikibaseLibrary' => 'includes/scribunto/Scribunto_LuaWikibaseLibrary.php',
		'Scribunto_LuaWikibaseEntityLibrary' => 'includes/scribunto/Scribunto_LuaWikibaseEntityLibrary.php',
		'Wikibase\Client\Scribunto\WikibaseLuaBindings' => 'includes/scribunto/WikibaseLuaBindings.php',
		'Wikibase\Client\Scribunto\WikibaseLuaEntityBindings' => 'includes/scribunto/WikibaseLuaEntityBindings.php',

		// test
		'Wikibase\Test\MockPageUpdater' => 'tests/phpunit/MockPageUpdater.php',
		'Wikibase\Client\Scribunto\Test\WikibaseLuaIntegrationTestHelper' => 'tests/phpunit/includes/scribunto/WikibaseLuaIntegrationTestHelper.php',
		'Wikibase\Client\Scribunto\Test\Scribunto_LuaWikibaseLibraryTestCase' => 'tests/phpunit/includes/scribunto/Scribunto_LuaWikibaseLibraryTestCase.php',
		'Wikibase\Client\Scribunto\Test\ClientStoreMock' => 'tests/phpunit/includes/scribunto/WikibaseLuaIntegrationTestHelper.php',

	);

	return $classes;

} );
