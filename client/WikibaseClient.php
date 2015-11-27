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
 * Entry point for the Wikibase Client extension.
 *
 * @see README.md
 * @see https://www.mediawiki.org/wiki/Extension:Wikibase_Client
 * @licence GNU GPL v2+
 */

/**
 * This documentation group collects source code files belonging to Wikibase Client.
 *
 * @defgroup WikibaseClient Wikibase Client
 */

// @codingStandardsIgnoreFile

if ( defined( 'WBC_VERSION' ) ) {
	// Do not initialize more than once.
	return;
}

define( 'WBC_VERSION', '0.5 alpha' );

define( 'WBC_DIR', __DIR__ );

// Include the WikibaseLib extension if that hasn't been done yet, since it's required for WikibaseClient to work.
if ( !defined( 'WBL_VERSION' ) ) {
	include_once __DIR__ . '/../lib/WikibaseLib.php';
}

if ( !defined( 'WBL_VERSION' ) ) {
	throw new Exception( 'WikibaseClient depends on the WikibaseLib extension.' );
}

call_user_func( function() {
	if ( function_exists( 'wfLoadExtension' ) ) {
		if ( file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
			include_once ( __DIR__ . '/../vendor/autoload.php' );
		}
		wfLoadExtension( 'WikibaseClient', __DIR__.'/extension.json' );
		// Keep i18n globals so mergeMessageFileList.php doesn't break
		$GLOBALS['wgMessagesDirs']['wikibaseclient'] = __DIR__ . '/i18n';
		$GLOBALS['wgExtensionMessagesFiles']['Wikibaseclientalias'] = __DIR__ . '/WikibaseClient.i18n.alias.php';
		$GLOBALS['wgExtensionMessagesFiles']['wikibaseclientmagic'] = __DIR__ . '/WikibaseClient.i18n.magic.php';
		ExtensionRegistry::getInstance()->loadFromQueue();
		if ( defined( 'WBC_VERSION' ) ) {
			// Do not initialize more than once.
			return;
		}

		define( 'WBC_VERSION', '0.5 alpha' );

		define( 'WBC_DIR', __DIR__ );

		// Include the WikibaseLib extension if that hasn't been done yet, since it's required for WikibaseClient to work.
		if ( !defined( 'WBL_VERSION' ) ) {
			include_once __DIR__ . '/../lib/WikibaseLib.php';
		}

		if ( !defined( 'WBL_VERSION' ) ) {
			throw new Exception( 'WikibaseClient depends on the WikibaseLib extension.' );
		}
		/* wfWarn(
			'Deprecated PHP entry point used for Wikibase Client extension. Please use wfLoadExtension instead, ' .
			'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
		); */
		return;
	} else {
		die( 'This version of the Wikibase Client extension requires MediaWiki 1.25+' );
	}
} );
