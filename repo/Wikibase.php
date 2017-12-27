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
 * Entry point for the Wikibase Repository extension.
 *
 * @see README.md
 * @see https://www.mediawiki.org/wiki/Extension:Wikibase_Repository
 *
 * @license GPL-2.0+
 */
if ( function_exists( 'wfLoadExtension' ) ) {
	// Include the WikibaseLib extension in case that hasn't been done yet, since it's required for Wikibase to work..
	wfLoadExtension( 'WikibaseLib', __DIR__ . '/../lib/extension.json' );
	wfLoadExtension( 'Wikibase View', __DIR__ . '/../view/extension.json' );

	wfLoadExtension( 'WikibaseRepo', __DIR__ . '/extension.json' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$GLOBALS['wgMessagesDirs']['Wikibase'] = __DIR__ . '/i18n';
	$GLOBALS['wgExtensionMessagesFiles']['WikibaseAlias'] = __DIR__ . '/Wikibase.i18n.alias.php';
	$GLOBALS['wgExtensionMessagesFiles']['WikibaseNS'] = __DIR__ . '/Wikibase.i18n.namespaces.php';
	wfWarn(
		'Deprecated PHP entry point used for Wikibase Repo extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the Wikibase Repo extension requires MediaWiki 1.31+' );
}
