<?php

namespace Wikibase;
use ResourceLoaderModule, ResourceLoaderContext;

/**
 * JavaScript variables needed to access the repo independent from the current
 * working wiki
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
 * @since 0.4
 *
 * @file
 * @ingroup WikibaseClient
 *
 * @licence GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 */
class RepoAccessModule extends ResourceLoaderModule {

	/**
	 * This one lets the client JavaScript know where it can find
	 * the API and the article path of the repo
	 * @see ResourceLoaderModule::getScript
	 *
	 * @since 0.4
	 *
	 * @param ResourceLoaderContext $context
	 *
	 * @return string
	 */
	public function getScript( ResourceLoaderContext $context ) {
		global $wgServer, $wgScriptPath, $wgArticlePath;

		$settings = Settings::singleton();

		if ( $settings->hasSetting( 'repoUrl' ) ) {
			// We're on a client (or at least the client configuration is available)
			$variables = array(
				'wbRepoUrl' => $settings->getSetting( 'repoUrl' ),
				'wbRepoScriptPath' => $settings->getSetting( 'repoScriptPath' ),
				'wbRepoArticlePath' => $settings->getSetting( 'repoArticlePath' )
			);
		} else {
			// Client configuration isn't available... just assume we're the repo
			$variables = array(
				'wbRepoUrl' => $wgServer,
				'wbRepoScriptPath' => $wgScriptPath,
				'wbRepoArticlePath' => $wgArticlePath
			);
		}

		return 'mediaWiki.config.set( ' . \FormatJson::encode( $variables ) . ' );';
	}
}
