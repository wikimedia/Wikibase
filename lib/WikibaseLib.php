<?php

/**
 * Welcome to the inside of Wikibase,              <>
 * the software that powers                   /\        /\
 * Wikidata and other                       <{  }>    <{  }>
 * structured data websites.        <>   /\   \/   /\   \/   /\   <>
 *                                     //  \\    //  \\    //  \\
 * It is Free Software.              <{{    }}><{{    }}><{{    }}>
 *                                /\   \\  //    \\  //    \\  //   /\
 *                              <{  }>   ><        \/        ><   <{  }>
 *                                \/   //  \\              //  \\   \/
 *                            <>     <{{    }}>     +--------------------------+
 *                                /\   \\  //       |                          |
 *                              <{  }>   ><        /|  W  I  K  I  B  A  S  E  |
 *                                \/   //  \\    // |                          |
 * We are                            <{{    }}><{{  +--------------------------+
 * looking for people                  \\  //    \\  //    \\  //
 * like you to join us in           <>   \/   /\   \/   /\   \/   <>
 * developing it further. Find              <{  }>    <{  }>
 * out more at http://wikiba.se               \/        \/
 * and join the open data revolution.              <>
 */

/**
 * Entry point for the WikibaseLib extension.
 *
 * @see README.md
 * @see https://www.mediawiki.org/wiki/Extension:WikibaseLib
 *
 * @license GPL-2.0-or-later
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not an entry point.' );
}

// Needs to be 1.26c because version_compare() works in confusing ways.
if ( version_compare( $GLOBALS['wgVersion'], '1.26c', '<' ) ) {
	die( "<b>Error:</b> Wikibase requires MediaWiki 1.26 or above.\n" );
}

if ( defined( 'WBL_VERSION' ) ) {
	// Do not initialize more than once.
	return 1;
}

define( 'WBL_VERSION', '0.5 alpha' );

// Load autoload info as long as extension classes are not PSR-4-autoloaded
require_once __DIR__  . '/autoload.php';
require_once __DIR__  . '/../data-access/autoload.php';
// Nasty hack: some lib's tests rely on ItemContent class defined in Repo! Load it in client-only mode to have tests pass
if ( !defined( 'WB_VERSION' ) && defined( 'MW_PHPUNIT_TEST' ) ) {
	global $wgAutoloadClasses;
	$wgAutoloadClasses['Wikibase\\ItemContent'] = __DIR__ . '/../repo/includes/Content/ItemContent.php';
	$wgAutoloadClasses['Wikibase\\EntityContent'] = __DIR__ . '/../repo/includes/Content/EntityContent.php';
	$wgAutoloadClasses['Wikibase\\Repo\\Content\\EntityContentDiff'] = __DIR__ . '/../repo/includes/Content/EntityContentDiff.php';
}

call_user_func( function() {
	global $wgExtensionCredits, $wgHooks, $wgResourceModules, $wgMessagesDirs;

	$wgExtensionCredits['wikibase'][] = [
		'path' => __DIR__ . '/../README.md',
		'name' => 'WikibaseLib',
		'author' => [
			'The Wikidata team',
		],
		'url' => 'https://www.mediawiki.org/wiki/Extension:WikibaseLib',
		'descriptionmsg' => 'wikibase-lib-desc',
		'license-name' => 'GPL-2.0-or-later'
	];

	// Maximum summary length in characters (not bytes)
	// Should be less than CommentStore::COMMENT_CHARACTER_LIMIT in core.
	define( 'SUMMARY_MAX_LENGTH', 250 );

	// i18n
	$wgMessagesDirs['WikibaseLib'] = __DIR__ . '/i18n';

	// Hooks
	$wgHooks['UnitTestsList'][] = 'Wikibase\LibHooks::registerPhpUnitTests';
	$wgHooks['ResourceLoaderTestModules'][] = 'Wikibase\LibHooks::registerQUnitTests';
	$wgHooks['ResourceLoaderRegisterModules'][] = 'Wikibase\LibHooks::onResourceLoaderRegisterModules';

	/**
	 * Called when generating the extensions credits, use this to change the tables headers.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ExtensionTypes
	 *
	 * @param array &$extensionTypes
	 *
	 * @return boolean
	 */
	$wgHooks['ExtensionTypes'][] = function( array &$extensionTypes ) {
		// @codeCoverageIgnoreStart
		$extensionTypes['wikibase'] = wfMessage( 'version-wikibase' )->text();

		return true;
		// @codeCoverageIgnoreEnd
	};

	// Resource Loader Modules:
	$wgResourceModules = array_merge(
		$wgResourceModules,
		include __DIR__ . '/resources/Resources.php'
	);
} );
