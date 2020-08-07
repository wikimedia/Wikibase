<?php

namespace Wikibase\Lib;

use ExtensionRegistry;
use Hooks;
use MWException;

/**
 * WikibaseSettings is a static access point to Wikibase settings defined as global state
 * (typically in LocalSettings.php).
 *
 * @note WikibaseSettings is intended for internal use by bootstrapping code. Application service
 * logic should have individual settings injected, static entry points to application logic should
 * use top level factory methods such as WikibaseRepo::getSettings() and
 * WikibaseClient::getSettings().
 *
 * @todo Move this to a separate component.
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class WikibaseSettings {

	/**
	 * @return bool True if and only if the Wikibase repository component is enabled on this wiki.
	 */
	public static function isRepoEnabled() {
		return ExtensionRegistry::getInstance()->isLoaded( 'WikibaseRepository' );
	}

	/**
	 * @note This runs the WikibaseRepoEntityNamespaces hook to allow extensions to modify
	 *       the repo 'entityNamespaces' setting.
	 *
	 * @throws MWException
	 *
	 * @return SettingsArray
	 */
	public static function getRepoSettings() {
		global $wgWBRepoSettings;
		if ( !self::isRepoEnabled() ) {
			throw new MWException( 'Cannot access repo settings: Wikibase Repository component is not enabled!' );
		}

		$repoSettings = array_merge(
			require __DIR__ . '/../config/WikibaseLib.default.php',
			require __DIR__ . '/../../repo/config/Wikibase.default.php'
		);
		// Hack to make a proper merge strategy for these configs in case they are being overriden by sysadmins
		// More info T257447
		// TODO: This should move to a proper mediawiki config handler: T258658
		$overrideArrays = [ 'entityDataFormats' ];
		$twoDArrayMerge = [ 'string-limits', 'pagePropertiesRdf' ];
		$falseMeansRemove = [ 'urlSchemes', 'canonicalLanguageCodes', 'globeUris' ];

		$settings = self::mergeSettings(
			$repoSettings,
			$wgWBRepoSettings ?? [],
			$overrideArrays,
			$twoDArrayMerge,
			$falseMeansRemove
		);

		$entityNamespaces = self::buildEntityNamespaceConfigurations( $settings );

		Hooks::run( 'WikibaseRepoEntityNamespaces', [ &$entityNamespaces ] );

		self::applyEntityNamespacesToSettings( $settings, $entityNamespaces );
		return $settings;
	}

	/**
	 * @return bool True if and only if the Wikibase client component is enabled on this wiki.
	 */
	public static function isClientEnabled() {
		return ExtensionRegistry::getInstance()->isLoaded( 'WikibaseClient' );
	}

	/**
	 * @throws MWException
	 *
	 * @return SettingsArray
	 */
	public static function getClientSettings() {
		global $wgWBClientSettings;

		if ( !self::isClientEnabled() ) {
			throw new MWException( 'Cannot access client settings: Wikibase Client component is not enabled!' );
		}

		$clientSettings = array_merge(
			require __DIR__ . '/../config/WikibaseLib.default.php',
			require __DIR__ . '/../../client/config/WikibaseClient.default.php'
		);

		$settings = self::mergeSettings( $clientSettings, $wgWBClientSettings ?? [] );

		$entityNamespaces = self::buildEntityNamespaceConfigurations( $settings );

		Hooks::run( 'WikibaseClientEntityNamespaces', [ &$entityNamespaces ] );

		self::applyEntityNamespacesToSettings( $settings, $entityNamespaces );

		return $settings;
	}

	/**
	 * Merge two arrays of default and custom settings,
	 * so that it looks like the custom settings were added on top of the default settings.
	 *
	 * Originally, Wikibase extensions were loaded and configured somewhat like this:
	 *
	 *     require_once "$IP/extensions/Wikibase/client/WikibaseClient.php";
	 *     $wgWBClientSettings['repoUrl'] = 'https://pool.my.wiki';
	 *
	 * Here, $wgWBClientSettings would be initialized by WikibaseClient.php.
	 * However, with the move to extension registration and wfLoadExtension(),
	 * this is no longer possible, and $wgWBClientSettings will start out empty.
	 * This method returns an array that looks like the custom settings
	 * were added on top of existing default settings as above,
	 * even though the default settings were in fact only loaded later.
	 *
	 * @param array $defaultSettings The default settings loaded from some other config file.
	 * @param array $customSettings The custom settings from a configuration global.
	 * @param string[] $overrideArrays
	 * @param string[] $twoDArrayMerge
	 * @param string[] $falseMeansRemove
	 * @return SettingsArray The merged settings.
	 */
	private static function mergeSettings(
		array $defaultSettings,
		array $customSettings,
		array $overrideArrays = [],
		array $twoDArrayMerge = [],
		array $falseMeansRemove = []
	): SettingsArray {
		foreach ( $customSettings as $key => $value ) {
			$defaultValue = $defaultSettings[$key] ?? [];
			if ( is_array( $value ) && is_array( $defaultValue ) ) {
				$defaultSettings[$key] = self::mergeComplexArrays(
					$key,
					$value,
					$defaultValue,
					$twoDArrayMerge,
					$overrideArrays,
					$falseMeansRemove
				);
			} else {
				$defaultSettings[$key] = $value;
			}
		}

		return new SettingsArray( $defaultSettings );
	}

	private static function mergeComplexArrays(
		string $key,
		array $value,
		array $defaultValue,
		array $twoDArrayMerge,
		array $overrideArrays,
		array $falseMeansRemove
	): array {
		if ( in_array( $key, $twoDArrayMerge ) ) {
			return wfArrayPlus2d( $value, $defaultValue );
		}
		if ( in_array( $key, $overrideArrays ) ) {
			return $value;
		}
		if ( in_array( $key, $falseMeansRemove ) ) {
			$result = array_merge( $defaultValue, $value );
			foreach ( $result as $valueKey => $valueValue ) {
				if ( $valueValue === false ) {
					unset( $result[$valueKey] );
					$index = array_search( $valueKey, $result );
					if ( $index !== false ) {
						unset( $result[$index] );
					}
				}
			}

			// Keep the non-numeric keys but drop the numeric ones
			return array_merge( $result );
		}
		return array_merge( $defaultValue, $value );
	}

	/**
	 * @throws MWException in case of a misconfiguration
	 * @return int[] An array mapping entity type identifiers to namespace numbers.
	 */
	private static function buildEntityNamespaceConfigurations( SettingsArray $settings ) {
		if ( !$settings->hasSetting( 'repositories' ) && !$settings->hasSetting( 'entityNamespaces' ) ) {
			throw new MWException( 'Wikibase: Incomplete configuration: '
				. 'The \'entityNamespaces\' setting has to be set to an '
				. 'array mapping entity types to namespace IDs. '
				. 'See Wikibase.example.php for details and examples.' );
		}

		$namespaces = $settings->hasSetting( 'entityNamespaces' )
			? $settings->getSetting( 'entityNamespaces' )
			: self::getEntityNamespacesFromRepositorySettings( $settings->getSetting( 'repositories' ) );

		return $namespaces;
	}

	private static function getEntityNamespacesFromRepositorySettings( array $repositorySettings ) {
		return array_reduce(
			$repositorySettings,
			function ( array $result, array $repoSettings ) {
				return array_merge( $result, $repoSettings['entityNamespaces'] );
			},
			[]
		);
	}

	private static function applyEntityNamespacesToSettings( SettingsArray $settings, array $entityNamespaces ) {
		if ( $settings->hasSetting( 'entityNamespaces' ) ) {
			$settings->setSetting( 'entityNamespaces', $entityNamespaces );
			return;
		}

		$repositorySettings = $settings->getSetting( 'repositories' );
		$namespacesDefinedForRepositories = self::getEntityNamespacesFromRepositorySettings( $repositorySettings );

		$namespacesInNoRepository = array_diff_key( $entityNamespaces, $namespacesDefinedForRepositories );

		if ( $namespacesInNoRepository ) {
			$repositorySettings['']['entityNamespaces'] += $namespacesInNoRepository;
			$settings->setSetting( 'repositories', $repositorySettings );
		}
	}

}
