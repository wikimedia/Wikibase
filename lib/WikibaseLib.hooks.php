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
				'clientPageArgs' => array(
					'action' => 'query',
					'prop' => 'info',
					'redirects' => true,
					'converttitles' => true,
					'format' => 'json',
					//TODO: This should REALLY be handled somehow as without it we could run into lots of trouble
					//'maxage' => 5, // filter down repeated clicks, don't let clicky folks loose to fast
					//'smaxage' => 15, // give the proxy some time, don't let clicky folks loose to fast
					//'maxlag' => 100, // time to wait on a lagging server, hanging on for 100 sec is very aggressive
				),
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

		if ( $type === 'mysql' || $type === 'sqlite' ) {
			$updater->addExtensionTable(
				'wb_changes',
				dirname( __FILE__ ) . '/sql/WikibaseLib.sql'
			);

			// TODO: move to core
			$updater->addExtensionTable(
				'sites',
				dirname( __FILE__ ) . '/sql/AddSitesTable.sql'
			);

			// TODO: move to core
			$updater->addExtensionField(
				'langlinks',
				'll_local',
				dirname( __FILE__ ) . '/sql/AddLocalLanglinksField.sql'
			);

			$updater->addExtensionField(
				'sites',
				'site_link_navigation',
				dirname( __FILE__ ) . '/sql/IndexSitesTable.sql'
			);

			$updater->addExtensionField(
				'sites',
				'site_language',
				dirname( __FILE__ ) . '/sql/MakeSitesTableMoarAwesome.sql'
			);

			$updater->addExtensionUpdate( array( '\Wikibase\Utils::insertDefaultSites' ) );
		}
		elseif ( $type === 'postgres' ) {
			$updater->addExtensionTable(
				'wb_changes',
				dirname( __FILE__ ) . '/sql/WikibaseLib.pg.sql'
			);

			//$updater->addExtensionUpdate( array( '\Wikibase\Utils::insertDefaultSites' ) );
		}
		else {
			// TODO
		}

		return true;
	}

	/**
	 * Hook to add PHPUnit test cases.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UnitTestsList
	 *
	 * @since 0.1
	 *
	 * @param array $files
	 *
	 * @return boolean
	 */
	public static function registerUnitTests( array &$files ) {
		$testFiles = array(
			'ChangeNotifier',
			'ChangeHandler',
			'Changes',
			'ItemMultilangTexts',
			'ItemNewEmpty',
			'ItemNewFromArray',
			'ItemObject',
			'LibHooks',
			'MediaWikiSite',
			'PropertyObject',
			'QueryObject',
			'SiteConfigObject',
			'SiteLink',
			'SiteList',
			'SiteRow',
			'MediaWikiSite',
			'Sites',
			'Utils',

			'changes/DiffChange',
			'changes/ItemChange',
		);

		// Test compat
		if ( !array_key_exists( 'SettingsBase', $GLOBALS['wgAutoloadLocalClasses'] ) ) {
			$testFiles[] = 'SettingsBase';
		}

		foreach ( $testFiles as $file ) {
			$files[] = dirname( __FILE__ ) . '/tests/phpunit/' . $file . 'Test.php';
		}

		return true;
	}

}
