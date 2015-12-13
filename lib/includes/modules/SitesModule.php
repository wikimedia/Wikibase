<?php

namespace Wikibase;

use ResourceLoaderContext;
use ResourceLoaderModule;
use SiteSQLStore;
use Wikibase\Lib\SitesModuleWorker;

/**
 * @since 0.2
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Werner < daniel.werner@wikimedia.de >
 * @author Marius Hoch < hoo@online.de >
 */
class SitesModule extends ResourceLoaderModule {

	/**
	 * @var SitesModuleWorker
	 */
	private $worker;

	public function __construct() {
		$this->worker = new SitesModuleWorker(
			Settings::singleton(),
			SiteSQLStore::newInstance(),
			wfGetCache( wfIsHHVM() ? CACHE_ACCEL : CACHE_ANYTHING )
		);
	}

	/**
	 * Used to propagate information about sites to JavaScript.
	 * Sites infos will be available in 'wbSiteDetails' config var.
	 * @see ResourceLoaderModule::getScript
	 *
	 * @since 0.2
	 *
	 * @param ResourceLoaderContext $context
	 *
	 * @return string
	 */
	public function getScript( ResourceLoaderContext $context ) {
		return $this->worker->getScript();
	}

	/**
	 * @see ResourceLoaderModule::getDefinitionSummary
	 *
	 * @param ResourceLoaderContext $context
	 *
	 * @return string
	 */
	public function getDefinitionSummary( ResourceLoaderContext $context ) {
		// Appending arrays using + is the right thing to do here: All keys
		// are named and we want the first one to overwrite the second one.
		return $this->worker->getDefinitionSummary() + parent::getDefinitionSummary( $context );
	}

}
