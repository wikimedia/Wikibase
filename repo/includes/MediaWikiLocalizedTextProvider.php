<?php

namespace Wikibase\Repo;

use Language;
use Message;
use Wikibase\View\LocalizedTextProvider;

/**
 * A LocalizedTextProvider wrapping MediaWiki's message system
 *
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Adrian Heine <adrian.heine@wikimedia.de>
 */
class MediaWikiLocalizedTextProvider implements LocalizedTextProvider {

	/**
	 * @var string
	 */
	private $languageCode;

	/**
	 * @param string $languageCode
	 */
	public function __construct( $languageCode ) {
		$this->languageCode = $languageCode;
	}

	/**
	 * @param string $key
	 * @param string[] $params Parameters that could be used for generating the text
	 *
	 * @return string The localized text
	 */
	public function get( $key, $params = [] ) {
		return ( new Message( $key, $params, Language::factory( $this->languageCode ) ) )->text();
	}

	/**
	 * @param string $key
	 *
	 * @return bool
	 */
	public function has( $key ) {
		return wfMessage( $key )->exists();
	}

	/**
	 * @param string $key Currently ignored
	 *
	 * @return string The language of the text returned for a specific key.
	 */
	public function getLanguageOf( $key ) {
		return $this->languageCode;
	}

}
