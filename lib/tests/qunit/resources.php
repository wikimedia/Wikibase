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
		'wikibase.tests.qunit.testrunner' => $moduleBase + array(
			'scripts' => 'data/testrunner.js',
			'dependencies' => array(
				'test.mediawiki.qunit.testrunner',
				'wikibase',
			),
			'position' => 'top'
		),

		'wikibase.experts.EntityIdInput.tests' => $moduleBase + array(
			'scripts' => array(
				'experts/EntityIdInput.tests.js',
			),
			'dependencies' => array(
				'wikibase.experts.EntityIdInput',
				'wikibase.tests.qunit.testrunner',
			),
		),

		'jquery.removeClassByRegex.tests' => $moduleBase + array(
			'scripts' => array(
				'jquery/jquery.removeClassByRegex.tests.js',
			),
			'dependencies' => array(
				'jquery.removeClassByRegex',
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

		'wikibase.parsers.EntityIdParser.tests' => $moduleBase + array(
			'scripts' => array(
				'parsers/EntityIdParser.tests.js',
			),
			'dependencies' => array(
				'util.inherit',
				'valueParsers.tests',
				'wikibase.tests',
				'wikibase.datamodel',
				'wikibase.EntityIdParser',
				'wikibase.tests.qunit.testrunner',
			),
		),

		'wikibase.dataTypes.tests' => $moduleBase + array(
			'scripts' => array(
				'wikibase.dataTypes/wikibase.dataTypes.tests.js',
			),
			'dependencies' => array(
				'dataTypes.DataTypeStore',
				'wikibase.dataTypes',
			),
		),

		'wikibase.RepoApi.tests' => $moduleBase + array(
			'scripts' => array(
				'wikibase.RepoApi/wikibase.RepoApi.tests.js',
			),
			'dependencies' => array(
				'wikibase',
				'wikibase.AbstractedRepoApi',
				'wikibase.RepoApi',
			),
		),

		'wikibase.RepoApiError.tests' => $moduleBase + array(
			'scripts' => array(
				'wikibase.RepoApi/wikibase.RepoApiError.tests.js',
			),
			'dependencies' => array(
				'wikibase',
				'wikibase.RepoApiError',
			),
			'messages' => array(
				'wikibase-error-unexpected',
				'wikibase-error-remove-timeout',
				'wikibase-error-ui-client-error',
			),
		),

		'wikibase.store.EntityStore.tests' => $moduleBase + array(
			'scripts' => array(
				'wikibase.store/store.EntityStore.tests.js',
			),
			'dependencies' => array(
				'wikibase.store.EntityStore',
			),
		),

		'wikibase.utilities.ClaimGuidGenerator.tests' => $moduleBase + array(
			'scripts' => array(
				'wikibase.utilities/wikibase.utilities.ClaimGuidGenerator.tests.js',
			),
			'dependencies' => array(
				'wikibase.utilities.ClaimGuidGenerator',
			),
		),

		'wikibase.utilities.GuidGenerator.tests' => $moduleBase + array(
			'scripts' => array(
				'wikibase.utilities/wikibase.utilities.GuidGenerator.tests.js',
			),
			'dependencies' => array(
				'wikibase.utilities.GuidGenerator',
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

		'wikibase.compileEntityStoreFromMwConfig.tests' => $moduleBase + array(
			'scripts' => array(
				'wikibase.compileEntityStoreFromMwConfig.tests.js',
			),
			'dependencies' => array(
				'wikibase.compileEntityStoreFromMwConfig',
			),
		),

		'wikibase.Site.tests' => $moduleBase + array(
			'scripts' => array(
				'wikibase.Site.tests.js',
			),
			'dependencies' => array(
				'wikibase.Site',
			),
		),

		'wikibase.sites.tests' => $moduleBase + array(
			'scripts' => array(
				'wikibase.sites.tests.js',
			),
			'dependencies' => array(
				'wikibase',
				'wikibase.Site',
				'wikibase.sites',
				'wikibase.tests.qunit.testrunner',
			),
		),

		'wikibase.tests' => $moduleBase + array(
			'scripts' => array(
				'wikibase.tests.js',
			),
			'dependencies' => array(
				'wikibase',
			),
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
		include( __DIR__ . '/jquery.wikibase/resources.php' )
	);

} );
