<?php
/**
 * File for Wikibase resourceloader modules.
 * When included this returns an array with all the modules introduced by Wikibase.
 *
 * @since 0.2
 *
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Daniel Werner
 *
 * @codeCoverageIgnoreStart
 */
return call_user_func( function() {

	$moduleTemplate = array(
		'localBasePath' => __DIR__,
		'remoteExtPath' =>  'Wikibase/lib/resources',
	);

	return array(
		// common styles independent from JavaScript being enabled or disabled
		'wikibase.common' => $moduleTemplate + array(
			'styles' => array(
				'wikibase.css',
				'wikibase.ui.Toolbar.css'
			)
		),

		'wikibase.sites' => $moduleTemplate + array(
			'class' => 'Wikibase\SitesModule',
		),

		'wikibase.repoAccess' => $moduleTemplate + array(
			'class' => 'Wikibase\RepoAccessModule',
		),

		'wikibase' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.js',
				'wikibase.Site.js',
				'wikibase.RevisionStore.js'
			),
			'dependencies' => array(
				'wikibase.common',
				'wikibase.sites',
				'wikibase.templates'
			),
			'messages' => array(
				'special-createitem',
				'wb-special-createitem-new-item-notification'
			)
		),

		'wikibase.parsers' => $moduleTemplate + array(
			'scripts' => array(
				'parsers/EntityIdParser.js',
			),
			'dependencies' => array(
				'valueParsers.ValueParser',
				'valueParsers.api',
				'wikibase.datamodel',
			),
		),

		'wikibase.datamodel' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.datamodel/wikibase.EntityId.js',
				'wikibase.datamodel/wikibase.Snak.js',
				'wikibase.datamodel/wikibase.PropertyValueSnak.js',
				'wikibase.datamodel/wikibase.PropertySomeValueSnak.js',
				'wikibase.datamodel/wikibase.PropertyNoValueSnak.js',
				'wikibase.datamodel/wikibase.Reference.js',
				'wikibase.datamodel/wikibase.Claim.js',
				'wikibase.datamodel/wikibase.Statement.js',
			),
			'dependencies' => array(
				'wikibase',
				'wikibase.utilities',
				'dataValues.values' // DataValues extension
			)
		),

		'wikibase.store' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.store/wikibase.EntityStore.js',
				'wikibase.store/wikibase.RepoApi.js',
			),
			'dependencies' => array(
				'user.tokens',
				'wikibase.datamodel',
				'wikibase.repoAccess',
				'jquery.json'
			)
		),

		'wikibase.utilities' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.utilities/wikibase.utilities.js',
				'wikibase.utilities/wikibase.utilities.ObservableObject.js',
				'wikibase.utilities/wikibase.utilities.ui.StatableObject.js',
			),
			'dependencies' => array(
				'wikibase'
			)
		),

		'wikibase.utilities.jQuery' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.utilities/wikibase.utilities.js',
				'wikibase.utilities/wikibase.utilities.jQuery.js',
			),
			'dependencies' => array(
				'wikibase.utilities'
			)
		),

		'wikibase.utilities.jQuery.ui.tagadata' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.utilities/wikibase.utilities.jQuery.ui.tagadata/wikibase.utilities.jQuery.ui.tagadata.js',
			),
			'styles' => array(
				'wikibase.utilities/wikibase.utilities.jQuery.ui.tagadata/wikibase.utilities.jQuery.ui.tagadata.css',
			),
			'dependencies' => array(
				'jquery.eachchange',
				'jquery.effects.blind',
				'jquery.inputAutoExpand',
				'jquery.ui.widget'
			)
		),

		'wikibase.tests.qunit.testrunner' => $moduleTemplate + array(
			'scripts' => '../tests/qunit/data/testrunner.js',
			'dependencies' => array(
				'mediawiki.tests.qunit.testrunner',
				'wikibase'
			),
			'position' => 'top'
		),

		// should be independent from the rest of Wikibase (or only use other stuff that could go into core)
		'wikibase.ui.Toolbar' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.ui.js',
				'wikibase.ui.Base.js',
				'wikibase.ui.Tooltip.js',
				'wikibase.ui.Tooltip.Extension.js',
				'wikibase.ui.Toolbar.js',
				'wikibase.ui.Toolbar.Group.js',
				'wikibase.ui.Toolbar.Label.js',
				'wikibase.ui.Toolbar.Button.js'
			),
			'dependencies' => array(
				'jquery.nativeEventHandler',
				'jquery.tipsy',
				'jquery.ui.core',
				'mediawiki.legacy.shared',
				'wikibase',
				'wikibase.common',
				'wikibase.utilities',
				'wikibase.utilities.jQuery'
			),
			'messages' => array(
				'wikibase-tooltip-error-details'
			)
		),

		'wikibase.ui.PropertyEditTool' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.ui.js',
				'wikibase.ui.Base.js',
				'wikibase.ui.Toolbar.EditGroup.js', // related to EditableValue, see todo in file
				'wikibase.ui.PropertyEditTool.js',
				'wikibase.ui.PropertyEditTool.EditableValue.js',
				'wikibase.ui.PropertyEditTool.EditableValue.Interface.js',
				'wikibase.ui.PropertyEditTool.EditableValue.AutocompleteInterface.js',
				'wikibase.ui.PropertyEditTool.EditableValue.SitePageInterface.js',
				'wikibase.ui.PropertyEditTool.EditableValue.SiteIdInterface.js',
				'wikibase.ui.PropertyEditTool.EditableValue.ListInterface.js',
				'wikibase.ui.PropertyEditTool.EditableValue.AliasesInterface.js',
				'wikibase.ui.PropertyEditTool.EditableDescription.js',
				'wikibase.ui.PropertyEditTool.EditableLabel.js',
				'wikibase.ui.PropertyEditTool.EditableSiteLink.js',
				'wikibase.ui.PropertyEditTool.EditableAliases.js',
				'wikibase.ui.LabelEditTool.js',
				'wikibase.ui.DescriptionEditTool.js',
				'wikibase.ui.SiteLinksEditTool.js',
				'wikibase.ui.AliasesEditTool.js',
			),
			'styles' => array(
				'wikibase.ui.PropertyEditTool.css'
			),
			'dependencies' => array(
				'jquery.eachchange',
				'jquery.nativeEventHandler',
				'jquery.inputAutoExpand',
				'jquery.tablesorter',
				'jquery.ui.suggester',
				'jquery.ui.entityselector',
				'jquery.wikibase.siteselector',
				'mediawiki.api',
				'mediawiki.language',
				'mediawiki.Title',
				'mediawiki.jqueryMsg', // for {{plural}} and {{gender}} support in messages
				'wikibase',
				'wikibase.ui.Toolbar',
				'wikibase.utilities',
				'wikibase.utilities.jQuery',
				'wikibase.utilities.jQuery.ui.tagadata',
				'wikibase.store',
			),
			'messages' => array(
				'wikibase-cancel',
				'wikibase-edit',
				'wikibase-save',
				'wikibase-add',
				'wikibase-save-inprogress',
				'wikibase-remove-inprogress',
				'wikibase-label-edit-placeholder',
				'wikibase-description-edit-placeholder',
				'wikibase-aliases-label',
				'wikibase-aliases-input-help-message',
				'wikibase-alias-edit-placeholder',
				'wikibase-sitelink-site-edit-placeholder',
				'wikibase-sitelink-page-edit-placeholder',
				'wikibase-label-input-help-message',
				'wikibase-description-input-help-message',
				'wikibase-sitelinks-input-help-message',
				'wikibase-sitelinks-sitename-columnheading',
				'wikibase-sitelinks-siteid-columnheading',
				'wikibase-sitelinks-link-columnheading',
				'wikibase-remove',
				'wikibase-propertyedittool-full',
				'wikibase-propertyedittool-counter',
				'wikibase-propertyedittool-counter-pending',
				'wikibase-propertyedittool-counter-pending-pendingsubpart',
				'wikibase-propertyedittool-counter-pending-tooltip',
				'wikibase-sitelinksedittool-full',
				'wikibase-error-save-generic',
				'wikibase-error-remove-generic',
				'wikibase-error-save-connection',
				'wikibase-error-remove-connection',
				'wikibase-error-save-timeout',
				'wikibase-error-remove-timeout',
				'wikibase-error-autocomplete-connection',
				'wikibase-error-autocomplete-response',
				'wikibase-error-ui-client-error',
				'wikibase-error-ui-no-external-page',
				'wikibase-error-ui-cant-edit',
				'wikibase-error-ui-no-permissions',
				'wikibase-error-ui-link-exists',
				'wikibase-error-ui-session-failure',
				'wikibase-error-ui-edit-conflict',
				'wikibase-restrictionedit-tooltip-message',
				'wikibase-blockeduser-tooltip-message'
			)
		),

		'wikibase.templates' => $moduleTemplate + array(
			'class' => 'Wikibase\TemplateModule',
			'scripts' => 'templates.js'
		),

		'jquery.ui.TemplatedWidget' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.ui/jquery.ui.TemplatedWidget.js'
			),
			'dependencies' => array(
				'wikibase.templates',
				'jquery.ui.widget'
			)
		),

		'jquery.nativeEventHandler' => $moduleTemplate + array(
			'scripts' => array(
				'jquery/jquery.nativeEventHandler.js'
			)
		),

		'jquery.eachchange' => $moduleTemplate + array(
			'scripts' => array(
				'jquery/jquery.eachchange.js'
			),
			'dependencies' => array(
				'jquery.client'
			)
		),

		'jquery.inputAutoExpand' => $moduleTemplate + array(
			'scripts' => array(
				'jquery/jquery.inputAutoExpand.js',
			),
			'dependencies' => array(
				'jquery.eachchange'
			)
		),

		'jquery.ui.suggester' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.ui/jquery.ui.suggester.js'
			),
			'styles' => array(
				'jquery.ui/themes/default/jquery.ui.suggester.css'
			),
			'dependencies' => array(
				'jquery.ui.autocomplete'
			)
		),

		'jquery.wikibase.siteselector' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/jquery.wikibase.siteselector.js'
			),
			'dependencies' => array(
				'jquery.ui.suggester',
				'wikibase'
			)
		),

		'jquery.wikibase.snakview' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/jquery.wikibase.snakview/snakview.js',
				'jquery.wikibase/jquery.wikibase.snakview/snakview.SnakTypeSelector.js',
				'jquery.wikibase/jquery.wikibase.snakview/snakview.ViewState.js',
				'jquery.wikibase/jquery.wikibase.snakview/snakview.variations.js',
				'jquery.wikibase/jquery.wikibase.snakview/snakview.variations.Variation.js',
				'jquery.wikibase/jquery.wikibase.snakview/snakview.variations.Value.js',
				'jquery.wikibase/jquery.wikibase.snakview/snakview.variations.SomeValue.js',
				'jquery.wikibase/jquery.wikibase.snakview/snakview.variations.NoValue.js',
			),
			'dependencies' => array(
				'jquery.eachchange',
				'jquery.nativeEventHandler',
				'jquery.ui.entityselector',
				'wikibase.datamodel',
				'dataTypes.jquery.valueview.views',
				'wikibase.jquery.valueview.views',
				'wikibase.store', // required for getting datatype from entityselector selected property
				'mediawiki.legacy.shared',
				'jquery.ui.TemplatedWidget'
			),
			'messages' => array(
				'wikibase-snakview-property-input-placeholder',
				'wikibase-snakview-unsupportedsnaktype',
				'wikibase-snakview-choosesnaktype',
				'wikibase-snakview-variation-unsupporteddatatype',
				'wikibase-snakview-variation-datavaluetypemismatch',
				'wikibase-snakview-variation-datavaluetypemismatch-details',
				'datatypes-type-wikibase-item',
				'wikibase-snakview-variations-somevalue-label',
				'wikibase-snakview-variations-novalue-label',
				'wikibase-snakview-snaktypeselector-value',
				'wikibase-snakview-snaktypeselector-somevalue',
				'wikibase-snakview-snaktypeselector-novalue'
			)
		),

		'jquery.wikibase.claimview' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/jquery.wikibase.claimview.js'
			),
			'dependencies' => array(
				'jquery.wikibase.snakview'
			),
			'messages' => array(
				'wikibase-claimview-snak-tooltip',
				'wikibase-claimview-snak-new-tooltip'
			)
		),

		'jquery.wikibase.claimlistview' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/jquery.wikibase.claimlistview.js'
			),
			'dependencies' => array(
				'jquery.wikibase.claimview',
				'wikibase.templates'
			),
			'messages' => array(
				'wikibase-label-empty'
			)
		),

		'jquery.wikibase.entityview' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/jquery.wikibase.entityview.js'
			),
			'dependencies' => array(
				'jquery.wikibase.claimlistview',
				'wikibase.templates'
			),
			'messages' => array(
				'wikibase-anonymouseditwarning-item',
				'wikibase-anonymouseditwarning-property'
			)
		),

		'jquery.ui.entityselector' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.ui/jquery.ui.entityselector.js'
			),
			'styles' => array(
				'jquery.ui/themes/default/jquery.ui.entityselector.css'
			),
			'dependencies' => array(
				'jquery.ui.suggester',
				'jquery.ui.resizable',
				'jquery.eachchange'
			)
		),

		// jQuery.valueview views for Wikibase specific DataValues/DataTypes
		'wikibase.jquery.valueview.views' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.valueview.views/wikibaseItem.js',
			),
			'styles' => array(
				'jquery.valueview.views/views.css',
			),
			'dependencies' => array(
				'dataTypes.jquery.valueview',
				'wikibase.parsers',
				'jquery.eachchange',
			),
		),

	);
} );
// @codeCoverageIgnoreEnd
