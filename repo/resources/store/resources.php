<?php
/**
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 *
 * @codeCoverageIgnoreStart
 */
return call_user_func( function() {
	preg_match( '+' . preg_quote( DIRECTORY_SEPARATOR ) . '(?:vendor|extensions)'
		. preg_quote( DIRECTORY_SEPARATOR ) . '.*+', __DIR__, $remoteExtPath );

	$moduleTemplate = array(
		'localBasePath' => __DIR__,
		'remoteExtPath' => '..' . $remoteExtPath[0],
	);

	$modules = array(

		'wikibase.store.ApiEntityStore' => $moduleTemplate + array(
			'scripts' => array(
				'store.ApiEntityStore.js',
			),
			'dependencies' => array(
				'wikibase.store',
				'wikibase.store.EntityStore',
			),
		),

		'wikibase.store.CachingEntityStore' => $moduleTemplate + array(
			'scripts' => array(
				'store.CachingEntityStore.js',
			),
			'dependencies' => array(
				'util.inherit',
				'wikibase.store',
				'wikibase.store.EntityStore',
			),
		),

		'wikibase.store.CombiningEntityStore' => $moduleTemplate + array(
			'scripts' => array(
				'store.CombiningEntityStore.js',
			),
			'dependencies' => array(
				'util.inherit',
				'wikibase.store',
				'wikibase.store.EntityStore',
			),
		),

		'wikibase.store.EntityStore' => $moduleTemplate + array(
			'scripts' => array(
				'store.EntityStore.js',
			),
			'dependencies' => array(
				'util.inherit',
				'wikibase.store',
			),
		),

		'wikibase.store.FetchedContent' => $moduleTemplate + array(
			'scripts' => array(
				'store.FetchedContent.js',
			),
			'dependencies' => array(
				'mediawiki.Title',
				'wikibase.store',
			),
		),

		'wikibase.store.FetchedContentUnserializer' => $moduleTemplate + array(
			'scripts' => array(
				'store.FetchedContentUnserializer.js',
			),
			'dependencies' => array(
				'mediawiki.Title',
				'util.inherit',
				'wikibase.serialization.Deserializer',
				'wikibase.store',
				'wikibase.store.FetchedContent',
			),
		),

		'wikibase.store' => $moduleTemplate + array(
			'scripts' => array(
				'store.js',
			),
			'dependencies' => array(
				'wikibase',
			),
		),

	);

	return $modules;
} );
