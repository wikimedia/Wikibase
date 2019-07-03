<?php

use Wikibase\Client\DataBridge\DataBridgeConfigValueProvider;
use Wikibase\Client\Modules\SiteModule;
use Wikibase\Client\WikibaseClient;
use Wikibase\Lib\Modules\MediaWikiConfigModule;

return call_user_func( function() {
	$moduleTemplate = [
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'Wikibase/client/resources',
	];

	return [
		'wikibase.client.getMwApiForRepo' => $moduleTemplate + [
			'scripts' => [
				'wikibase.client.getMwApiForRepo.js'
			],
			'dependencies' => [
				'mw.config.values.wbRepo',
				'wikibase.api.getLocationAgnosticMwApi',
			]
		],

		'wikibase.client.init' => $moduleTemplate + [
			'skinStyles' => [
				'modern' => 'wikibase.client.css',
				'monobook' => 'wikibase.client.css',
				'vector' => [
					'wikibase.client.css',
					'wikibase.client.vector.css'
				]
			],
		],

		'wikibase.client.data-bridge.init' => [
			'factory' => function () {
				$clientSettings = WikibaseClient::getDefaultInstance()->getSettings();
				return new ResourceLoaderFileModule(
					[
						'scripts' => [
							'data-bridge.init.js'
						],
						'targets' => $clientSettings->getSetting( 'dataBridgeEnabled' ) ?
							[ 'desktop', 'mobile' ] :
							[],
						'dependencies' => [
							'mw.config.values.wbDataBridgeConfig',
						],
						'remoteExtPath' => 'Wikibase/client/data-bridge/dist'
					],
					__DIR__ . '/../data-bridge/dist'
				);
			},
		],

		'mw.config.values.wbDataBridgeConfig' => [
			'factory' => function () {
				$clientSettings = WikibaseClient::getDefaultInstance()->getSettings();
				return new MediaWikiConfigModule(
					[
						'getconfigvalueprovider' => function() use ( $clientSettings ) {
							return new DataBridgeConfigValueProvider( $clientSettings );
						},
						'targets' => $clientSettings->getSetting( 'dataBridgeEnabled' ) ?
							[ 'desktop', 'mobile' ] :
							[],
					]
				);
			}
		],

		'wikibase.client.data-bridge.app' => [
			'factory' => function () {
				$clientSettings = WikibaseClient::getDefaultInstance()->getSettings();
				return new ResourceLoaderFileModule(
					[
						'scripts' => [
							'data-bridge.app.js'
						],
						'targets' => $clientSettings->getSetting( 'dataBridgeEnabled' ) ?
							[ 'desktop', 'mobile' ] :
							[],
						'remoteExtPath' => 'Wikibase/client/data-bridge/dist'
					],
					__DIR__ . '/../data-bridge/dist'
				);
			},
		],

		'wikibase.client.currentSite' => $moduleTemplate + [
			'class' => SiteModule::class
		],

		'wikibase.client.page-move' => $moduleTemplate + [
			'styles' => 'wikibase.client.page-move.css'
		],

		'wikibase.client.changeslist.css' => $moduleTemplate + [
			'styles' => 'wikibase.client.changeslist.css'
		],

		'wikibase.client.linkitem.init' => $moduleTemplate + [
			'scripts' => [
				'wikibase.client.linkitem.init.js'
			],
			'messages' => [
				'unknown-error'
			],
			'dependencies' => [
				'jquery.spinner',
				'mediawiki.notify'
			],
		],

		'wikibase.client.PageConnector' => $moduleTemplate + [
			'scripts' => [
				'wikibase.client.PageConnector.js'
			],
			'dependencies' => [
				'wikibase.sites'
			],
		],

		'jquery.wikibase.linkitem' => $moduleTemplate + [
			'scripts' => [
				'jquery.wikibase/jquery.wikibase.linkitem.js'
			],
			'styles' => [
				'jquery.wikibase/jquery.wikibase.linkitem.css'
			],
			'dependencies' => [
				'jquery.spinner',
				'jquery.ui.dialog',
				'jquery.ui.suggester',
				'jquery.wikibase.siteselector',
				'jquery.wikibase.wbtooltip',
				'mediawiki.api',
				'mediawiki.util',
				'mediawiki.jqueryMsg',
				'jquery.event.special.eachchange',
				'wikibase.client.currentSite',
				'wikibase.sites',
				'wikibase.api.RepoApi',
				'wikibase.api.RepoApiError',
				'wikibase.client.PageConnector'
			],
			'messages' => [
				'wikibase-error-unexpected',
				'wikibase-linkitem-alreadylinked',
				'wikibase-linkitem-title',
				'wikibase-linkitem-linkpage',
				'wikibase-linkitem-selectlink',
				'wikibase-linkitem-input-site',
				'wikibase-linkitem-input-page',
				'wikibase-linkitem-confirmitem-text',
				'wikibase-linkitem-confirmitem-button',
				'wikibase-linkitem-success-link',
				'wikibase-linkitem-close',
				'wikibase-linkitem-not-loggedin-title',
				'wikibase-linkitem-not-loggedin',
				'wikibase-linkitem-failure',
				'wikibase-replicationnote',
				'wikibase-sitelinks-sitename-columnheading',
				'wikibase-sitelinks-link-columnheading'
			],
		],

		'wikibase.client.action.edit.collapsibleFooter' => $moduleTemplate + [
			'scripts' => 'wikibase.client.action.edit.collapsibleFooter.js',
			'dependencies' => [
				'jquery.makeCollapsible',
				'mediawiki.storage',
				'mediawiki.icon',
			],
		]
	];
} );
