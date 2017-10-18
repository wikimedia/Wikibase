<?php
/**
 * @license GNU GPL v2+
 * @author Daniel Werner < daniel.werner@wikimedia.de >
 * @author H. Snater < mediawiki@snater.com >
 *
 * @codeCoverageIgnoreStart
 */
return call_user_func( function() {

	$dir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'wikibase-data-values-value-view' . DIRECTORY_SEPARATOR . 'src';

	$remoteExtPathParts = explode(
		DIRECTORY_SEPARATOR . 'extensions' . DIRECTORY_SEPARATOR, $dir, 2
	);
	$moduleTemplate = array(
		'localBasePath' => $dir,
		'remoteExtPath' => $remoteExtPathParts[1],
	);

	$resources = array(

		// Loads the actual valueview widget into jQuery.valueview.valueview and maps
		// jQuery.valueview to jQuery.valueview.valueview without losing any properties.
		'jquery.valueview' => $moduleTemplate + array(
				'scripts' => array(
					'jquery.valueview.js',
				),
				'dependencies' => array(
					'jquery.valueview.valueview',
				),
			),

		'jquery.valueview.Expert' => $moduleTemplate + array(
				'scripts' => array(
					'jquery.valueview.Expert.js',
				),
				'dependencies' => array(
					'util.inherit',
					'util.CombiningMessageProvider',
					'util.HashMessageProvider',
					'util.Notifier',
					'util.Extendable'
				),
			),

		'jquery.valueview.ExpertStore' => $moduleTemplate + array(
				'scripts' => array(
					'jquery.valueview.ExpertStore.js',
				),
			),

		'jquery.valueview.experts' => $moduleTemplate + array(
				'scripts' => array(
					'jquery.valueview.experts.js',
				),
			),

		// The actual valueview widget:
		'jquery.valueview.valueview' => $moduleTemplate + array(
				'scripts' => array(
					'jquery.valueview.valueview.js',
				),
				'styles' => array(
					'jquery.valueview.valueview.css',
				),
				'dependencies' => array(
					'dataValues.DataValue',
					'jquery.ui.widget',
					'jquery.valueview.ViewState',
					'jquery.valueview.ExpertStore',
					'jquery.valueview.experts.EmptyValue',
					'jquery.valueview.experts.UnsupportedValue',
					'util.Notifier',
					'valueFormatters.ValueFormatter',
					'valueParsers.ValueParserStore',
				),
			),

		'jquery.valueview.ViewState' => $moduleTemplate + array(
				'scripts' => array(
					'jquery.valueview.ViewState.js',
				),
			),

	);

	return array_merge(
		$resources,
		include __DIR__ . '/experts/resources.php',
		include __DIR__ . '/ExpertExtender/resources.php'
	);

} );
