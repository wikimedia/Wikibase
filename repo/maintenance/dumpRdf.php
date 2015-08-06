<?php

namespace Wikibase;

use SiteStore;
use Title;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\Dumpers\DumpGenerator;
use Wikibase\Dumpers\RdfDumpGenerator;
use Wikibase\Lib\Store\EntityPrefetcher;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Repo\Store\EntityPerPage;
use Wikibase\Repo\WikibaseRepo;

require_once __DIR__ . '/dumpEntities.php';

class DumpRdf extends DumpScript {

	/**
	 * @var EntityRevisionLookup
	 */
	private $revisionLookup;

	/**
	 * @var EntityPrefetcher
	 */
	private $entityPrefetcher;

	/**
	 * @var SiteStore
	 */
	private $siteStore;

	/**
	 * @var PropertyDataTypeLookup
	 */
	private $propertyDatatypeLookup;

	/**
	 * @var Settings
	 */
	private $settings;

	public function __construct() {
		parent::__construct();
		$this->addOption( 'format', "Set the dump format.", false, true );

		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$this->setServices(
			$wikibaseRepo->getStore()->newEntityPerPage(),
			$wikibaseRepo->getStore()->getEntityPrefetcher(),
			$wikibaseRepo->getSiteStore(),
			$wikibaseRepo->getPropertyDataTypeLookup(),
			$wikibaseRepo->getEntityRevisionLookup( 'uncached' ),
			$wikibaseRepo->getSettings()
		);
	}

	public function setServices(
		EntityPerPage $entityPerPage,
		EntityPrefetcher $entityPrefetcher,
		SiteStore $siteStore,
		PropertyDataTypeLookup $propertyDataTypeLookup,
		EntityRevisionLookup $entityRevisionLookup,
		SettingsArray $settings
	) {
		parent::setServices( $entityPerPage );
		$this->entityPrefetcher = $entityPrefetcher;
		$this->siteStore = $siteStore;
		$this->propertyDatatypeLookup = $propertyDataTypeLookup;
		$this->revisionLookup = $entityRevisionLookup;
		$this->settings = $settings;
	}

	/**
	 * Returns EntityPerPage::INCLUDE_REDIRECTS.
	 *
	 * @return mixed a EntityPerPage::XXX_REDIRECTS constant
	 */
	protected function getRedirectMode() {
		return EntityPerPage::INCLUDE_REDIRECTS;
	}

	/**
	 * Create concrete dumper instance
	 *
	 * @param resource $output
	 *
	 * @return DumpGenerator
	 */
	protected function createDumper( $output ) {
		$entityDataTitle = Title::makeTitle( NS_SPECIAL, 'EntityData' );

		return RdfDumpGenerator::createDumpGenerator(
			$this->getOption( 'format', 'ttl' ),
			$output,
			$this->settings->getSetting( 'conceptBaseUri' ),
			$entityDataTitle->getCanonicalURL() . '/',
			$this->siteStore->getSites(),
			$this->revisionLookup,
			$this->propertyDatatypeLookup,
			$this->entityPrefetcher
		);
	}

}

$maintClass = 'Wikibase\DumpRdf';
require_once RUN_MAINTENANCE_IF_MAIN;
