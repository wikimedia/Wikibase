<?php

namespace Wikibase;
use Sanitizer, UtfNormal, Language;

/**
 * Utility functions for Wikibase.
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
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Tobias Gritschacher
 * @author Jens Ohlig < jens.ohlig@wikimedia.de >
 */
final class Utils {

	/**
	 * Returns a list of language codes that Wikibase supports,
	 * ie the languages that a label or description can be in.
	 *
	 * @since 0.1
	 *
	 * @return array
	 */
	public static function getLanguageCodes() {
		static $languageCodes = null;

		if ( is_null( $languageCodes ) ) {
			$languageCodes = array_keys( \Language::fetchLanguageNames() );
		}

		return $languageCodes;
	}

	/**
	 * @see \Language::fetchLanguageName()
	 *
	 * @since 0.1
	 *
	 * @param string $languageCode
	 *
	 * @return string
	 */
	public static function fetchLanguageName( $languageCode ) {
		$languageName = \Language::fetchLanguageName( str_replace( '_', '-', $languageCode ) );
		if ( $languageName == '' ) {
			$languageName = $languageCode;
		}
		return $languageName;
	}

	/**
	 * Inserts some sites into the sites table, if the sites table is currently empty.
	 * Called when update.php is run. The initial sites are loaded from https://meta.wikimedia.org.
	 *
	 * @param \DatabaseUpdater $updater database updater. Not used. Present to be compatible with DatabaseUpdater::addExtensionUpdate
	 *
	 * @throws \MWException if an error occurs.
	 * @since 0.1
	 */
	public static function insertDefaultSites( $updater = null ) {
		if ( \SitesTable::singleton()->count() > 0 ) {
			return;
		}

		self::insertSitesFrom( 'https://meta.wikimedia.org/w/api.php' );
	}

	/**
	 * Inserts sites from another wiki into the sites table. The other wiki must run the
	 * WikiMatrix extension.
	 *
	 * @note This should move into core, together with the populateSitesTable.php script.
	 *
	 * @param String           $url     The URL of the API to fetch the sites from.
	 *                         Defaults to 'https://meta.wikimedia.org/w/api.php'
	 *
	 * @param String|bool      $stripProtocol Causes any leading http or https to be stripped from URLs, forcing
	 *                         the remote sites to be references in a protocol-relative way.
	 *
	 * @throws \MWException if an error occurs.
	 * @since 0.1
	 */
	public static function insertSitesFrom( $url, $stripProtocol = false ) {

		// No sites present yet, fetching from api to populate sites table

		$url .= '?action=sitematrix&format=json';

		//NOTE: the raiseException option needs change Iad3995a6 to be merged, otherwise it is ignored.
		$json = \Http::get( $url, 'default', array( 'raiseException' => true ) );

		if ( !$json ) {
			throw new \MWException( "Got no data from $url" );
		}

		$languages = \FormatJson::decode(
			$json,
			true
		);

		if ( !is_array( $languages ) ) {
			throw new \MWException( "Failed to parse JSON from $url" );
		}

		$groupMap = array(
			'wiki' => 'wikipedia',
			'wiktionary' => 'wiktionary',
			'wikibooks' => 'wikibooks',
			'wikiquote' => 'wikiquote',
			'wikisource' => 'wikisource',
			'wikiversity' => 'wikiversity',
			'wikivoyage' => 'wikivoyage',
			'wikinews' => 'wikinews',
		);

		$dbw = wfGetDB( DB_MASTER );
		$doTrx = ( $dbw->trxLevel() === 0 );

		if ( $doTrx ) {
			$dbw->begin();
		}

		// Inserting obtained sites...
		foreach ( $languages['sitematrix'] as $language ) {
			if ( is_array( $language ) && array_key_exists( 'code', $language ) && array_key_exists( 'site', $language ) ) {
				$languageCode = $language['code'];

				foreach ( $language['site'] as $siteData ) {
					$sites = \Sites::singleton();
					$site = \Sites::singleton()->getSite( $siteData['dbname'] );

					if ( !$site ) {
						$site = \MediaWikiSite::newFromGlobalId( $siteData['dbname'] );
					}

					$site->setGroup( $groupMap[$siteData['code']] );
					$site->setLanguageCode( $languageCode );

					$localId = $siteData['code'] === 'wiki' ? $languageCode : $siteData['dbname'];
					$site->addInterwikiId( $localId );
					$site->addNavigationId( $localId );

					$url = $siteData['url'];

					if ( $stripProtocol === 'stripProtocol' ) {
						$url = preg_replace( '@^https?:@', '', $url );
					}

					$site->setFilePath( $url . '/w/$1' );
					$site->setPagePath( $url . '/wiki/$1' );

					$site->save();
				}
			}
		}

		if ( $doTrx ) {
			$dbw->commit();
		}

		\Sites::singleton()->getSites( false ); // re-cache
	}

	/**
	 * Trim initial and trailing whitespace, and compress internal ones.
	 *
	 * @since 0.1
	 *
	 * @param string $inputString The actual string to process.
	 *
	 * @return string where whitespace possibly are removed.
	 */
	static public function squashWhitespace( $inputString ) {
		return preg_replace( '/(\s+)/', ' ', preg_replace( '/(^\s+|\s+$)/', '', $inputString ) );
		//return preg_replace( '/(^\s+|\s+$)/', '', Sanitizer::normalizeWhitespace( $inputString ) );
	}

	/**
	 * Normalize string into NFC by using the cleanup metod from UtfNormal.
	 *
	 * @since 0.1
	 *
	 * @param string $inputString The actual string to process.
	 *
	 * @return string where whitespace possibly are removed.
	 */
	static public function cleanupToNFC( $inputString ) {
		return UtfNormal::cleanUp( $inputString );
	}

	/**
	 * Do a toNFC after the string is squashed
	 *
	 * @since 0.1
	 *
	 * @param string $inputString
	 *
	 * @return string on NFC form
	 */
	static public function squashToNFC( $inputString ) {
		return self::cleanupToNFC( self::squashWhitespace( $inputString ) );
	}

	/**
	 * Reorder an array with keys with the order given by a second array.
	 *
	 * Note that this function will do an intersection and then organize
	 * the resulting array in the order given by the array in the second
	 * argument. The sorting is not by the keys, but by the order the
	 * entries are inserted into the resulting array. Another way to
	 * describe this is to change the insertion order of the first array
	 * according to the sequence of values in the second array.
	 *
	 * @since 0.1
	 *
	 * @param array $array
	 * @param array $sequence
	 *
	 * @return array
	 */
	static public function reorderArray( array $array, array $sequence ) {

		// First create an intersection with our wanted entries as keys
		$common = array_intersect_key( array_flip( $sequence ), $array );

		// Then do a merge with our previous array, and with a new intersection
		return array_merge( $common, array_intersect_key( $array, $common ) );
	}

	/**
	 * Find the multilingual texts that has keys in the the sequence.
	 *
	 * The final result will be in the order given by the sequence.
	 *
	 * @since 0.1
	 *
	 * @param array $texts the key-value pairs to check for existence
	 * @param array $sequence the list of keys that should exist
	 *
	 * @return array
	 */
	static public function filterMultilangText( array $texts = null, array $sequence = null ) {

		// Prerequisites for further processing
		if ( is_null( $texts ) || is_null( $sequence ) ) {
			return array(); // makes the simplest use case
		}

		// Do a reordering to get the language strings in correct order
		$texts = \Wikibase\Utils::reorderArray(
			$texts,
			$sequence
		);

		// Extract the valid codes
		$validCodes = array_filter(
			array_keys( $texts ),
			function( $langCode ) { return is_string( $langCode ) && Language::isValidCode( $langCode ); }
		);

		// If the valid codes are empty we don't need to process it further
		if ( empty( $validCodes ) ) {
			return array();
		}

		// Filter out everything that matches with a key before we return the result
		return array_intersect_key( $texts, array_flip( $validCodes ) );
	}

	/**
	 * Find the first multilingual string that can be used for constructing a language object. The
	 * global chain is always used.
	 *
	 * Note that a multilingual string from the global chain will always be globally cachable.
	 *
	 * @since 0.1
	 *
	 * @param array $texts the key-value pairs to check for existence
	 * @param array $sequence the list of keys that should exist
	 * @param array $fallback an array of values that are used as a replacement if nothing is found
	 * 		The fallback is in the form array( code, text, language )
	 * @return array|null triplet with the initial language code, the text, and the language object
	 */
	static public function lookupMultilangText( array $texts = null, array $sequence = null, array $fallback = null ) {

		// Prerequisites for further processing
		if ( is_null( $texts ) || is_null( $sequence ) ) {
			return $fallback; // makes the simplest use case
		}

		// Filter down the result
		$texts = \Wikibase\Utils::filterMultilangText( $texts, $sequence );
		if ( is_null( $texts ) || empty( $texts ) ) {
			return $fallback;
		}

		// Find the first language code we can turn into a language object
		// Note that the factory call do a pretty dumb cleaning up that can make this vejjy slow
		foreach ( $texts as $code => $text ) {
			$lang = Language::factory( $code );
			if ( !is_null( $lang ) ) {
				return array( $code, $text, $lang );
			}
		}

		// Use the fallback if the previous fails
		return $fallback;
	}

	/**
	 * Find the first multilingual string that can be used for constructing a language object
	 * for the current user. If a preferred language can't be identified the global chain is
	 * used.
	 *
	 * Note that a user specific multilingual string is not globally cachable.
	 *
	 * FIXME: duplication with @see lookupMultilangText, needs refactor
	 *
	 * @since 0.1
	 *
	 * @param array $texts the key-value pairs to check for existence
	 * @param array $sequence the list of keys that should exist
	 * @param array $fallback an array of values that are used as a replacement if nothing is found
	 * 		The fallback is in the form array( code, text, language )
	 * @return array|null triplet with the initial language code, the text, and the language object
	 */
	static public function lookupUserMultilangText( array $texts = null, array $sequence = null, array $fallback = null ) {
		// FIXME: deprecated globals!
		global $wgUser, $wgLang;

		// Prerequisites for further processing
		if ( is_null( $texts ) || is_null( $sequence ) ) {
			return $fallback; // makes the simplest use case
		}

		// Filter down the result
		$texts = \Wikibase\Utils::filterMultilangText( $texts, $sequence );
		if ( is_null( $texts ) || empty( $texts ) ) {
			return $fallback;
		}

		// Check if we can use the ordinary language
		// This should always be used if possible because this will match
		// with the user set language
		reset($texts);
		list( $code, $text ) = each( $texts );
		if ( $wgLang->getCode() === $code ) {
			$lang = Language::factory( $code );
			if ( !is_null( $lang ) ) {
				return array( $code, $text, $lang );
			}
		}

		// Find the first preferred language code we can turn into a language object
		// Note that the factory call do a pretty dumb cleaning up that can make this vejjy slow
		foreach ( $texts as $code => $text ) {
			if ( $wgUser->getOption( "sttl-languages-$code" ) ) {
				$lang = Language::factory( $code );
				if ( !is_null( $lang ) ) {
					return array( $code, $text, $lang );
				}
			}
		}

		// Find the first ordinary language code we can turn into a language object
		// Note that the factory call do a pretty dumb cleaning up that can make this vejjy slow
		foreach ( $texts as $code => $text ) {
			$lang = Language::factory( $code );
			if ( !is_null( $lang ) ) {
				return array( $code, $text, $lang );
			}
		}

		// Use the fallback if the previous fails
		return $fallback;
	}

	/**
	 * Get the fallback languages prepended with the source language itself.
	 *
	 * A language chain in this respect is the language itself and all fallback
	 * languagese. Because English is prepended to all languages it is not a real
	 * language group, its only a language group for the purpose of figuring out
	 * the best guess if language attributes are missing.
	 *
	 * Note that a language chain is globally unique, there will not be any
	 * language with two different chains.
	 *
	 * @since 0.1
	 *
	 * @param string $langCode the language code for the source language itself
	 * @return array of language codes
	 */
	static public function languageChain( $langCode ) {
		return array_merge( array( $langCode ), Language::getFallbacksFor( $langCode ) );
	}

	/**
	 * Generates and returns a GUID.
	 * @see http://php.net/manual/en/function.com-create-guid.php
	 *
	 * @since 0.1
	 *
	 * @return string
	 */
	public static function getGuid() {
		if ( function_exists( 'com_create_guid' ) ) {
			return trim( com_create_guid(), '{}' );
		}

		return sprintf(
			'%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
			mt_rand( 0, 65535 ),
			mt_rand( 0, 65535 ),
			mt_rand( 0, 65535 ),
			mt_rand( 16384, 20479 ),
			mt_rand( 32768, 49151 ),
			mt_rand( 0, 65535 ),
			mt_rand( 0, 65535 ),
			mt_rand( 0, 65535 )
		);
	}

	/**
	 * Returns a list of entity content model ids pointing to the ids of the namespaces in which they reside.
	 *
	 * @since 0.1
	 *
	 * @return array [ content model id (string) -> namespace id (integer) ]
	 */
	public static function getEntityNamespaces() {
		$namespaces = Settings::get( 'entityNamespaces' );

		if ( !is_array( $namespaces ) ) {
			return array();
		}

		return $namespaces;
	}

	/**
	 * Returns the namespace ID for the given entity content model, or false if the content model
	 * is not a known entity model.
	 *
	 * The return value is based on getEntityNamespaces(), which is configured via $wgWBSettings['entityNamespaces'].
	 *
	 * @since 0.1
	 *
	 * @param String $model the model ID
	 *
	 * @return int|bool the namespace associated with the given content model (or false if there is none)
	 */
	public static function getEntityNamespace( $model ) {
		$namespaces = self::getEntityNamespaces();
		return isset( $namespaces[$model] ) ? $namespaces[$model] : false;
	}

	/**
	 * Determines whether the given namespace is designated to hold some kind of Wikibase entity.
	 * Shorthand for in_array( $ns, self::getEntityNamespaces() );
	 *
	 * @since 0.1
	 *
	 * @param int $ns the namespace ID
	 *
	 * @return bool true iff $ns is an entity namespace
	 */
	public static function isEntityNamespace( $ns ) {
		return in_array( $ns, self::getEntityNamespaces() );
	}

	/**
	 * Determines whether the given namespace is a core namespace, i.e. a namespace pre-defined by MediaWiki core.
	 *
	 * The present implementation just checks whether the namespace ID is smaller than 100, relying on the
	 * convention that namespace IDs smaller than 100 are reserved for use by MediaWiki core.
	 *
	 * @since 0.1
	 *
	 * @param int $ns the namespace ID
	 *
	 * @return bool true iff $ns is a core namespace
	 */
	public static function isCoreNamespace( $ns ) {
		return $ns < 100;
	}

}
