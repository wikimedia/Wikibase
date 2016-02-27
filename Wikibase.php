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
 * As of MediaWiki 1.27 this is now the entry point.
 *
 * @see README.md
 * @see http://wikiba.se
 * @licence GNU GPL v2+
 */

if (
	!array_key_exists( 'wgEnableWikibaseRepo', $GLOBALS ) || $GLOBALS['wgEnableWikibaseRepo'] ||
	isset( $wgEnableWikibaseRepo ) && $wgEnableWikibaseRepo == true ||
	isset( $GLOBALS['wgEnableWikibaseRepo'] ) && $GLOBALS['wgEnableWikibaseRepo'] == true
) {
	if ( (
		!defined( 'WIKIBASE_DATAMODEL_VERSION' ) || !defined( 'Diff_VERSION' ) ||
		!defined( 'DATAVALUES_VERSION' ) ) && is_readable( __DIR__ . '/vendor/autoload.php' )
	) {
		require_once __DIR__ . '/vendor/autoload.php';
	}

	require_once __DIR__ . '/lib/WikibaseLib.php';

	if ( !defined( 'WIKIBASE_VIEW_VERSION' ) ) {
		include_once __DIR__ . '/view/WikibaseView.php';
	}

	if ( !defined( 'WIKIBASE_VIEW_VERSION' ) ) {
		throw new Exception( 'Wikibase depends on WikibaseView.' );
	}

	if ( !defined( 'PURTLE_VERSION' ) ) {
		include_once __DIR__ . '/purtle/Purtle.php';
	}

	if ( !defined( 'PURTLE_VERSION' ) ) {
		throw new Exception( 'Wikibase depends on Purtle.' );
	}

	require_once __DIR__ . '/repo/Wikibase.php';
}

if (
	!array_key_exists( 'wgEnableWikibaseClient', $GLOBALS ) || $GLOBALS['wgEnableWikibaseClient'] ||
	isset( $wgEnableWikibaseClient ) && $wgEnableWikibaseClient == true ||
	isset( $GLOBALS['wgEnableWikibaseClient'] ) && $GLOBALS['wgEnableWikibaseClient'] == true
) {
	if ( (
		!defined( 'WIKIBASE_DATAMODEL_VERSION' ) || !defined( 'Diff_VERSION' ) ||
		!defined( 'DATAVALUES_VERSION' ) ) && is_readable( __DIR__ . '/vendor/autoload.php' )
	) {
		require_once __DIR__ . '/vendor/autoload.php';
	}

	require_once __DIR__ . '/lib/WikibaseLib.php';

	require_once __DIR__ . '/client/WikibaseClient.php';
}

if (
	!array_key_exists( 'wgEnableWikibaseBoth', $GLOBALS ) || $GLOBALS['wgEnableWikibaseBoth'] ||
	isset( $wgEnableWikibaseBoth ) && $wgEnableWikibaseBoth == true ||
	isset( $GLOBALS['wgEnableWikibaseBoth'] ) && $GLOBALS['wgEnableWikibaseBoth'] == true
) {
	if ( (
		!defined( 'WIKIBASE_DATAMODEL_VERSION' ) || !defined( 'Diff_VERSION' ) ||
		!defined( 'DATAVALUES_VERSION' ) ) && is_readable( __DIR__ . '/vendor/autoload.php' )
	) {
		require_once __DIR__ . '/vendor/autoload.php';
	}

	require_once __DIR__ . '/lib/WikibaseLib.php';

	if ( !defined( 'WIKIBASE_VIEW_VERSION' ) ) {
		include_once __DIR__ . '/view/WikibaseView.php';
	}

	if ( !defined( 'WIKIBASE_VIEW_VERSION' ) ) {
		throw new Exception( 'Wikibase depends on WikibaseView.' );
	}

	if ( !defined( 'PURTLE_VERSION' ) ) {
		include_once __DIR__ . '/purtle/Purtle.php';
	}

	if ( !defined( 'PURTLE_VERSION' ) ) {
		throw new Exception( 'Wikibase depends on Purtle.' );
	}

	require_once __DIR__ . '/repo/Wikibase.php';
	require_once __DIR__ . '/client/WikibaseClient.php';
}
