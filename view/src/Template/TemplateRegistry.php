<?php

namespace Wikibase\Template;

/**
 * Allows storing and accessing of templates (e.g. snippets commonly used in server-side HTML
 * generation and client-side JavaScript processing).
 *
 * This class Stores plain templates.
 *
 * @since 0.2
 *
 * @licence GNU GPL v2+
 * @author H. Snater <mediawiki@snater.com>
 * @author Thiemo Mättig
 */
class TemplateRegistry {

	/**
	 * @var TemplateRegistry
	 */
	private static $instance;

	/**
	 * @var string[]
	 */
	private $templates = array();

	public static function getDefaultInstance() {
		if ( self::$instance === null ) {
			self::$instance = new self( include( __DIR__ . '/../../resources/templates.php' ) );
		}

		return self::$instance;
	}

	/**
	 * @param string[] $templates
	 */
	function __construct( array $templates = array() ) {
		$this->addTemplates( $templates );
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

	/**
	 * Adds multiple templates to the store.
	 *
	 * @param string[] $templates
	 */
	public function addTemplates( array $templates ) {
		foreach ( $templates as $key => $snippet ) {
			$this->addTemplate( $key, $snippet );
		}
	}

	/**
	 * Adds a single template to the store.
	 *
	 * @param string $key
	 * @param string $snippet
	 */
	public function addTemplate( $key, $snippet ) {
		$this->templates[$key] = str_replace( "\t", '', $snippet );
	}

}
