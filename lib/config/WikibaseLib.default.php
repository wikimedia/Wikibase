<?php

/**
 * Lib is not an application and should not have global config state.
 * Therefore you should NOT add new settings here. If both client and repo
 * need them, add them to both.
 *
 * This file assigns the default values to all Wikibase Lib settings.
 *
 * This file is NOT an entry point the WikibaseLib extension. Use WikibaseLib.php.
 * It should furthermore not be included from outside the extension.
 *
 * @license GPL-2.0+
 */
return [
	// whether changes get recorded to wb_changes
	'useChangesTable' => true,

	'siteLinkGroups' => [
		'wikipedia',
	],

	'specialSiteLinkGroups' => [],

	// local by default. Set to something LBFactory understands.
	'changesDatabase' => false,

	'maxSerializedEntitySize' => $GLOBALS['wgMaxArticleSize'],
];
