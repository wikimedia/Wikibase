<?php

namespace Wikibase\View\Module;

use FormatJson;
use ResourceLoaderContext;
use ResourceLoaderFileModule;
use Wikibase\View\Template\TemplateFactory;

/**
 * Injects templates into JavaScript.
 *
 * @since 0.2
 *
 * @licence GNU GPL v2+
 * @author H. Snater <mediawiki@snater.com>
 */

class TemplateModule extends ResourceLoaderFileModule {

	/**
	 * @see ResourceLoaderModule::getScript
	 *
	 * @param ResourceLoaderContext $context
	 *
	 * @return string
	 */
	public function getScript( ResourceLoaderContext $context ) {
		// register HTML templates
		$templateFactory = TemplateFactory::getDefaultInstance();
		$templatesJson = FormatJson::encode( $templateFactory->getTemplates() );

		// template store JavaScript initialisation
		$script = <<<EOT
( function( mw ) {
	'use strict';

	mw.wbTemplates = mw.wbTemplates || {};
	mw.wbTemplates.store = new mw.Map();
	mw.wbTemplates.store.set( $templatesJson );

}( mediaWiki ) );
EOT;

		return $script . "\n" . parent::getScript( $context );
	}

	/**
	 * @see ResourceLoaderModule::supportsURLLoading
	 *
	 * @return bool
	 */
	public function supportsURLLoading() {
		return false; // always use getScript() to acquire JavaScript (even in debug mode)
	}

	/**
	 * @see ResourceLoaderModule::getModifiedHash
	 *
	 * @param ResourceLoaderContext $context
	 *
	 * @return string
	 */
	public function getModifiedHash( ResourceLoaderContext $context ) {
		return (string)filemtime( __DIR__ . '/../../resources/templates.php' );
	}

	/**
	 * @see ResourceLoaderModule::getModifiedTime
	 *
	 * @param ResourceLoaderContext $context
	 *
	 * @return int
	 */
	public function getModifiedTime( ResourceLoaderContext $context ) {
		return $this->getHashMtime( $context );
	}

}
