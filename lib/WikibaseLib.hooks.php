<?php

namespace Wikibase;
use DatabaseUpdater;

/**
 * File defining the hook handlers for the WikibaseLib extension.
 *
 * @since 0.1
 *
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
final class LibHooks {

	/**
	 * Adds default settings.
	 * Setting name (string) => setting value (mixed)
	 *
	 * @param array &$settings
	 *
	 * @since 0.1
	 *
	 * @return boolean
	 */
	public static function onWikibaseDefaultSettings( array &$settings ) {
		$settings = array_merge(
			$settings,
			array(
				'pollDefaultInterval' => 1,
				'pollDefaultLimit' => 100,
				'pollContinueInterval' => 0,

				'itemPrefix' => 'q',
				'propertyPrefix' => 'p',
				'queryPrefix' => 'y', // TODO: find a more suiting prefix, perhaps use 'q' and use 'i' for items then

				'siteLinkGroup' => 'wikipedia',

				'changesDatabase' => false, // local by default. Set to something LBFactory understands.

				'changeHandlers' => array(
					'wikibase-item~add' => 'Wikibase\EntityCreation',
					'wikibase-property~add' => 'Wikibase\EntityCreation',
					'wikibase-query~add' => 'Wikibase\EntityCreation',

					'wikibase-item~update' => 'Wikibase\EntityUpdate',
					'wikibase-property~update' => 'Wikibase\EntityUpdate',
					'wikibase-query~update' => 'Wikibase\EntityUpdate',

					'wikibase-item~remove' => 'Wikibase\EntityDeletion',
					'wikibase-property~remove' => 'Wikibase\EntityDeletion',
					'wikibase-query~remove' => 'Wikibase\EntityDeletion',

					'wikibase-item~refresh' => 'Wikibase\EntityRefresh',
					'wikibase-property~refresh' => 'Wikibase\EntityRefresh',
					'wikibase-query~refresh' => 'Wikibase\EntityRefresh',

					'wikibase-item~restore' => 'Wikibase\EntityRestore',
					'wikibase-property~restore' => 'Wikibase\EntityRestore',
					'wikibase-query~restore' => 'Wikibase\EntityRestore',
				),
				'dataTypes' => array(
					'wikibase-item',
					'commonsMedia',
					//'string',
				),
				'entityNamespaces' => array()
			)
		);

		return true;
	}

	/**
	 * Schema update to set up the needed database tables.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 *
	 * @since 0.1
	 *
	 * @param DatabaseUpdater $updater
	 *
	 * @return boolean
	 */
	public static function onSchemaUpdate( DatabaseUpdater $updater ) {
		$type = $updater->getDB()->getType();

		if ( $type === 'mysql' || $type === 'sqlite' /* || $type === 'postgres' */ ) {
			// TODO: move to core
			$updater->addExtensionField(
				'sites',
				'site_source',
				__DIR__ . '/sql/DropSites.sql'
			);

			$updater->addExtensionTable(
				'site_identifiers',
				__DIR__ . '/sql/AddSitesTable.sql'
			);

			$updater->addExtensionUpdate( array( '\Wikibase\Utils::insertDefaultSites' ) );
		}
		else {
			wfWarn( "Database type '$type' is not supported by Wikibase." );
		}

		return true;
	}

	/**
	 * Hook to add PHPUnit test cases.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UnitTestsList
	 *
	 * @since 0.2 (as registerUnitTests in 0.1)
	 *
	 * @param array $files
	 *
	 * @return boolean
	 */
	public static function registerPhpUnitTests( array &$files ) {
		// @codeCoverageIgnoreStart
		$testFiles = array(
			'changes/DiffChange',
			'changes/EntityCreation',
			'changes/EntityDeletion',
			'changes/EntityRefresh',
			'changes/EntityUpdate',

			'claim/ClaimAggregate',
			'claim/ClaimListAccess',
			'claim/ClaimList',
			'claim/ClaimObject',

			'entity/EntityFactory',

			'item/ItemDiff',
			'item/ItemMultilangTexts',
			'item/ItemNewEmpty',
			'item/ItemNewFromArray',
			'item/ItemObject',

			'property/PropertyObject',

			'query/QueryObject',

			'reference/ReferenceList',
			'reference/ReferenceObject',

			'snak/PropertyValueSnak',
			'snak/SnakList',
			'snak/Snak',

			'statement/StatementAggregate',
			'statement/StatementListAccess',
			'statement/StatementList',
			'statement/StatementObject',

			'store/SiteLinkLookup',

			'ByPropertyIdArray',
			'ChangeNotifier',
			'ChangeHandler',
			'ChangesTable',
			'HashableObjectStorage',
			'LibHooks',
			'MapValueHasher',
			'SiteLink',
			'Utils',
		);

		// Test compat
		if ( !array_key_exists( 'SettingsBase', $GLOBALS['wgAutoloadLocalClasses'] ) ) {
			$testFiles[] = 'SettingsBase';
		}

		foreach ( $testFiles as $file ) {
			$files[] = __DIR__ . '/tests/phpunit/' . $file . 'Test.php';
		}

		return true;
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Add new javascript testing modules. This is called after the addition of MediaWiki core test suites.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderTestModules
	 *
	 * @since 0.2 (in repo as RepoHooks::onResourceLoaderTestModules in 0.1)
	 *
	 * @param array &$testModules
	 * @param \ResourceLoader &$resourceLoader
	 *
	 * @return boolean
	 */
	public static function registerQUnitTests( array &$testModules, \ResourceLoader &$resourceLoader ) {
		$testModules['qunit']['wikibase.tests'] = array(
			'scripts' => array(
				'tests/qunit/wikibase.tests.js',
				'tests/qunit/wikibase.Site.tests.js',
				'tests/qunit/wikibase.ui.AliasesEditTool.tests.js',
				'tests/qunit/wikibase.ui.DescriptionEditTool.tests.js',
				'tests/qunit/wikibase.ui.LabelEditTool.tests.js',
				'tests/qunit/wikibase.ui.SiteLinksEditTool.tests.js',
				'tests/qunit/wikibase.ui.PropertyEditTool.tests.js',
				'tests/qunit/wikibase.ui.PropertyEditTool.EditableAliases.tests.js',
				'tests/qunit/wikibase.ui.PropertyEditTool.EditableDescription.tests.js',
				'tests/qunit/wikibase.ui.PropertyEditTool.EditableLabel.tests.js',
				'tests/qunit/wikibase.ui.PropertyEditTool.EditableSiteLink.tests.js',
				'tests/qunit/wikibase.ui.PropertyEditTool.EditableValue.tests.js',
				'tests/qunit/wikibase.ui.PropertyEditTool.EditableValue.AutocompleteInterface.tests.js',
				'tests/qunit/wikibase.ui.PropertyEditTool.EditableValue.Interface.tests.js',
				'tests/qunit/wikibase.ui.PropertyEditTool.EditableValue.SiteIdInterface.tests.js',
				'tests/qunit/wikibase.ui.PropertyEditTool.EditableValue.SitePageInterface.tests.js',
				'tests/qunit/wikibase.ui.PropertyEditTool.EditableValue.ListInterface.tests.js',
				'tests/qunit/wikibase.ui.Toolbar.tests.js',
				'tests/qunit/wikibase.ui.Toolbar.EditGroup.tests.js',
				'tests/qunit/wikibase.ui.Toolbar.Group.tests.js',
				'tests/qunit/wikibase.ui.Toolbar.Label.tests.js',
				'tests/qunit/wikibase.ui.Toolbar.Button.tests.js',
				'tests/qunit/wikibase.ui.Tooltip.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.inherit.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.newExtension.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.ObservableObject.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.ui.StatableObject.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.jQuery.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.jQuery.PersistentPromisor.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.jQuery.NativeEventHandler.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.jQuery.ui.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.jQuery.ui.inputAutoExpand.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.jQuery.ui.tagadata.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.jQuery.ui.eachchange.tests.js',
				'tests/qunit/jquery.ui/jquery.ui.wikibaseAutocomplete.tests.js'
			),
			'dependencies' => array(
				'wikibase.tests.qunit.testrunner',
				'wikibase',
				'wikibase.utilities',
				'wikibase.utilities.jQuery',
				'wikibase.ui.Toolbar',
				'wikibase.ui.PropertyEditTool',
				'wikibase.jquery.ui'
			),
			'localBasePath' => __DIR__,
			'remoteExtPath' => 'Wikibase/lib',
		);

		return true;
	}
}
