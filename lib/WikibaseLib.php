<?php

/**
 * Initialization file for the WikibaseLib extension.
 *
 * Documentation:	 		https://www.mediawiki.org/wiki/Extension:WikibaseLib
 * Support					https://www.mediawiki.org/wiki/Extension_talk:WikibaseLib
 * Source code:				https://gerrit.wikimedia.org/r/gitweb?p=mediawiki/extensions/WikibaseLib.git
 *
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */

/**
 * This documentation group collects source code files belonging to WikibaseLib.
 *
 * @defgroup WikibaseLib WikibaseLib
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not an entry point.' );
}

if ( version_compare( $wgVersion, '1.20c', '<' ) ) { // Needs to be 1.20c because version_compare() works in confusing ways.
	die( '<b>Error:</b> WikibaseLib requires MediaWiki 1.20 or above.' );
}

// Include the Diff extension if that hasn't been done yet, since it's required for WikibaseLib to work.
if ( !defined( 'Diff_VERSION' ) ) {
	@include_once( dirname( __FILE__ ) . '/../../Diff/Diff.php' );
}

if ( !defined( 'Diff_VERSION' ) ) {
	die( '<b>Error:</b> WikibaseLib depends on the <a href="https://www.mediawiki.org/wiki/Extension:Diff">Diff</a> extension.' );
}

define( 'WBL_VERSION', '0.1 alpha' );

$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'WikibaseLib',
	'version' => WBL_VERSION,
	'author' => array(
		'The Wikidata team', // TODO: link?
	),
	'url' => 'https://www.mediawiki.org/wiki/Extension:WikibaseLib',
	'descriptionmsg' => 'wikibaselib-desc'
);

$dir = dirname( __FILE__ ) . '/';

// constants
define( 'CONTENT_MODEL_WIKIBASE_ITEM', "wikibase-item" );
define( 'CONTENT_MODEL_WIKIBASE_PROPERTY', "wikibase-property" );
define( 'CONTENT_MODEL_WIKIBASE_QUERY', "wikibase-query" );

define( 'SITE_TYPE_MEDIAWIKI', 0 );
define( 'SITE_TYPE_UNKNOWN', 1 );

define( 'SITE_GROUP_NONE', -1 );
define( 'SITE_GROUP_WIKIPEDIA', 0 );
define( 'SITE_GROUP_WIKTIONARY', 1 );
define( 'SITE_GROUP_WIKIBOOKS', 2 );
define( 'SITE_GROUP_WIKIQUOTE', 3 );
define( 'SITE_GROUP_WIKISOURCE', 4 );
define( 'SITE_GROUP_WIKIVERSITY', 5 );
define( 'SITE_GROUP_WIKINEWS', 6 );

define( 'SUMMARY_MAX_LENGTH', 250 );

$wgSiteTypes = array();

$wgSiteTypes[SITE_TYPE_MEDIAWIKI] = 'Wikibase\MediaWikiSite';

// i18n
$wgExtensionMessagesFiles['WikibaseLib'] 			= $dir . 'WikibaseLib.i18n.php';



// Autoloading
$wgAutoloadClasses['Wikibase\LibHooks'] 			= $dir . 'WikibaseLib.hooks.php';

// includes
$wgAutoloadClasses['Wikibase\ChangeHandler'] 		= $dir . 'includes/ChangeHandler.php';
$wgAutoloadClasses['Wikibase\ChangeNotifier'] 		= $dir . 'includes/ChangeNotifier.php';
$wgAutoloadClasses['Wikibase\Changes'] 				= $dir . 'includes/Changes.php';
$wgAutoloadClasses['Wikibase\DiffView'] 			= $dir . 'includes/DiffView.php';
$wgAutoloadClasses['Wikibase\MediaWikiSite'] 		= $dir . 'includes/MediaWikiSite.php';
$wgAutoloadClasses['Wikibase\Settings'] 			= $dir . 'includes/Settings.php';
$wgAutoloadClasses['Wikibase\Site'] 				= $dir . 'includes/Site.php';
$wgAutoloadClasses['Wikibase\SiteConfig'] 			= $dir . 'includes/SiteConfig.php';
$wgAutoloadClasses['Wikibase\SiteConfigObject'] 	= $dir . 'includes/SiteConfigObject.php';
$wgAutoloadClasses['Wikibase\SiteLink'] 			= $dir . 'includes/SiteLink.php';
$wgAutoloadClasses['Wikibase\SiteList'] 			= $dir . 'includes/SiteList.php';
$wgAutoloadClasses['Wikibase\SiteRow'] 				= $dir . 'includes/SiteRow.php';
$wgAutoloadClasses['Wikibase\Sites'] 				= $dir . 'includes/Sites.php';
$wgAutoloadClasses['Wikibase\SitesTable'] 			= $dir . 'includes/SitesTable.php';
$wgAutoloadClasses['Wikibase\Utils'] 				= $dir . 'includes/Utils.php';

// includes/changes
$wgAutoloadClasses['Wikibase\Change'] 				= $dir . 'includes/changes/Change.php';
$wgAutoloadClasses['Wikibase\DiffChange'] 			= $dir . 'includes/changes/DiffChange.php';
$wgAutoloadClasses['Wikibase\ItemChange'] 			= $dir . 'includes/changes/ItemChange.php';
$wgAutoloadClasses['Wikibase\ItemCreation'] 		= $dir . 'includes/changes/ItemCreation.php';
$wgAutoloadClasses['Wikibase\ItemDeletion'] 		= $dir . 'includes/changes/ItemDeletion.php';

// includes/entity
$wgAutoloadClasses['Wikibase\Entity'] 				= $dir . 'includes/entity/Entity.php';
$wgAutoloadClasses['Wikibase\EntityDiff'] 			= $dir . 'includes/entity/EntityDiff.php';
$wgAutoloadClasses['Wikibase\EntityDiffObject'] 	= $dir . 'includes/entity/EntityDiffObject.php';
$wgAutoloadClasses['Wikibase\EntityDiffView'] 		= $dir . 'includes/entity/EntityDiffView.php';
$wgAutoloadClasses['Wikibase\EntityObject'] 		= $dir . 'includes/entity/EntityObject.php';

// includes/item
$wgAutoloadClasses['Wikibase\Item'] 				= $dir . 'includes/item/Item.php';
$wgAutoloadClasses['Wikibase\ItemDiff'] 			= $dir . 'includes/item/ItemDiff.php';
$wgAutoloadClasses['Wikibase\ItemDiffView'] 		= $dir . 'includes/item/ItemDiffView.php';
$wgAutoloadClasses['Wikibase\ItemObject'] 			= $dir . 'includes/item/ItemObject.php';

// includes/property
$wgAutoloadClasses['Wikibase\Property'] 			= $dir . 'includes/property/Property.php';
$wgAutoloadClasses['Wikibase\PropertyObject'] 		= $dir . 'includes/property/PropertyObject.php';

// includes/query
$wgAutoloadClasses['Wikibase\Query'] 				= $dir . 'includes/query/Query.php';
$wgAutoloadClasses['Wikibase\QueryObject'] 			= $dir . 'includes/query/QueryObject.php';

// tests
$wgAutoloadClasses['Wikibase\Test\TestItems'] 				= $dir . 'tests/phpunit/TestItems.php';
$wgAutoloadClasses['Wikibase\Test\EntityObjectTest'] 		= $dir . 'tests/phpunit/EntityObjectTest.php';

// tests/changes
$wgAutoloadClasses['Wikibase\tests\AliasChangeTest'] 		= $dir . 'tests/phpunit/changes/AliasChangeTest.php';
$wgAutoloadClasses['Wikibase\tests\SitelinkChangeTest'] 	= $dir . 'tests/phpunit/changes/SitelinkChangeTest.php';

foreach ( array(
			  'Settings',
			  'SettingsBase'
		  ) as $compatClass ) {
	if ( !array_key_exists( $compatClass, $wgAutoloadLocalClasses ) ) {
		$wgAutoloadClasses[$compatClass] = $dir . 'compat/' . $compatClass . '.php';
	}
}



// Hooks
$wgHooks['WikibaseDefaultSettings'][]				= 'Wikibase\LibHooks::onWikibaseDefaultSettings';
$wgHooks['LoadExtensionSchemaUpdates'][] 			= 'Wikibase\LibHooks::onSchemaUpdate';
$wgHooks['UnitTestsList'][]					= 'Wikibase\LibHooks::registerUnitTests';


$wgSharedTables[] = 'wb_changes';



$egWBDefaultsFunction = null;

$egWBSettings = array();
