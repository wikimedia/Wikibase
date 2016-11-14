<?php
/**
 * @license GPL-2.0+
 * @author H. Snater < mediawiki@snater.com >
 *
 * @codeCoverageIgnoreStart
 */
return call_user_func( function() {
	preg_match( '+' . preg_quote( DIRECTORY_SEPARATOR ) . '(?:vendor|extensions)'
		. preg_quote( DIRECTORY_SEPARATOR ) . '.*+', __DIR__, $remoteExtPath );

	$moduleTemplate = [
		'localBasePath' => __DIR__,
		'remoteExtPath' => '..' . $remoteExtPath[0],
	];

	$modules = [

		'jquery.wikibase.addtoolbar' => $moduleTemplate + [
			'scripts' => [
				'jquery.wikibase.addtoolbar.js',
			],
			'dependencies' => [
				'jquery.wikibase.singlebuttontoolbar',
			],
			'messages' => [
				'wikibase-add',
			],
		],

		'jquery.wikibase.edittoolbar' => $moduleTemplate + [
			'scripts' => [
				'jquery.wikibase.edittoolbar.js',
			],
			'styles' => [
				'themes/default/jquery.wikibase.edittoolbar.css',
			],
			'dependencies' => [
				'jquery.wikibase.toolbar',
				'jquery.wikibase.toolbarbutton',
				'jquery.wikibase.wbtooltip',
				'wikibase.api.RepoApiError',
			],
			'messages' => [
				'wikibase-cancel',
				'wikibase-edit',
				'wikibase-remove',
				'wikibase-remove-inprogress',
				'wikibase-save',
				'wikibase-save-inprogress',
			],
		],

		'jquery.wikibase.removetoolbar' => $moduleTemplate + [
			'scripts' => [
				'jquery.wikibase.removetoolbar.js',
			],
			'dependencies' => [
				'jquery.wikibase.singlebuttontoolbar',
			],
			'messages' => [
				'wikibase-remove',
			],
		],

		'jquery.wikibase.singlebuttontoolbar' => $moduleTemplate + [
			'scripts' => [
				'jquery.wikibase.singlebuttontoolbar.js',
			],
			'dependencies' => [
				'jquery.wikibase.toolbar',
				'jquery.wikibase.toolbarbutton',
			],
		],

		'jquery.wikibase.toolbar' => $moduleTemplate + [
			'scripts' => [
				'jquery.wikibase.toolbar.js',
			],
			'dependencies' => [
				'jquery.wikibase.toolbaritem',
				'jquery.wikibase.toolbar.styles',
			],
		],

		'jquery.wikibase.toolbar.styles' => $moduleTemplate + [
			'position' => 'top',
			'styles' => [
				'themes/default/jquery.wikibase.toolbar.css',
			],
		],

		'jquery.wikibase.toolbarbutton' => $moduleTemplate + [
			'scripts' => [
				'jquery.wikibase.toolbarbutton.js',
			],
			'dependencies' => [
				'jquery.wikibase.toolbaritem',
				'jquery.wikibase.toolbarbutton.styles',
			],
		],

		'jquery.wikibase.toolbarbutton.styles' => $moduleTemplate + [
			'position' => 'top',
			'styles' => [
				'themes/default/jquery.wikibase.toolbarbutton.css',
			],
		],

		'jquery.wikibase.toolbaritem' => $moduleTemplate + [
			'scripts' => [
				'jquery.wikibase.toolbaritem.js',
			],
			'styles' => [
				'themes/default/jquery.wikibase.toolbaritem.css',
			],
			'dependencies' => [
				'jquery.ui.TemplatedWidget',
			],
		],

	];

	return $modules;
} );
