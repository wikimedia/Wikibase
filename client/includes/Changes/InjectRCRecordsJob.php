<?php

namespace Wikibase\Client\Changes;

use InvalidArgumentException;
use Job;
use JobSpecification;
use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Title;
use Wikibase\Client\Changes\ChangeHandler;
use Wikibase\Client\RecentChanges\RecentChangeFactory;
use Wikibase\Client\RecentChanges\RecentChangesDuplicateDetector;
use Wikibase\Client\Store\TitleFactory;
use Wikibase\Client\WikibaseClient;
use Wikibase\EntityChange;
use Wikibase\Lib\Store\Sql\EntityChangeLookup;
use Wikimedia\Assert\Assert;
use Wikimedia\Rdbms\LBFactory;

/**
 * Job for injecting RecentChange records representing changes on the Wikibase repository.
 *
 * @see docs/change-propagation.wiki for an overview of the change propagation mechanism.
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class InjectRCRecordsJob extends Job {

	/**
	 * @var LBFactory
	 */
	private $lbFactory;

	/**
	 * @var EntityChangeLookup
	 */
	private $changeLookup;

	/**
	 * @var RecentChangeFactory
	 */
	private $rcFactory;

	/**
	 * @var RecentChangesDuplicateDetector|null
	 */
	private $rcDuplicateDetector = null;

	/**
	 * @var TitleFactory
	 */
	private $titleFactory;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var StatsdDataFactoryInterface|null
	 */
	private $stats = null;

	/**
	 * @var int Batch size for database operations
	 */
	private $dbBatchSize = 100;

	/**
	 * @param Title[] $titles
	 * @param EntityChange $change
	 *
	 * @return JobSpecification
	 */
	public static function makeJobSpecification( array $titles, EntityChange $change ) {
		if ( $change->getId() === null ) {
			throw new InvalidArgumentException( 'The given Change does not have an ID defined' );
		}

		$pages = [];

		foreach ( $titles as $t ) {
			$id = $t->getArticleId();
			$pages[$id] = [ $t->getNamespace(), $t->getDBkey() ];
		}

		$params = [
			'change' => $change->getId(),
			'pages' => $pages
		];

		return new JobSpecification(
			'wikibase-InjectRCRecords',
			$params
		);
	}

	/**
	 * Constructs an InjectRCRecordsJob for injecting a change into the recentchanges feed
	 * for the given pages.
	 *
	 * @param LBFactory $lbFactory
	 * @param EntityChangeLookup $changeLookup
	 * @param RecentChangeFactory $rcFactory
	 * @param array $params Needs to have two keys: "change": the id of the change,
	 *     "pages": array of pages, represented as $pageId => [ $namespace, $dbKey ].
	 */
	public function __construct(
		LBFactory $lbFactory,
		EntityChangeLookup $changeLookup,
		RecentChangeFactory $rcFactory,
		array $params
	) {
		$title = Title::makeTitle( NS_SPECIAL, 'Badtitle/' . __CLASS__ );
		parent::__construct( 'wikibase-InjectRCRecords', $title, $params );

		Assert::parameter(
			isset( $params['change'] ),
			'$params',
			'$params[\'change\'] not set.'
		);
		Assert::parameter(
			isset( $params['pages'] ),
			'$params',
			'$params[\'pages\'] not set.'
		);
		Assert::parameterElementType(
			'array',
			$params['pages'],
			'$params[\'pages\']'
		);

		$this->lbFactory = $lbFactory;
		$this->changeLookup = $changeLookup;
		$this->rcFactory = $rcFactory;

		$this->titleFactory = new TitleFactory();
		$this->logger = new NullLogger();
	}

	/**
	 * @param RecentChangesDuplicateDetector $rcDuplicateDetector
	 */
	public function setRecentChangesDuplicateDetector( RecentChangesDuplicateDetector $rcDuplicateDetector ) {
		$this->rcDuplicateDetector = $rcDuplicateDetector;
	}

	/**
	 * @param TitleFactory $titleFactory
	 */
	public function setTitleFactory( TitleFactory $titleFactory ) {
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @param LoggerInterface $logger
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @param StatsdDataFactoryInterface $stats
	 */
	public function setStats( StatsdDataFactoryInterface $stats ) {
		$this->stats = $stats;
	}

	/**
	 * @param int $dbBatchSize
	 */
	public function setDbBatchSize( $dbBatchSize ) {
		Assert::parameterType( 'integer', $dbBatchSize, '$dbBatchSize' );
		$this->dbBatchSize = $dbBatchSize;
	}

	/**
	 * Returns the change that should be processed.
	 *
	 * EntityChange objects are loaded using a EntityChangeLookup.
	 *
	 * @return EntityChange|null the change to process (or none).
	 */
	private function getChange() {
		$params = $this->getParams();
		$changeId = $params['change'];

		$this->logger->debug( __FUNCTION__ . ": loading change $changeId." );

		$changes = $this->changeLookup->loadByChangeIds( [ $changeId ] );

		$change = reset( $changes );

		if ( !$change ) {
			$this->logger->error( __FUNCTION__ . ": failed to load change $changeId." );
		}

		return $change;
	}

	/**
	 * Returns the list of Titles to inject RC entries for.
	 *
	 * @return Title[]
	 */
	private function getTitles() {
		$params = $this->getParams();
		$pages = $params['pages'];

		$titles = [];

		foreach ( $pages as $pageId => list( $namespace, $dbKey ) ) {
			$titles[] = $this->titleFactory->makeTitle( $namespace, $dbKey );
		}

		return $titles;
	}

	/**
	 * @return bool success
	 */
	public function run() {
		$change = $this->getChange();
		$titles = $this->getTitles();

		if ( !$change || $titles === [] ) {
			return false;
		}

		$rcAttribs = $this->rcFactory->prepareChangeAttributes( $change );

		$c = 0;
		$trxToken = $this->lbFactory->getEmptyTransactionTicket( __METHOD__ );

		foreach ( $titles as $title ) {
			if ( !$title->exists() ) {
				continue;
			}

			$rc = $this->rcFactory->newRecentChange( $change, $title, $rcAttribs );

			if ( $this->rcDuplicateDetector
				&& $this->rcDuplicateDetector->changeExists( $rc )
			) {
				$this->logger->debug( __FUNCTION__ . ": skipping duplicate RC entry for " . $title->getFullText() );
			} else {
				$this->logger->debug( __FUNCTION__ . ": saving RC entry for " . $title->getFullText() );
				$rc->save();
			}

			if ( ++$c >= $this->dbBatchSize ) {
				$this->lbFactory->commitAndWaitForReplication( __METHOD__, $trxToken );
				$trxToken = $this->lbFactory->getEmptyTransactionTicket( __METHOD__ );
				$c = 0;
			}
		}

		if ( $c > 0 ) {
			$this->lbFactory->commitAndWaitForReplication( __METHOD__, $trxToken );
		}

		$this->incrementStats( 'InjectRCRecords.run.titles', count( $titles ) );

		return true;
	}

	private function incrementStats( $updateType, $delta ) {
		if ( $this->stats ) {
			$this->stats->updateCount( 'wikibase.client.pageupdates.' . $updateType, $delta );
		}
	}

}
