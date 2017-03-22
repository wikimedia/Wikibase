<?php

use Wikibase\Client\WikibaseClient;
use Wikibase\SettingsArray;

/**
 * This file assigns the default values to all Wikibase Client settings.
 *
 * This file is NOT an entry point the Wikibase Client extension. Use WikibaseClient.php.
 * It should furthermore not be included from outside the extension.
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */

return call_user_func( function() {
	global $wgLanguageCode;

	$defaults = [
		'namespaces' => [], // by default, include all namespaces; deprecated as of 0.4
		'excludeNamespaces' => [],
		// @todo would be great to just get this from the sites stuff
		// but we will need to make sure the caching works good enough
		'siteLocalID' => $wgLanguageCode,
		'languageLinkSiteGroup' => null,
		'injectRecentChanges' => true,
		'showExternalRecentChanges' => true,
		'sendEchoNotification' => false,
		'repoIcon' => false,
		'allowDataTransclusion' => true,
		'propagateChangesToRepo' => true,
		'otherProjectsLinksByDefault' => false,
		'otherProjectsLinksBeta' => false,
		'propertyOrderUrl' => null,
		// List of additional CSS class names for site links that have badges,
		// e.g. array( 'Q101' => 'badge-goodarticle' )
		'badgeClassNames' => [],
		// Allow accessing data from other items in the parser functions and via Lua
		'allowArbitraryDataAccess' => true,
		// Maximum number of full entities that can be accessed on a page. This does
		// not include convenience functions like mw.wikibase.label that use TermLookup
		// instead of loading a full entity.
		'entityAccessLimit' => 250,
		// Allow accessing data in the user's language rather than the content language
		// in the parser functions and via Lua.
		// Allows users to split the ParserCache by user language.
		'allowDataAccessInUserLanguage' => false,

		/**
		 * Prefix to use for cache keys that should be shared among a Wikibase Repo instance and all
		 * its clients. This is for things like caching entity blobs in memcached.
		 *
		 * The default here assumes Wikibase Repo + Client installed together on the same wiki. For
		 * a multiwiki / wikifarm setup, to configure shared caches between clients and repo, this
		 * needs to be set to the same value in both client and repo wiki settings.
		 *
		 * For Wikidata production, we set it to 'wikibase-shared/wikidata_1_25wmf24-wikidatawiki',
		 * which is 'wikibase_shared/' + deployment branch name + '-' + repo database name, and have
		 * it set in both $wgWBClientSettings and $wgWBRepoSettings.
		 */
		'sharedCacheKeyPrefix' => 'wikibase_shared/' . rawurlencode( WBL_VERSION ) . '-' . $GLOBALS['wgDBname'],

		/**
		 * The duration of the object cache, in seconds.
		 *
		 * As with sharedCacheKeyPrefix, this is both client and repo setting. On a multiwiki setup,
		 * this should be set to the same value in both the repo and clients. Also note that the
		 * setting value in $wgWBClientSettings overrides the one here.
		 */
		'sharedCacheDuration' => 60 * 60,

		/**
		 * List of data types (by data type id) not enabled on the wiki.
		 * This setting is intended to aid with deployment of new data types
		 * or on new Wikibase installs without items and properties yet.
		 *
		 * This setting should be consistent with the corresponding setting on the repo.
		 *
		 * WARNING: Disabling a data type after it is in use is dangerous
		 * and might break items.
		 */
		'disabledDataTypes' => [],

		// The type of object cache to use. Use CACHE_XXX constants.
		// This is both a repo and client setting, and should be set to the same value in
		// repo and clients for multiwiki setups.
		'sharedCacheType' => $GLOBALS['wgMainCacheType'],

		'repositoryServiceWiringFiles' => [ __DIR__ . '/../includes/Store/RepositoryServiceWiring.php' ],
		'dispatchingServiceWiringFiles' => [ __DIR__ . '/../includes/DispatchingServiceWiring.php' ],
		'foreignRepositories' => [],
	];

	// Some defaults depend on information not available at this time.
	// Especially, if the repository may be active on the local wiki, and
	// we need to adjust some defaults accordingly.
	// We use Closures to calculate such settings on the fly, the first time they
	// are used. See SettingsArray::setSetting() for details.

	//NOTE: when this is executed, WB_VERSION may not yet be defined, because
	//      the repo extension has not yet been initialized. We need to defer the
	//      check and do it inside the closures.
	//      We use the pseudo-setting thisWikiIsTheRepo to store this information.
	//      thisWikiIsTheRepo should really never be overwritten, except for testing.

	$defaults['thisWikiIsTheRepo'] = function ( SettingsArray $settings ) {
		// determine whether the repo extension is present
		return defined( 'WB_VERSION' );
	};

	$defaults['repoSiteName'] = function ( SettingsArray $settings ) {
		// This uses $wgSitename if this wiki is the repo.  Otherwise, set this to
		// either an i18n message key and the message will be used, if it exists.
		// If repo site name does not need translation, then set this as a string.
		return $settings->getSetting( 'thisWikiIsTheRepo' ) ? $GLOBALS['wgSitename'] : 'Wikidata';
	};

	$defaults['repoUrl'] = function ( SettingsArray $settings ) {
		// use $wgServer if this wiki is the repo, otherwise default to wikidata.org
		return $settings->getSetting( 'thisWikiIsTheRepo' ) ? $GLOBALS['wgServer'] : '//www.wikidata.org';
	};

	$defaults['repoConceptBaseUri'] = function ( SettingsArray $settings ) {
		return $settings->getSetting( 'repoUrl' ) . '/entity/';
	};

	$defaults['repoArticlePath'] = function ( SettingsArray $settings ) {
		// use $wgArticlePath if this wiki is the repo, otherwise default to /wiki/$1
		return $settings->getSetting( 'thisWikiIsTheRepo' ) ? $GLOBALS['wgArticlePath'] : '/wiki/$1';
	};

	$defaults['repoScriptPath'] = function ( SettingsArray $settings ) {
		// use $wgScriptPath if this wiki is the repo, otherwise default to /w
		return $settings->getSetting( 'thisWikiIsTheRepo' ) ? $GLOBALS['wgScriptPath'] : '/w';
	};

	$defaults['repoDatabase'] = function ( SettingsArray $settings ) {
		// Use false (meaning the local wiki's database) if this wiki is the repo,
		// otherwise default to null (meaning we can't access the repo's DB directly).
		return $settings->getSetting( 'thisWikiIsTheRepo' ) ? false : null;
	};

	$defaults['entityNamespaces'] = function ( SettingsArray $settings ) {
		if ( $settings->getSetting( 'thisWikiIsTheRepo' ) ) {
			// if this is the repo wiki, use the repo setting
			// FIXME: this used to be using WikibaseClient::getRepoSettings() which cannot be used
			// if Repo does depend on Client (leads to an infinite loop in Repo's constructor).
			// This is a temporary workaround that should be rather replaced with something better
			// than accessing a static function.
			return \Wikibase\Repo\WikibaseRepo::buildEntityNamespaceConfigurations();
		} else {
			// XXX: Default to having Items in the main namespace, and properties in NS 120.
			// That is the live setup at wikidata.org, it is NOT consistent with the example settings!
			return [
				'item' => 0,
				'property' => 120
			];
		}
	};

	$defaults['repoNamespaces'] = function ( SettingsArray $settings ) {
		if ( $settings->getSetting( 'thisWikiIsTheRepo' ) ) {
			// if this is the repo wiki, look up the namespace names based on the entityNamespaces setting
			$namespaceNames = array_map(
				'MWNamespace::getCanonicalName',
				$settings->getSetting( 'entityNamespaces' )
			);
			return $namespaceNames;
		} else {
			// XXX: Default to having Items in the main namespace, and properties in the 'Property' namespace.
			// That is the live setup at wikidata.org, it is NOT consistent with the example settings!
			return [
				'item' => '',
				'property' => 'Property'
			];
		}
	};

	$defaults['changesDatabase'] = function ( SettingsArray $settings ) {
		// Per default, the database for tracking changes is the repo's database.
		// Note that the value for the repoDatabase setting may be calculated dynamically,
		// see above.
		return $settings->getSetting( 'repoDatabase' );
	};

	$defaults['siteGlobalID'] = function ( SettingsArray $settings ) {
		// The database name is a sane default for the site ID.
		// On Wikimedia sites, this is always correct.
		return $GLOBALS['wgDBname'];
	};

	$defaults['repoSiteId'] = function( SettingsArray $settings ) {
		// If repoDatabase is set, then default is same as repoDatabase
		// otherwise, defaults to siteGlobalID
		return ( $settings->getSetting( 'repoDatabase' ) === false )
			? $settings->getSetting( 'siteGlobalID' )
			: $settings->getSetting( 'repoDatabase' );
	};

	$defaults['siteGroup'] = function ( SettingsArray $settings ) {
		// by default lookup from SiteLookup, can override with setting for performance reasons
		return null;
	};

	$defaults['otherProjectsLinks'] = function ( SettingsArray $settings ) {
		$otherProjectsSitesProvider = WikibaseClient::getDefaultInstance()->getOtherProjectsSitesProvider();
		return $otherProjectsSitesProvider->getOtherProjectsSiteIds( $settings->getSetting( 'siteLinkGroups' ) );
	};

	// URL of geo shape storage frontend. Used primarily to build links to the geo shapes.
	// URL will be concatenated with the page title, so should end up with '/' or 'title='
	// Special characters (e.g. space, percent, etc.) in URL should NOT be encoded
	$defaults['geoShapeStorageFrontendUrl'] = 'https://commons.wikimedia.org/wiki/';

	return $defaults;
} );
