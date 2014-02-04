<?php

use DataTypes\DataTypeFactory;
use Wikibase\Client\WikibaseClient;
use Wikibase\Lib\WikibaseDataTypeBuilders;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Settings;

/**
 * File for Wikibase resourceloader modules.
 * When included this returns an array with all the modules introduced by Wikibase.
 *
 * @since 0.2
 *
 * @licence GNU GPL v2+
 * @author Daniel Werner
 * @author H. Snater < mediawiki@snater.com >
 *
 * @codeCoverageIgnoreStart
 */
return call_user_func( function() {
	$remoteExtPathParts = explode( DIRECTORY_SEPARATOR . 'extensions' . DIRECTORY_SEPARATOR , __DIR__, 2 );
	$moduleTemplate = array(
		'localBasePath' => __DIR__,
		'remoteExtPath' => $remoteExtPathParts[1],
	);

	$modules = array(
		// common styles independent from JavaScript being enabled or disabled
		'wikibase.common' => $moduleTemplate + array(
			'styles' => array(
				'wikibase.css',
			)
		),

		'wikibase.sites' => $moduleTemplate + array(
			'class' => 'Wikibase\SitesModule'
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
				'wb-special-newitem-new-item-notification'
			)
		),

		'wikibase.parsers.api' => $moduleTemplate + array(
			'scripts' => array(
				'parsers/wikibase.parsers.api.js',
			),
			'dependencies' => array(
				'wikibase',
			),
		),

		'wikibase.ApiBasedValueParser' => $moduleTemplate + array(
			'scripts' => array(
				'parsers/ApiBasedValueParser.js',
			),
			'dependencies' => array(
				'util.inherit',
				'valueParsers.ValueParser',
				'wikibase.parsers.api',
			),
		),

		'wikibase.EntityIdParser' => $moduleTemplate + array(
			'scripts' => array(
				'parsers/EntityIdParser.js',
			),
			'dependencies' => array(
				'util.inherit',
				'valueParsers.ValueParser',
				'wikibase',
				'wikibase.datamodel',
			),
		),

		'wikibase.GlobeCoordinateParser' => $moduleTemplate + array(
			'scripts' => array(
				'parsers/GlobeCoordinateParser.js',
			),
			'dependencies' => array(
				'util.inherit',
				'wikibase.ApiBasedValueParser',
			),
		),

		'wikibase.QuantityParser' => $moduleTemplate + array(
			'scripts' => array(
				'parsers/QuantityParser.js',
			),
			'dependencies' => array(
				'util.inherit',
				'wikibase.ApiBasedValueParser',
			),
		),

		'wikibase.parsers' => $moduleTemplate + array(
			'scripts' => array(
				'parsers/wikibase.parsers.register.js',
			),
			'dependencies' => array(
				'dataValues.values',
				'mw.ext.valueParsers',
				'wikibase.datamodel',
				'wikibase.EntityIdParser',
				'wikibase.GlobeCoordinateParser',
				'wikibase.QuantityParser',
			),
		),

		'wikibase.formatters.api' => $moduleTemplate + array(
			'scripts' => array(
				'formatters/wikibase.formatters.api.js',
			),
			'dependencies' => array(
				'mediawiki.api',
				'dataTypes',
				'wikibase',
			),
		),

		'wikibase.ApiBasedValueFormatter' => $moduleTemplate + array(
			'scripts' => array(
				'formatters/ApiBasedValueFormatter.js',
			),
			'dependencies' => array(
				'mediawiki.api',
				'dataTypes',
				'util.inherit',
				'valueFormatters.ValueFormatter',
				'wikibase.formatters.api',
			),
		),

		'wikibase.QuantityFormatter' => $moduleTemplate + array(
			'scripts' => array(
				'formatters/QuantityFormatter.js',
			),
			'dependencies' => array(
				'util.inherit',
				'wikibase.ApiBasedValueFormatter',
			),
		),

		'wikibase.formatters' => $moduleTemplate + array(
			'scripts' => array(
				'formatters/wikibase.formatters.register.js',
			),
			'dependencies' => array(
				'dataTypes',
				'dataValues.values',
				'mw.ext.valueFormatters',
				'wikibase.dataTypes',
				'wikibase.QuantityFormatter',
			),
		),

		'wikibase.dataTypes' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.dataTypes/wikibase.dataTypes.js',
			),
			'dependencies' => array(
				'wikibase',
				'dataTypes',
				'mw.config.values.wbDataTypes',
				'mw.ext.valueView',
				'jquery.valueview.experts',
				'jquery.valueview.experts.UrlType',
				'jquery.valueview.experts.CommonsMediaType',
			),
		),

		'mw.config.values.wbDataTypes' => $moduleTemplate + array(
			'class' => 'DataTypes\DataTypesModule',
			'datatypefactory' => function() {
				// TODO: extreme uglynes here! Get rid of this method!
				if ( defined( 'WB_VERSION' ) ) { // repo mode
					$repo = WikibaseRepo::getDefaultInstance();
					$entityIdParser = $repo->getEntityIdParser();
					$entityLookup = $repo->getEntityLookup();
				} elseif ( defined( 'WBC_VERSION' ) ) { // client mode
					$client = WikibaseClient::getDefaultInstance();
					$entityIdParser = $client->getEntityIdParser();
					$entityLookup = $client->getStore()->getEntityLookup();
				} else {
					throw new \RuntimeException( "Neither repo nor client found!" );
				}

				$settings = Settings::singleton();

				$urlSchemes = $settings->getSetting( 'urlSchemes' );
				$builders = new WikibaseDataTypeBuilders( $entityLookup, $entityIdParser, $urlSchemes );

				$typeBuilderSpecs = array_intersect_key(
					$builders->getDataTypeBuilders(),
					array_flip( $settings->getSetting( 'dataTypes' ) )
				);

				return new DataTypeFactory( $typeBuilderSpecs );
			},
			'datatypesconfigvarname' => 'wbDataTypes',
		),

		'wikibase.datamodel' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.datamodel/datamodel.entities/wikibase.Entity.js',
				'wikibase.datamodel/datamodel.entities/wikibase.Item.js',
				'wikibase.datamodel/datamodel.entities/wikibase.Property.js',
				'wikibase.datamodel/wikibase.EntityId.js',
				'wikibase.datamodel/wikibase.Snak.js',
				'wikibase.datamodel/wikibase.SnakList.js',
				'wikibase.datamodel/wikibase.PropertyValueSnak.js',
				'wikibase.datamodel/wikibase.PropertySomeValueSnak.js',
				'wikibase.datamodel/wikibase.PropertyNoValueSnak.js',
				'wikibase.datamodel/wikibase.Reference.js',
				'wikibase.datamodel/wikibase.Claim.js',
				'wikibase.datamodel/wikibase.Statement.js',
			),
			'dependencies' => array(
				'util.inherit',
				'wikibase',
				'mw.ext.dataValues', // DataValues extension
				'dataTypes', // DataTypes extension
				'wikibase.dataTypes',
			)
		),

		'wikibase.serialization' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.serialization/serialization.js',
				'wikibase.serialization/serialization.Serializer.js',
				'wikibase.serialization/serialization.Unserializer.js',
				'wikibase.serialization/serialization.SerializerFactory.js',
			),
			'dependencies' => array(
				'util.inherit',
				'wikibase',
			)
		),

		'wikibase.serialization.entities' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.serialization/serialization.EntityUnserializer.js',
				'wikibase.serialization/serialization.EntityUnserializer.propertyExpert.js',
			),
			'dependencies' => array(
				'util.inherit',
				'wikibase.serialization',
				'wikibase.datamodel',
			)
		),

		'wikibase.serialization.fetchedcontent' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.serialization/serialization.FetchedContentUnserializer.js',
			),
			'dependencies' => array(
				'util.inherit',
				'wikibase.serialization',
				'wikibase.store.FetchedContent',
			)
		),

		'wikibase.store' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.store/store.js'
			),
			'dependencies' => array(
				'wikibase'
			)
		),

		'wikibase.store.FetchedContent' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.store/store.FetchedContent.js',
			),
			'dependencies' => array(
				'wikibase.store',
				'mediawiki.Title',
			)
		),

		'wikibase.AbstractedRepoApi' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.RepoApi/wikibase.AbstractedRepoApi.js',
			),
			'dependencies' => array(
				'wikibase.datamodel',
				'wikibase.serialization.entities',
				'wikibase.RepoApi',
			)
		),

		'wikibase.RepoApi' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.RepoApi/wikibase.RepoApi.js',
			),
			'dependencies' => array(
				'jquery.json',
				'user.tokens',
				'mediawiki.api',
				'wikibase.repoAccess',
				'wikibase',
			)
		),

		'wikibase.RepoApiError' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.RepoApi/wikibase.RepoApiError.js',
			),
			'messages' => array(
				'wikibase-error-unexpected',
				'wikibase-error-save-generic',
				'wikibase-error-remove-generic',
				'wikibase-error-save-timeout',
				'wikibase-error-remove-timeout',
				'wikibase-error-ui-client-error',
				'wikibase-error-ui-no-external-page',
				'wikibase-error-ui-cant-edit',
				'wikibase-error-ui-no-permissions',
				'wikibase-error-ui-link-exists',
				'wikibase-error-ui-session-failure',
				'wikibase-error-ui-edit-conflict',
				'wikibase-error-ui-edit-conflict',
			),
			'dependencies' => array(
				'util.inherit',
				'wikibase',
			)
		),

		'wikibase.utilities' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.utilities/wikibase.utilities.js',
				'wikibase.utilities/wikibase.utilities.ObservableObject.js',
				'wikibase.utilities/wikibase.utilities.ui.js',
				'wikibase.utilities/wikibase.utilities.ui.StatableObject.js',
			),
			'styles' => array(
				'wikibase.utilities/wikibase.utilities.ui.css',
			),
			'dependencies' => array(
				'wikibase',
				'jquery.tipsy',
				'util.inherit',
				'mediawiki.language',
			),
			'messages' => array(
				'wikibase-ui-pendingquantitycounter-nonpending',
				'wikibase-ui-pendingquantitycounter-pending',
				'wikibase-ui-pendingquantitycounter-pending-pendingsubpart',
				'wikibase-label-empty',
				'wikibase-deletedentity-item',
				'wikibase-deletedentity-property',
				'wikibase-deletedentity-query',
				'word-separator',
				'parentheses',
			)
		),

		'wikibase.utilities.GuidGenerator' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.utilities/wikibase.utilities.GuidGenerator.js',
			),
			'dependencies' => array(
				'util.inherit',
				'wikibase.utilities',
			)
		),

		'wikibase.utilities.ClaimGuidGenerator' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.utilities/wikibase.utilities.ClaimGuidGenerator.js',
			),
			'dependencies' => array(
				'wikibase.datamodel',
				'wikibase.utilities.GuidGenerator',
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
				'jquery.inputautoexpand',
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

		'wikibase.ui.Base' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.ui.js',
				'wikibase.ui.Base.js',
			),
			'dependencies' => array(
				'wikibase',
				'wikibase.utilities',
			),
		),

		'wikibase.ui.PropertyEditTool' => $moduleTemplate + array(
			'scripts' => array(
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
				'jquery.NativeEventHandler',
				'jquery.inputautoexpand',
				'jquery.tablesorter',
				'jquery.ui.suggester',
				'util.inherit',
				'jquery.wikibase.entityselector',
				'jquery.wikibase.toolbareditgroup',
				'jquery.wikibase.siteselector',
				'mediawiki.api',
				'mediawiki.language',
				'mediawiki.Title',
				'mediawiki.jqueryMsg', // for {{plural}} and {{gender}} support in messages
				'wikibase',
				'wikibase.RepoApiError',
				'wikibase.ui.Base',
				'wikibase.utilities',
				'wikibase.utilities.jQuery',
				'wikibase.utilities.jQuery.ui.tagadata',
				'wikibase.AbstractedRepoApi',
			),
			'messages' => array(
				'wikibase-cancel',
				'wikibase-edit',
				'wikibase-save',
				'wikibase-add',
				'wikibase-save-inprogress',
				'wikibase-remove-inprogress',
				'wikibase-label-edit-placeholder',
				'wikibase-label-edit-placeholder-language-aware',
				'wikibase-description-edit-placeholder',
				'wikibase-description-edit-placeholder-language-aware',
				'wikibase-aliases-label',
				'wikibase-aliases-input-help-message',
				'wikibase-alias-edit-placeholder',
				'wikibase-sitelink-site-edit-placeholder',
				'wikibase-sitelink-page-edit-placeholder',
				'wikibase-label-input-help-message',
				'wikibase-description-input-help-message',
				'wikibase-sitelinks-input-help-message',
				'wikibase-sitelinks-sitename-columnheading',
				'wikibase-sitelinks-sitename-columnheading-special',
				'wikibase-sitelinks-siteid-columnheading',
				'wikibase-sitelinks-link-columnheading',
				'wikibase-remove',
				'wikibase-propertyedittool-full',
				'wikibase-propertyedittool-counter-pending-tooltip',
				'wikibase-propertyedittool-counter-entrieslabel',
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
				'parentheses',
			)
		),

		'jquery.wikibase.toolbarcontroller' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/toolbar/toolbarcontroller.js',
				'jquery.wikibase/toolbar/toolbarcontroller.definitions.js',
			),
			'dependencies' => array(
				'jquery.wikibase.addtoolbar',
				'jquery.wikibase.edittoolbar',
				'jquery.wikibase.movetoolbar',
				'jquery.wikibase.removetoolbar',
			)
		),

		'jquery.wikibase.toolbarbase' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/toolbar/toolbarbase.js',
			),
			'dependencies' => array(
				'jquery.ui.widget',
				'jquery.wikibase.toolbar',
				'jquery.wikibase.toolbareditgroup',
			),
		),

		'jquery.wikibase.addtoolbar' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/toolbar/addtoolbar.js',
			),
			'dependencies' => array(
				'jquery.wikibase.toolbarbase',
			),
			'messages' => array(
				'wikibase-add'
			)
		),

		'jquery.wikibase.edittoolbar' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/toolbar/edittoolbar.js',
			),
			'dependencies' => array(
				'jquery.wikibase.toolbarbase',
				'jquery.wikibase.toolbareditgroup',
			)
		),

		'jquery.wikibase.movetoolbar' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/toolbar/movetoolbar.js',
			),
			'styles' => array(
				'jquery.wikibase/toolbar/themes/default/movetoolbar.css',
			),
			'dependencies' => array(
				'jquery.ui.widget',
				'jquery.wikibase.toolbarbase',
				'jquery.wikibase.toolbarbutton',
				'wikibase.templates',
			),
			'messages' => array(
				'wikibase-move-up',
				'wikibase-move-down',
			),
		),

		'jquery.wikibase.removetoolbar' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/toolbar/removetoolbar.js',
			),
			'dependencies' => array(
				'jquery.wikibase.toolbarbase',
			),
			'messages' => array(
				'wikibase-remove',
			),
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
				'jquery.ui.widget',
				'util.inherit',
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

		'jquery.wikibase.listview' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/jquery.wikibase.listview.js',
				'jquery.wikibase/jquery.wikibase.listview.ListItemAdapter.js'
			),
			'dependencies' => array(
				'jquery.NativeEventHandler',
				'jquery.ui.TemplatedWidget',
				'jquery.ui.widget',
			)
		),

		'jquery.wikibase.snaklistview' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/jquery.wikibase.snaklistview.js',
			),
			'dependencies' => array(
				'jquery.NativeEventHandler',
				'jquery.ui.TemplatedWidget',
				'jquery.ui.widget',
				'jquery.wikibase.listview',
				'jquery.wikibase.snakview',
				'wikibase.datamodel',
			),
			'messages' => array(
				'wikibase-claimview-snak-tooltip',
				'wikibase-claimview-snak-new-tooltip',
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
			'styles' => array(
				'jquery.wikibase/jquery.wikibase.snakview/themes/default/snakview.SnakTypeSelector.css',
			),
			'dependencies' => array(
				'jquery.eachchange',
				'jquery.NativeEventHandler',
				'util.inherit',
				'jquery.wikibase.entityselector',
				'wikibase.datamodel',
				'wikibase.AbstractedRepoApi',
				'wikibase.store.FetchedContent', // required for getting datatype from entityselector selected property
				'mediawiki.legacy.shared',
				'jquery.ui.position',
				'jquery.ui.TemplatedWidget',
				// valueviews for representing DataValues in snakview:
				'jquery.valueview.experts.StringValue',
				'jquery.valueview.experts.CommonsMediaType',
				'jquery.valueview.experts.wikibase.entityidvalue',
				'wikibase.formatters',
			),
			'messages' => array(
				'wikibase-snakview-property-input-placeholder',
				'wikibase-snakview-unsupportedsnaktype',
				'wikibase-snakview-choosesnaktype',
				'wikibase-snakview-variation-datavaluetypemismatch',
				'wikibase-snakview-variation-datavaluetypemismatch-details',
				'wikibase-snakview-variation-nonewvaluefordeletedproperty',
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
				'jquery.wikibase.snakview',
				'jquery.wikibase.snaklistview',
				'wikibase.AbstractedRepoApi',
				'jquery.wikibase.toolbarcontroller',
			),
			'messages' => array(
				'wikibase-addqualifier',
				'wikibase-claimview-snak-tooltip',
				'wikibase-claimview-snak-new-tooltip'
			)
		),

		'jquery.wikibase.referenceview' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/jquery.wikibase.referenceview.js',
			),
			'dependencies' => array(
				'jquery.wikibase.listview',
				'jquery.wikibase.snaklistview',
				'jquery.wikibase.toolbarcontroller',
				'wikibase.AbstractedRepoApi',
			)
		),

		'jquery.wikibase.statementview' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/jquery.wikibase.statementview.js',
				'jquery.wikibase/jquery.wikibase.statementview.RankSelector.js',
			),
			'styles' => array(
				'jquery.wikibase/themes/default/jquery.wikibase.statementview.RankSelector.css',
			),
			'dependencies' => array(
				'jquery.ui.position',
				'jquery.ui.toggler',
				'util.inherit',
				'jquery.wikibase.claimview',
				'jquery.wikibase.listview',
				'jquery.wikibase.referenceview',
				'jquery.wikibase.toolbarcontroller',
				'wikibase.AbstractedRepoApi',
				'wikibase.datamodel',
				'wikibase.utilities',
			),
			'messages' => array(
				'wikibase-statementview-rank-preferred',
				'wikibase-statementview-rank-normal',
				'wikibase-statementview-rank-deprecated',
				'wikibase-statementview-referencesheading-pendingcountersubject',
				'wikibase-statementview-referencesheading-pendingcountertooltip',
				'wikibase-addreference'
			)
		),

		'jquery.wikibase.claimlistview' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/jquery.wikibase.claimlistview.js'
			),
			'dependencies' => array(
				'jquery.ui.TemplatedWidget',
				'jquery.ui.widget',
				'jquery.wikibase.claimview',
				'jquery.wikibase.listview',
				'jquery.wikibase.statementview',
				'jquery.wikibase.toolbarcontroller',
				'wikibase',
				'wikibase.AbstractedRepoApi',
				'wikibase.datamodel',
				'wikibase.RepoApiError',
				'wikibase.templates',
				'wikibase.utilities.ClaimGuidGenerator',
			),
			'messages' => array(
				'wikibase-entity-property',
			),
		),

		'jquery.wikibase.claimgrouplistview' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/jquery.wikibase.claimgrouplistview.js'
			),
			'dependencies' => array(
				'jquery.ui.TemplatedWidget',
				'jquery.ui.widget',
				'jquery.wikibase.claimlistview',
				'jquery.wikibase.listview',
				'jquery.wikibase.toolbarcontroller',
				'wikibase',
				'wikibase.AbstractedRepoApi',
				'wikibase.templates',
				'wikibase.utilities'
			),
		),

		'jquery.wikibase.entityview' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/jquery.wikibase.entityview.js'
			),
			'dependencies' => array(
				'jquery.wikibase.statementview',
				'jquery.wikibase.claimlistview',
				'jquery.wikibase.claimgrouplistview',
				'jquery.wikibase.toolbarcontroller',
				'wikibase.templates'
			)
		),

		'jquery.wikibase.entityselector' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/jquery.wikibase.entityselector.js'
			),
			'styles' => array(
				'jquery.wikibase/themes/default/jquery.wikibase.entityselector.css'
			),
			'dependencies' => array(
				'jquery.autocompletestring',
				'jquery.eachchange',
				'jquery.ui.suggester',
				'jquery.ui.resizable',
				'jquery.ui.widget',
				'jquery.util.adaptlettercase',
			),
			'messages' => array(
				'wikibase-aliases-label',
				'wikibase-entityselector-more'
			)
		),

		'jquery.wikibase.claimgrouplabelscroll' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/jquery.wikibase.claimgrouplabelscroll.js'
			),
			'dependencies' => array(
				'jquery.ui.widget',
			),
		),

		// jQuery.valueview views for Wikibase specific DataValues/DataTypes
		'jquery.valueview.experts.wikibase' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.valueview.experts.wikibase/experts.wikibase.js',
			),
			'dependencies' => array(
				'mw.ext.valueView',
				'mw.ext.valueFormatters',
				'mw.ext.valueParsers',
				'wikibase.formatters',
				'wikibase.parsers',
			),
		),

		'jquery.valueview.experts.wikibase.entityidvalue' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.valueview.experts.wikibase/experts.wikibase.js',
				'jquery.valueview.experts.wikibase/experts.wikibase.EntityIdInput.js',
				'jquery.valueview.experts.wikibase/experts.wikibase.EntityIdValue.js',
			),
			'dependencies' => array(
				'jquery.valueview.BifidExpert',
				'jquery.valueview.experts.StaticDom',
				'jquery.valueview.experts.wikibase',
				'jquery.eachchange',
				'jquery.inputautoexpand',
				'wikibase.utilities',
				'wikibase.store.FetchedContent'
			),
			'messages' => array(
				'wikibase-entity-item',
			)
		),

		'jquery.wikibase.toolbarlabel' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/toolbar/toolbarlabel.js',
			),
			'styles' => array(
				'jquery.wikibase/toolbar/themes/default/toolbarlabel.css',
			),
			'dependencies' => array(
				'jquery.ui.widget',
				'wikibase.utilities',
			),
		),

		'jquery.wikibase.toolbarbutton' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/toolbar/toolbarbutton.js',
			),
			'styles' => array(
				'jquery.wikibase/toolbar/themes/default/toolbarbutton.css',
			),
			'dependencies' => array(
				'jquery.wikibase.toolbarlabel',
			),
		),

		'jquery.wikibase.toolbar' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/toolbar/toolbar.js',
			),
			'styles' => array(
				'jquery.wikibase/toolbar/themes/default/toolbar.css',
			),
			'dependencies' => array(
				'jquery.wikibase.toolbarbutton',
			),
		),

		'jquery.wikibase.toolbareditgroup' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/toolbar/toolbareditgroup.js',
			),
			'dependencies' => array(
				'jquery.ui.widget',
				'jquery.wikibase.toolbar',
				'jquery.wikibase.wbtooltip',
			),
		),

		'jquery.wikibase.wbtooltip' => $moduleTemplate + array(
			'scripts' => array(
				'jquery.wikibase/jquery.wikibase.wbtooltip.js',
			),
			'styles' => array(
				'jquery.wikibase/themes/default/jquery.wikibase.wbtooltip.css'
			),
			'dependencies' => array(
				'jquery.tipsy',
				'jquery.ui.toggler',
				'jquery.ui.widget',
				'wikibase.RepoApiError',
			),
			'messages' => array(
				'wikibase-tooltip-error-details',
			),
		),

	);

	if ( defined( 'ULS_VERSION' ) ) {
		$modules['wikibase']['dependencies'][] = 'jquery.uls.data';
		$modules['wikibase.sites']['dependencies'] = array( 'jquery.uls.data' );
		$modules['wikibase.ui.PropertyEditTool']['dependencies'][] = 'jquery.uls.data';
	}

	return $modules;
} );
// @codeCoverageIgnoreEnd
