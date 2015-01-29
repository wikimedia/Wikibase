<?php

namespace Wikibase\Template;

use Message;

/**
 * Allows storing and accessing of templates (e.g. snippets commonly used in server-side HTML
 * generation and client-side JavaScript processing).
 *
 * This class Represents a template that can contain placeholders just like MediaWiki messages.
 *
 * @since 0.2
 *
 * @licence GNU GPL v2+
 * @author H. Snater <mediawiki@snater.com>
 * @author Thiemo Mättig
 */
class Template extends Message {

	/**
	 * important! note that the Template class does not escape anything.
	 * be sure to escape your params before using this class!
	 *
	 * @param string|string[] $key Message key or array of message keys to try and use the first
	 * non-empty message for.
	 * @param string|null $template A raw, non-empty template string or null, if a template with
	 * that name could not be found.
	 * @param array $params Message parameters.
	 */
	public function __construct( $key, $template, $params = array() ) {
		parent::__construct( $key, $params );

		if ( is_string( $template ) && $template !== '' ) {
			$this->message = $template;
		}
	}

	/**
	 * @return string
	 */
	public function render() {
		// Use plain() to prevent replacing {{...}}
		return $this->plain();
	}

}
