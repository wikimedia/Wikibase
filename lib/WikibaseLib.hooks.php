<?php

namespace Wikibase;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * File defining the hook handlers for the WikibaseLib extension.
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Werner < daniel.a.r.werner@gmail.com >
 * @author H. Snater < mediawiki@snater.com >
 */
final class LibHooks {

	/**
	 * Hook to add PHPUnit test cases.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UnitTestsList
	 *
	 * @since 0.2
	 *
	 * @param string[] $files
	 *
	 * @return boolean
	 */
	public static function registerPhpUnitTests( array &$files ) {
		// @codeCoverageIgnoreStart
		$directoryIterator = new RecursiveDirectoryIterator( __DIR__ . '/tests/phpunit/' );

		/**
		 * @var SplFileInfo $fileInfo
		 */
		foreach ( new RecursiveIteratorIterator( $directoryIterator ) as $fileInfo ) {
			if ( substr( $fileInfo->getFilename(), -8 ) === 'Test.php' ) {
				$files[] = $fileInfo->getPathname();
			}
		}

		return true;
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Add new javascript testing modules. This is called after the addition of MediaWiki core test suites.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderTestModules
	 *
	 * TODO: Move into a file with only this definition.
	 *
	 * @since 0.2 (in repo as RepoHooks::onResourceLoaderTestModules in 0.1)
	 *
	 * @param array &$testModules
	 * @param \ResourceLoader &$resourceLoader
	 *
	 * @return boolean
	 */
	public static function registerQUnitTests( array &$testModules, \ResourceLoader &$resourceLoader ) {
		$moduleBase = array(
			'localBasePath' => __DIR__,
			'remoteExtPath' => 'Wikibase/lib',
		);

		// TODO: Split into test modules per QUnit module.
		$testModules['qunit']['wikibase.tests'] = $moduleBase + array(
			'scripts' => array(
				'tests/qunit/templates.tests.js',
				'tests/qunit/wikibase.tests.js',

				'tests/qunit/parsers/EntityIdParser.tests.js',

				'tests/qunit/wikibase.dataTypes/wikibase.dataTypes.tests.js',

				'tests/qunit/wikibase.datamodel/Wikibase.claim.tests.js',
				'tests/qunit/wikibase.datamodel/Wikibase.reference.tests.js',
				'tests/qunit/wikibase.datamodel/Wikibase.snak.tests.js',
				'tests/qunit/wikibase.datamodel/Wikibase.SnakList.tests.js',
				'tests/qunit/wikibase.datamodel/wikibase.Statement.tests.js',
				'tests/qunit/wikibase.datamodel/datamodel.Entity.tests.js',
				'tests/qunit/wikibase.datamodel/datamodel.Item.tests.js',
				'tests/qunit/wikibase.datamodel/datamodel.Property.tests.js',

				'tests/qunit/wikibase.Site.tests.js',

				'tests/qunit/wikibase.RepoApi/wikibase.RepoApi.tests.js',
				'tests/qunit/wikibase.RepoApi/wikibase.RepoApiError.tests.js',

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

				'tests/qunit/wikibase.utilities/wikibase.utilities.ClaimGuidGenerator.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.GuidGenerator.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.newExtension.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.ObservableObject.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.ui.StatableObject.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.jQuery.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.jQuery.PersistentPromisor.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.jQuery.ui.tagadata.tests.js',

				'tests/qunit/jquery.wikibase/jquery.wikibase.entityselector.tests.js',
				'tests/qunit/jquery.wikibase/jquery.wikibase.siteselector.tests.js',
				'tests/qunit/jquery.wikibase/toolbar/toolbarbutton.tests.js',
				'tests/qunit/jquery.wikibase/toolbar/toolbarlabel.tests.js',

				'tests/qunit/jquery.wikibase/toolbar/toolbar.tests.js',
				'tests/qunit/jquery.wikibase/toolbar/toolbareditgroup.tests.js',
			),
			'dependencies' => array(
				'wikibase.tests.qunit.testrunner',
				'wikibase',
				'wikibase.AbstractedRepoApi',
				'wikibase.parsers',
				'wikibase.store',
				'wikibase.utilities',
				'wikibase.utilities.ClaimGuidGenerator',
				'wikibase.utilities.GuidGenerator',
				'wikibase.utilities.jQuery',
				'wikibase.ui.PropertyEditTool',
				'jquery.ui.suggester',
				'jquery.wikibase.entityselector',
				'jquery.wikibase.toolbar',
				'jquery.wikibase.toolbareditgroup',
				'jquery.nativeEventHandler',
				'jquery.client',
				'jquery.eachchange',
			)
		);

		$testModules['qunit']['jquery.wikibase.claimgrouplabelscroll.tests'] = $moduleBase + array(
			'scripts' => array(
				'tests/qunit/jquery.wikibase/jquery.wikibase.claimgrouplabelscroll.tests.js',
			),
			'dependencies' => array(
				'jquery.wikibase.claimgrouplabelscroll'
			),
		);

		$testModules['qunit']['jquery.wikibase.listview.tests'] = $moduleBase + array(
			'scripts' => array(
				'tests/qunit/jquery.wikibase/jquery.wikibase.listview.tests.js',
			),
			'dependencies' => array(
				'jquery.wikibase.listview',
			),
		);

		$testModules['qunit']['jquery.wikibase.movetoolbar.tests'] = $moduleBase + array(
			'scripts' => array(
				'tests/qunit/jquery.wikibase/toolbar/movetoolbar.tests.js',
			),
			'dependencies' => array(
				'jquery.wikibase.movetoolbar',
				'jquery.wikibase.listview',
			),
		);

		$testModules['qunit']['jquery.wikibase.statementview.tests'] = $moduleBase + array(
				'scripts' => array(
					'tests/qunit/jquery.wikibase/jquery.wikibase.statementview.RankSelector.tests.js',
				),
				'dependencies' => array(
					'jquery.wikibase.statementview',
				),
			);

		$testModules['qunit']['jquery.wikibase.snaklistview.tests'] = $moduleBase + array(
			'scripts' => array(
				'tests/qunit/jquery.wikibase/jquery.wikibase.snaklistview.tests.js',
			),
			'dependencies' => array(
				'jquery.wikibase.snaklistview',
			),
		);

		$testModules['qunit']['jquery.wikibase.wbtooltip.tests'] = $moduleBase + array(
			'scripts' => array(
				'tests/qunit/jquery.wikibase/jquery.wikibase.wbtooltip.tests.js',
			),
			'dependencies' => array(
				'jquery.wikibase.wbtooltip',
			),
		);

		return true;
	}
}
