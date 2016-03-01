<?php

namespace Wikibase\View\Template;

/**
 * Allows storing and accessing of templates (e.g. snippets commonly used in server-side HTML
 * generation and client-side JavaScript processing).
 *
 * This class Stores plain templates.
 *
 * @since 0.2
 *
 * @license GPL-2.0+
 * @author H. Snater <mediawiki@snater.com>
 * @author Thiemo Mättig
 */
class TemplateRegistry {

	/**
	 * @var string[]
	 */
	private $templates = array();

	/**
	 * @param string[] $templates
	 */
	public function __construct( array $templates ) {
		foreach ( $templates as $key => $snippet ) {
			$this->templates[$key] = str_replace( "\t", '',
				preg_replace( '/<!--.*-->/Us', '', $snippet )
			);
		}
	}

	/**
	 * Gets the array containing all templates.
	 *
	 * @return string[]
	 */
	public function getTemplates() {
		return $this->templates;
	}

	/**
	 * Gets a specific template.
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	public function getTemplate( $key ) {
		return $this->templates[$key];
	}

}
