<?php

namespace Wikibase;
use MWException, SettingsBase;

/**
 * File defining the settings for the Wikibase extension.
 * More info can be found at https://www.mediawiki.org/wiki/Extension:Wikibase#Settings
 *
 * NOTICE:
 * Changing one of these settings can be done by assigning to $wgWBSettings,
 * AFTER the inclusion of the extension itself.
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
 */
class Settings extends SettingsBase {

	/**
	 * @see SettingsBase::getSetSettings
	 *
	 * @since 0.1
	 *
	 * @return array
	 */
	protected function getSetSettings() {
		return $GLOBALS['wgWBSettings'];
	}

	/**
	 * @see SettingsBase::getDefaultSettings
	 *
	 * @since 0.1
	 *
	 * @return array
	 */
	protected function getDefaultSettings() {
		$settings = array();

		// allow extensions that use WikidataLib to register mode defaults
		wfRunHooks( 'WikibaseDefaultSettings', array( &$settings ) );

		return $settings;
	}

}
