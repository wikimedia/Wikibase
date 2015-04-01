<?php

namespace Wikibase\View\Module;

use FormatJson;
use ResourceLoaderContext;
use ResourceLoaderFileModule;
use Wikibase\View\Template\TemplateRegistry;

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
		$templateRegistry = TemplateRegistry::getDefaultInstance();
		$templatesJson = FormatJson::encode( $templateRegistry->getTemplates() );

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

}
