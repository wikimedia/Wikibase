<?php

/**
 * @license GNU GPL v2+
 * @author Adrian Heine < adrian.heine@wikimedia.de >
 */
return call_user_func( function() {
	preg_match( '+' . preg_quote( DIRECTORY_SEPARATOR ) . '(?:vendor|extensions)'
		. preg_quote( DIRECTORY_SEPARATOR ) . '.*+', __DIR__, $remoteExtPath );

	$moduleTemplate = array(
		'localBasePath' => __DIR__,
		'remoteExtPath' => '..' . $remoteExtPath[0]
	);

	$modules = array(

		'wikibase.view.ViewFactory.tests' => $moduleTemplate + array(
			'scripts' => array(
				'ViewFactory.tests.js',
			),
			'dependencies' => array(
				'wikibase.view.ViewFactory',
				'wikibase.ValueViewBuilder'
			),
		),

	);

	return $modules;
} );
