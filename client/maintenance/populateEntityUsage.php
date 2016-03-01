<?php

namespace Wikibase;

use LoggedUpdateMaintenance;
use Wikibase\Client\Usage\Sql\EntityUsageTableBuilder;
use Wikibase\Client\WikibaseClient;
use Wikibase\Lib\Reporting\ObservableMessageReporter;
use Wikibase\Lib\Reporting\ReportingExceptionHandler;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' )
	: __DIR__ . '/../../../..';

require_once $basePath . '/maintenance/Maintenance.php';

/**
 * Maintenance script for populating wbc_entity_usage based on the page_props table.
 *
 * @since 0.4
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class PopulateEntityUsage extends LoggedUpdateMaintenance {

	public function __construct() {
		$this->addDescription( 'Populate the wbc_entity_usage table based on entries in page_props.' );

		$this->addOption( 'start-page', "The page ID to start from.", false, true );

		parent::__construct();

		$this->setBatchSize( 1000 );
	}

	/**
	 * @see LoggedUpdateMaintenance::doDBUpdates
	 *
	 * @return boolean
	 */
	public function doDBUpdates() {
		if ( !defined( 'WBC_VERSION' ) ) {
			$this->output( "You need to have Wikibase enabled in order to use this maintenance script!\n\n" );
			exit;
		}

		$startPage = (int)$this->getOption( 'start-page', 0 );

		$reporter = new ObservableMessageReporter();
		$reporter->registerReporterCallback(
			array( $this, 'report' )
		);

		$builder = new EntityUsageTableBuilder(
			WikibaseClient::getDefaultInstance()->getEntityIdParser(),
			wfGetLB(),
			$this->mBatchSize
		);

		$builder->setProgressReporter( $reporter );
		$builder->setExceptionHandler( new ReportingExceptionHandler( $reporter ) );

		$builder->fillUsageTable( $startPage );
		return true;
	}

	/**
	 * @see LoggedUpdateMaintenance::getUpdateKey
	 *
	 * @return string
	 */
	public function getUpdateKey() {
		return 'Wikibase\PopulateEntityUsage';
	}

	/**
	 * Outputs a message vis the output() method.
	 *
	 * @since 0.4
	 *
	 * @param string $msg
	 */
	public function report( $msg ) {
		$this->output( "$msg\n" );
	}

}

$maintClass = 'Wikibase\PopulateEntityUsage';
require_once RUN_MAINTENANCE_IF_MAIN;
