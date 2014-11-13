<?php
/**
 * @license GNU GPL v2+
 * @author Adrian Lang <adrian.lang@wikimedia.de>
 */
return call_user_func( function() {
	preg_match( '+' . preg_quote( DIRECTORY_SEPARATOR ) . '(?:vendor|extensions)'
		. preg_quote( DIRECTORY_SEPARATOR ) . '.*+', __DIR__, $remoteExtPath );

	$moduleTemplate = array(
		'localBasePath' => __DIR__,
		'remoteExtPath' => '..' . $remoteExtPath[0],
	);

	return array(

		'wikibase.entityChangers.__namespace' => $moduleTemplate + array(
			'scripts' => array(
				'namespace.js',
			),
			'dependencies' => array(
				'wikibase',
			),
		),

		'wikibase.entityChangers.AliasesChanger' => $moduleTemplate + array(
			'scripts' => array(
				'AliasesChanger.js',
			),
			'dependencies' => array(
				'wikibase.datamodel.MultiTerm',
				'wikibase.entityChangers.__namespace',
				'wikibase.api.RepoApiError',
			),
		),

		'wikibase.entityChangers.ClaimsChanger' => $moduleTemplate + array(
			'scripts' => array(
				'ClaimsChanger.js',
			),
			'dependencies' => array(
				'wikibase.entityChangers.__namespace',
				'wikibase.api.RepoApiError',
			),
		),

		'wikibase.entityChangers.DescriptionsChanger' => $moduleTemplate + array(
			'scripts' => array(
				'DescriptionsChanger.js',
			),
			'dependencies' => array(
				'wikibase.entityChangers.__namespace',
				'wikibase.api.RepoApiError',
			)
		),

		'wikibase.entityChangers.EntityChangersFactory' => $moduleTemplate + array(
			'scripts' => array(
				'EntityChangersFactory.js',
			),
			'dependencies' => array(
				'wikibase.entityChangers.__namespace',
				'wikibase.entityChangers.AliasesChanger',
				'wikibase.entityChangers.ClaimsChanger',
				'wikibase.entityChangers.DescriptionsChanger',
				'wikibase.entityChangers.LabelsChanger',
				'wikibase.entityChangers.ReferencesChanger',
				'wikibase.entityChangers.SiteLinksChanger',
				'wikibase.serialization.ClaimDeserializer',
				'wikibase.serialization.ClaimSerializer',
				'wikibase.serialization.ReferenceDeserializer',
				'wikibase.serialization.ReferenceSerializer',
				'wikibase.serialization.StatementDeserializer',
				'wikibase.serialization.StatementSerializer',
			)
		),

		'wikibase.entityChangers.LabelsChanger' => $moduleTemplate + array(
			'scripts' => array(
				'LabelsChanger.js',
			),
			'dependencies' => array(
				'wikibase.entityChangers.__namespace',
				'wikibase.api.RepoApiError',
			)
		),

		'wikibase.entityChangers.ReferencesChanger' => $moduleTemplate + array(
			'scripts' => array(
				'ReferencesChanger.js',
			),
			'dependencies' => array(
				'wikibase.entityChangers.__namespace',
				'wikibase.api.RepoApiError',
			),
		),

		'wikibase.entityChangers.SiteLinksChanger' => $moduleTemplate + array(
			'scripts' => array(
				'SiteLinksChanger.js',
			),
			'dependencies' => array(
				'wikibase.entityChangers.__namespace',
				'wikibase.api.RepoApiError',
			)
		),

	);

} );
