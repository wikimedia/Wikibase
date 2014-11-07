<?php

/**
 * @license GNU GPL v2+
 * @author Adrian Lang <adrian.lang@wikimedia.de>
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

		'wikibase.entityChangers.AliasesChanger.tests' => $moduleBase + array(
			'scripts' => array(
				'AliasesChanger.tests.js',
			),
			'dependencies' => array(
				'wikibase.datamodel',
				'wikibase.entityChangers.AliasesChanger',
			),
		),

		'wikibase.entityChangers.ClaimsChanger.tests' => $moduleBase + array(
			'scripts' => array(
				'ClaimsChanger.tests.js',
			),
			'dependencies' => array(
				'wikibase.datamodel',
				'wikibase.entityChangers.ClaimsChanger',
				'wikibase.serialization.ClaimDeserializer',
				'wikibase.serialization.ClaimSerializer',
				'wikibase.serialization.StatementDeserializer',
				'wikibase.serialization.StatementSerializer',
			),
		),

		'wikibase.entityChangers.DescriptionsChanger.tests' => $moduleBase + array(
			'scripts' => array(
				'DescriptionsChanger.tests.js',
			),
			'dependencies' => array(
				'wikibase.datamodel',
				'wikibase.entityChangers.DescriptionsChanger'
			),
		),

		'wikibase.entityChangers.LabelsChanger.tests' => $moduleBase + array(
			'scripts' => array(
				'LabelsChanger.tests.js',
			),
			'dependencies' => array(
				'wikibase.datamodel',
				'wikibase.entityChangers.LabelsChanger'
			),
		),

		'wikibase.entityChangers.ReferencesChanger.tests' => $moduleBase + array(
			'scripts' => array(
				'ReferencesChanger.tests.js',
			),
			'dependencies' => array(
				'wikibase.datamodel',
				'wikibase.entityChangers.ReferencesChanger',
				'wikibase.serialization.ReferenceDeserializer',
				'wikibase.serialization.ReferenceSerializer',
			),
		),

		'wikibase.entityChangers.SiteLinksChanger.tests' => $moduleBase + array(
			'scripts' => array(
				'SiteLinksChanger.tests.js',
			),
			'dependencies' => array(
				'wikibase.datamodel',
				'wikibase.entityChangers.SiteLinksChanger'
			),
		),

	);

	return $modules;
} );
