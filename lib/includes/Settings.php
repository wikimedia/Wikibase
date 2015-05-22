<?php

namespace Wikibase;

/**
 * @deprecated
 *
 * Each component should manage its own settings,
 * and such settings should be defined in their own configuration.
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 */
final class Settings extends SettingsArray {

	/**
	 * @deprecated
	 *
	 * @return Settings
	 */
	public static function singleton() {
		static $instance = null;

		if ( $instance === null ) {
			$instance = new self();
			$instance->initFromGlobals();
		}

		return $instance;
	}

	/**
	 * Initializes this Settings object from the global configuration variables.
	 * Default settings are loaded from the appropriate files.
	 * The hook WikibaseDefaultSettings can be used to manipulate the defaults.
	 */
	private function initFromGlobals() {
		$settings = array();

		//NOTE: Repo overrides client. This is important especially for
		//      settings initialized by WikibaseLib.

		if ( defined( 'WBC_VERSION' ) ) {
			$settings = array_merge( $settings, $GLOBALS['wgWBClientSettings'] );
		}

		if ( defined( 'WB_VERSION' ) ) {
			$settings = array_merge( $settings, $GLOBALS['wgWBRepoSettings'] );
		}

		// store
		foreach ( $settings as $key => $value ) {
			$this[$key] = $value;
		}
	}

}
