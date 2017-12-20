<?php

namespace Wikibase\Lib;

/**
 * GOAT
 */
class Registrar {

	public static function registerExtension() {
		global $wgAutoloadClasses, $wgHooks, $wgResourceModules;

		if ( defined( 'WBL_VERSION' ) ) {
			// Do not initialize more than once.
			return 1;
		}

		define( 'WBL_VERSION', '0.5 alpha' );

		// This is the path to the autoloader generated by composer in case of a composer install.
		if ( is_readable( __DIR__ . '/../vendor/autoload.php' ) ) {
			require_once __DIR__ . '/../vendor/autoload.php';
		}

		// Nasty hack: some lib's tests rely on ItemContent class defined in Repo! Load it in client-only mode to have tests pass
		if ( !defined( 'WB_VERSION' ) && defined( 'MW_PHPUNIT_TEST' ) ) {
			$wgAutoloadClasses['Wikibase\\ItemContent'] = __DIR__ . '/../repo/includes/Content/ItemContent.php';
			$wgAutoloadClasses['Wikibase\\EntityContent'] = __DIR__ . '/../repo/includes/Content/EntityContent.php';
			$wgAutoloadClasses['Wikibase\\Repo\\Content\\EntityContentDiff'] = __DIR__ . '/../repo/includes/Content/EntityContentDiff.php';
		}

		define( 'SUMMARY_MAX_LENGTH', 250 );

		// Hooks
		$wgHooks['UnitTestsList'][] = 'Wikibase\LibHooks::registerPhpUnitTests';
		$wgHooks['ResourceLoaderTestModules'][] = 'Wikibase\LibHooks::registerQUnitTests';
		$wgHooks['ResourceLoaderRegisterModules'][] = 'Wikibase\LibHooks::onResourceLoaderRegisterModules';

		/**
		 * Called when generating the extensions credits, use this to change the tables headers.
		 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ExtensionTypes
		 *
		 * @param array &$extensionTypes
		 *
		 * @return boolean
		 */
		$wgHooks['ExtensionTypes'][] = function( array &$extensionTypes ) {
			// @codeCoverageIgnoreStart
			$extensionTypes['wikibase'] = wfMessage( 'version-wikibase' )->text();

			return true;
			// @codeCoverageIgnoreEnd
		};

		// Resource Loader Modules:
		$wgResourceModules = array_merge(
			$wgResourceModules,
			include __DIR__ . '/resources/Resources.php'
		);
	}

}
