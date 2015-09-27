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

		'wikibase.utilities.ClaimGuidGenerator' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.utilities.ClaimGuidGenerator.js',
			),
			'dependencies' => array(
				'wikibase.utilities.GuidGenerator',
			),
		),

		'wikibase.utilities.GuidGenerator' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.utilities.GuidGenerator.js',
			),
			'dependencies' => array(
				'util.inherit',
				'wikibase.utilities',
			),
		),

		'wikibase.utilities' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.utilities.js',
				'wikibase.utilities.ui.js',
			),
			'styles' => array(
				'wikibase.utilities.ui.css',
			),
			'dependencies' => array(
				'wikibase',
				'jquery.tipsy',
				'mediawiki.language',
			),
			'messages' => array(
				'wikibase-ui-pendingquantitycounter-nonpending',
				'wikibase-ui-pendingquantitycounter-pending',
				'wikibase-ui-pendingquantitycounter-pending-pendingsubpart',
			),
		),

	);

	return $modules;
} );
