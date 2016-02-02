<?php

namespace Wikibase\Lib;

/**
 * Provide languages supported as content languages by removing values in one ContentLanguages
 * from another ContentLanguages
 *
 * @author Adrian Heine < adrian.heine@wikimedia.de >
 */
class DifferenceContentLanguages implements ContentLanguages {

	/**
	 * @var ContentLanguages $a
	 */
	private $a;

	/**
	 * @var ContentLanguages $b
	 */
	private $b;

	/**
	 * @var string[]|null Array of language codes
	 */
	private $languageCodes = null;

	/**
	 * @param ContentLanguages $a
	 * @param ContentLanguages $b
	 */
	public function __construct( ContentLanguages $a, ContentLanguages $b ) {
		$this->a = $a;
		$this->b = $b;
	}

	/**
	 * @return string[] Array of language codes supported as content language
	 */
	public function getLanguages() {
		return $this->getLanguageCodes();
	}

	/**
	 * @param string $languageCode
	 *
	 * @return bool
	 */
	public function hasLanguage( $languageCode ) {
		return in_array( $languageCode, $this->getLanguageCodes() );
	}

	/**
	 * @return string[] Array of language codes
	 */
	private function getLanguageCodes() {
		if ( $this->languageCodes === null ) {
			$this->languageCodes = array_values( array_diff( $this->a->getLanguages(), $this->b->getLanguages() ) );
		}

		return $this->languageCodes;
	}

}
