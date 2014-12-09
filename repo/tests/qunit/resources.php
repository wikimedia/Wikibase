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
	$moduleBase = array(
		'localBasePath' => __DIR__,
		'remoteExtPath' => $remoteExtPathParts[1],
	);

	$modules = array(

		'jquery.removeClassByRegex.tests' => $moduleBase + array(
			'scripts' => array(
				'jquery/jquery.removeClassByRegex.tests.js',
			),
			'dependencies' => array(
				'jquery.removeClassByRegex',
			),
		),

		'jquery.sticknode.tests' => $moduleBase + array(
			'scripts' => array(
				'jquery/jquery.sticknode.tests.js',
			),
			'dependencies' => array(
				'jquery.sticknode',
			),
		),

		'jquery.util.EventSingletonManager.tests' => $moduleBase + array(
			'scripts' => array(
				'jquery/jquery.util.EventSingletonManager.tests.js',
			),
			'dependencies' => array(
				'jquery.util.EventSingletonManager',
			),
		),

		'jquery.ui.closeable.tests' => $moduleBase + array(
			'scripts' => array(
				'jquery.ui/jquery.ui.closeable.tests.js',
			),
			'dependencies' => array(
				'jquery.ui.closeable',
			),
		),

		'jquery.ui.tagadata.tests' => $moduleBase + array(
			'scripts' => array(
				'jquery.ui/jquery.ui.tagadata.tests.js',
			),
			'dependencies' => array(
				'jquery.ui.tagadata',
			),
		),

		'jquery.ui.EditableTemplatedWidget.tests' => $moduleBase + array(
			'scripts' => array(
				'jquery.ui/jquery.ui.EditableTemplatedWidget.tests.js',
			),
			'dependencies' => array(
				'jquery.ui.EditableTemplatedWidget',
				'wikibase.templates',
			),
		),

		'jquery.ui.TemplatedWidget.tests' => $moduleBase + array(
			'scripts' => array(
				'jquery.ui/jquery.ui.TemplatedWidget.tests.js',
			),
			'dependencies' => array(
				'jquery.ui.TemplatedWidget',
				'wikibase.templates',
			),
		),

		'jquery.wikibase.entitysearch.tests' => $moduleBase + array(
			'scripts' => array(
				'jquery.wikibase/jquery.wikibase.entitysearch.tests.js',
			),
			'dependencies' => array(
				'jquery.wikibase.entitysearch',
			),
		),

		'templates.tests' => $moduleBase + array(
			'scripts' => array(
				'templates.tests.js',
			),
			'dependencies' => array(
				'wikibase.templates',
			),
		),

		'wikibase.dataTypeStore.tests' => $moduleBase + array(
			'scripts' => array(
				'dataTypes/wikibase.dataTypeStore.tests.js',
			),
			'dependencies' => array(
				'dataTypes.DataTypeStore',
				'wikibase.dataTypeStore',
			),
		),

		'wikibase.experts.Item.tests' => $moduleBase + array(
			'scripts' => array(
				'experts/Item.tests.js',
			),
			'dependencies' => array(
				'wikibase.experts.Item',
				'wikibase.tests.qunit.testrunner',
			),
		),

		'wikibase.experts.Property.tests' => $moduleBase + array(
			'scripts' => array(
				'experts/Property.tests.js',
			),
			'dependencies' => array(
				'wikibase.experts.Property',
				'wikibase.tests.qunit.testrunner',
			),
		),

		'wikibase.getLanguageNameByCode.tests' => $moduleBase + array(
			'scripts' => array(
				'wikibase.getLanguageNameByCode.tests.js'
			),
			'dependencies' => array(
				'wikibase.getLanguageNameByCode'
			)
		),

		'wikibase.ValueViewBuilder.tests' => $moduleBase + array(
			'scripts' => array(
				'wikibase.ValueViewBuilder.tests.js'
			),
			'dependencies' => array(
				'test.sinonjs',
				'wikibase.ValueViewBuilder'
			)
		),

	);

	return array_merge(
		$modules,
		include( __DIR__ . '/entityChangers/resources.php' ),
		include __DIR__ . '/store/resources.php',
		include __DIR__ . '/utilities/resources.php'
	);

} );
