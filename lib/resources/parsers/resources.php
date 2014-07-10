<?php
/**
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 *
 * @codeCoverageIgnoreStart
 */
return call_user_func( function() {

	$remoteExtPathParts = explode(
		DIRECTORY_SEPARATOR . 'extensions' . DIRECTORY_SEPARATOR, __DIR__, 2
	);
	$moduleTemplate = array(
		'localBasePath' => __DIR__,
		'remoteExtPath' => $remoteExtPathParts[1],
	);

	return array(

		'wikibase.parsers.getApiBasedValueParserConstructor' => $moduleTemplate + array(
			'scripts' => array(
				'getApiBasedValueParserConstructor.js',
			),
			'dependencies' => array(
				'jquery',
				'util.inherit',
				'valueParsers.ValueParser',
				'wikibase',
			),
		),

		'wikibase.EntityIdParser' => $moduleTemplate + array(
			'scripts' => array(
				'EntityIdParser.js',
			),
			'dependencies' => array(
				'jquery',
				'util.inherit',
				'valueParsers.ValueParser',
				'wikibase',
				'wikibase.datamodel',
			),
		),

		'wikibase.parsers.getStore' => $moduleTemplate + array(
			'scripts' => array(
				'getStore.js',
			),
			'dependencies' => array(
				'dataValues.values',
				'jquery',
				'valueParsers.parsers',
				'valueParsers.ValueParserStore',
				'wikibase.api.ParseValueCaller',
				'wikibase.parsers.getApiBasedValueParserConstructor',
				'wikibase.datamodel',
				'wikibase.EntityIdParser'
			),
		),

	);

} );
