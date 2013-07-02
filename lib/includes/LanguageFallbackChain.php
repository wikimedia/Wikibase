<?php

namespace Wikibase;
use Language, IContextSource;

/**
 * Object representing a language fallback chain used in Wikibase.
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
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 */
class LanguageFallbackChain {

	/**
	 * Fallback levels
	 */
	const FALLBACK_ALL = 0xff;

	/**
	 * The language itself. eg. 'en' for 'en'.
	 */
	const FALLBACK_SELF = 1;

	/**
	 * Other compatible languages that can be translated into the requested language
	 * (and translation is automatically done). eg. 'sr', 'sr-ec' and 'sr-el' for 'sr'.
	 */
	const FALLBACK_VARIANTS = 2;

	/**
	 * All other language from the system fallback chain. eg. 'de' and 'en' for 'de-formal'.
	 */
	const FALLBACK_OTHERS = 4;

	/**
	 * @var LanguageWithConversion[]
	 */
	private $chain = array();

	/**
	 * @var string[] language codes (as array keys) used in previously added items, to check duplication faster
	 */
	private $fetched = array();

	/**
	 * Get the fallback chain based a single language, and specified fallback level.
	 *
	 * @param Language $language
	 * @param $mode bitfield of self::FALLBACK_*
	 *
	 * @return LanguageFallbackChain
	 */
	public static function newFromLanguage( Language $language, $mode = self::FALLBACK_ALL ) {
		static $cache = array();

		if ( isset( $cache[$language->getCode()][$mode] ) ) {
			return $cache[$language->getCode()][$mode];
		}

		$chain = new self();
		$chain->loadFromLanguage( $language, $mode );

		$cache[$language->getCode()][$mode] = $chain;

		return $chain;
	}

	/**
	 * Load fallback chain for a given language into this object.
	 *
	 * @param Language $language
	 * @param $mode bitfield of self::FALLBACK_*
	 */
	private function loadFromLanguage( Language $language, $mode ) {

		if ( $mode & self::FALLBACK_SELF ) {
			if ( !isset( $this->fetched[$language->getCode()] ) ) {
				$this->chain[] = LanguageWithConversion::factory( $language );
				$this->fetched[$language->getCode()] = true;
			}
		}

		if ( $mode & self::FALLBACK_VARIANTS ) {
			$parentLanguage = $language->getParentLanguage();
			if ( $parentLanguage ) {
				// It's less likely to trigger conversion mistakes by converting
				// zh-tw to zh-hk first instead of converting zh-cn to zh-tw.
				$variantFallbacks = $parentLanguage->getConverter()
					->getVariantFallbacks( $language->getCode() );
				if ( is_array( $variantFallbacks ) ) {
					$variants = array_unique( array_merge(
						$variantFallbacks, $parentLanguage->getVariants()
					) );
				} else {
					$variants = $parentLanguage->getVariants();
				}

				foreach ( $variants as $variant ) {
					$variantLanguage = Language::factory( $variant );
					if ( isset( $this->fetched[$variantLanguage->getCode()] ) ) {
						continue;
					}

					$this->chain[] = LanguageWithConversion::factory( $language, $variantLanguage );
					$this->fetched[$variantLanguage->getCode()] = true;
				}
			}
		}

		if ( $mode & self::FALLBACK_OTHERS ) {
			// Regarding $mode in recursive calls:
			// * self is a must to have the fallback item itself included;
			// * respect the original caller about whether to include variants or not;
			// * others should be excluded as they'll be handled here in loops.
			$recursiveMode = $mode;
			$recursiveMode &= self::FALLBACK_VARIANTS;
			$recursiveMode |= self::FALLBACK_SELF;
			foreach ( $language->getFallbackLanguages() as $other ) {
				$this->loadFromLanguage( Language::factory( $other ), $recursiveMode );
			}
		}
	}

	/**
	 * Construct the fallback chain based a context, currently from on data provided by Extension:Babel.
	 *
	 * @param IContextSource $context
	 *
	 * @return LanguageFallbackChain
	 */
	public static function newFromContext( IContextSource $context ) {
		$chain = new self();
		$chain->loadFromContext( $context );

		return $chain;
	}

	/**
	 * Load fallback chain for a given context into this object.
	 *
	 * @param IContextSource $context
	 */
	private function loadFromContext( IContextSource $context ) {
		global $wgBabelCategoryNames;

		$user = $context->getUser();

		if ( !class_exists( 'Babel' ) || $user->isAnon() ) {
			$this->loadFromLanguage( $context->getLanguage(), self::FALLBACK_ALL );
			return;
		}

		$babels = array();
		$contextLanguage = array( $context->getLanguage()->getCode() );

		if ( count( $wgBabelCategoryNames ) ) {
			// A little redundant but it's the only way to get required information with current Babel API.
			$previousLevelBabel = array();
			foreach ( $wgBabelCategoryNames as $level => $_ ) {
				// Make the current language at the top of the chain.
				$levelBabel = array_unique( array_merge(
					$contextLanguage, \Babel::getUserLanguages( $user, $level )
				) );
				$babels[$level] = array_diff( $levelBabel, $previousLevelBabel );
				$previousLevelBabel = $levelBabel;
			}
		} else {
			// Just in case
			$babels['N'] = $contextLanguage;
		}

		// First pass to get "compatible" languages (self and variants)
		foreach ( $babels as $languageCodes ) { // Already sorted when added
			foreach ( array( self::FALLBACK_SELF, self::FALLBACK_VARIANTS ) as $mode ) {
				foreach ( $languageCodes as $languageCode ) {
					$this->loadFromLanguage( Language::factory( $languageCode ), $mode );
				}
			}
		}

		// Second pass to get other languages from system fallback chain
		foreach ( $babels as $languageCodes ) {
			foreach ( $languageCodes as $languageCode ) {
				$this->loadFromLanguage( Language::factory( $languageCode ),
					self::FALLBACK_OTHERS | self::FALLBACK_VARIANTS
				);
			}
		}

		return $chain;
	}

	/**
	 * Get raw fallback chain as an array. Semi-private for testing.
	 *
	 * @return LanguageWithConversion[]
	 */
	public function getFallbackChain() {
		return $this->chain;
	}

	/**
	 * Try to fetch the best value in a multilingual data array.
	 *
	 * @param string[] $data Multilingual data with language codes as keys
	 *
	 * @return null|array of three items: array(
	 * 	'value' => finally fetched and translated value
	 * 	'language' => language code of the language which final value is in
	 * 	'source' => language code of the language where the value is fetched
	 * ), or null when no data can be found.
	 */
	public function extractPreferredValue( $data ) {

		foreach ( $this->chain as $languageWithConversion ) {
			if ( isset( $data[$languageWithConversion->getFetchLanguage()->getCode()] ) ) {
				return array(
					'value' => $languageWithConversion->translate(
						$data[$languageWithConversion->getFetchLanguage()->getCode()]
					),
					'language' => $languageWithConversion->getLanguage()->getCode(),
					'source' => $languageWithConversion->getFetchLanguage()->getCode(),
				);
			}
		}

		return null;
	}
}
