<?php

namespace Wikibase;

/**
 * @deprecated
 *
 * Each component should manage its own settings,
 * and such settings should be defined in their own configuration.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @since 0.1
 *
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 */
class Settings extends SettingsArray {

	/**
	 * @see Settings::singleton
	 *
	 * @since 0.1
	 *
	 * @return Settings
	 */
	public static function singleton() {
		static $instance = null;

		if ( $instance === null ) {
			$settings = array();

			// load appropriate defaults -------------------
			if ( defined( 'WBL_VERSION' ) ) {
				$settings = array_merge( $settings, self::loadDefaults( WBL_DIR . '/config/WikibaseLib.default.php' ) );
			}

			if ( defined( 'WB_VERSION' ) ) {
				$settings = array_merge( $settings, self::loadDefaults( WB_DIR . '/config/Wikibase.default.php' ) );
			}

			if ( defined( 'WBC_VERSION' ) ) {
				$settings = array_merge( $settings, self::loadDefaults( WBC_DIR . '/config/WikibaseClient.default.php' ) );
			}

			// merge appropriate settings -------------------
			if ( defined( 'WBL_VERSION' ) ) {
				$settings = array_merge( $settings, $GLOBALS['wgWBSettings'] );
			}

			if ( defined( 'WB_VERSION' ) ) {
				$settings = array_merge( $settings, $GLOBALS['wgWBRepoSettings'] );
			}

			if ( defined( 'WBC_VERSION' ) ) {
				$settings = array_merge( $settings, $GLOBALS['wgWBClientSettings'] );
			}

			$instance = new static( $settings );
		}

		return $instance;
	}

	protected static function loadDefaults( $path ) {
		$defaults = include( $path );

		if ( !is_array( $defaults ) ) {
			throw new \MWException( "Defaults file not found: $path" );
		}

		return $defaults;
	}

	/**
	 * Shortcut to ::singleton()->getSetting
	 *
	 * @since 0.1
	 *
	 * @param string $settingName
	 *
	 * @return mixed
	 */
	public static function get( $settingName ) {
		return static::singleton()->offsetGet( $settingName );
	}

}
