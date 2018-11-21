<?php

namespace Wikibase\Client;

use Job;
use MediaWiki\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use Title;
use Wikibase\Client\Changes\ChangeHandler;
use Wikibase\EntityChange;
use Wikimedia\Assert\Assert;

/**
 * Job for notifying a client wiki of a batch of changes on the repository.
 *
 * @see docs/change-propagation.wiki for an overview of the change propagation mechanism.
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class ChangeNotificationJob extends Job {

	/**
	 * @var EntityChange[]|null Initialized lazily by getChanges.
	 */
	private $changes = null;

	/**
	 * @var ChangeHandler|null
	 */
	private $changeHandler = null;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * Constructs a ChangeNotificationJob representing the changes given by $changeIds.
	 *
	 * @note: the constructor's signature is dictated by Job::factory, so we'll have to
	 *           live with it even though it's rather ugly for our use case.
	 *
	 * @see      Job::factory.
	 *
	 * @param Title $title
	 * @param array|bool $params Needs to have two keys: "repo": the id of the repository,
	 *     "changeIds": array of change ids.
	 */
	public function __construct( Title $title, $params = false ) {
		parent::__construct( 'ChangeNotification', $title, $params );

		Assert::parameterType( 'array', $params, '$params' );
		Assert::parameter(
			isset( $params['repo'] ),
			'$params',
			'$params[\'repo\'] not set.'
		);
		Assert::parameter(
			isset( $params['changeIds'] ) && is_array( $params['changeIds'] ),
			'$params',
			'$params[\'changeIds\'] not set or not an array.'
		);

		// TODO inject me
		$this->logger = WikibaseClient::getDefaultInstance()->getLogger();
	}

	/**
	 * Returns the batch of changes that should be processed.
	 *
	 * EntityChange objects are loaded using a EntityChangeLookup.
	 *
	 * @return EntityChange[] the changes to process.
	 */
	private function getChanges() {
		if ( $this->changes === null ) {
			$params = $this->getParams();
			$ids = $params['changeIds'];

			$this->logger->debug(
				"{method}: loading {idCount} changes.",
				[
					'method' => __METHOD__,
					'idCount' => count( $ids ),
				]
			);

			// load actual change records from the changes table
			// TODO: allow mock store for testing!
			$changeLookup = WikibaseClient::getDefaultInstance()->getStore()->getEntityChangeLookup();
			$this->changes = $changeLookup->loadByChangeIds( $ids );

			$this->logger->debug(
				"{method}: loaded {changeCount} of {idCount} changes.",
				[
					'method' => __METHOD__,
					'changeCount' => count( $this->changes ),
					'idCount' => count( $ids ),
				]
			);

			if ( count( $this->changes ) != count( $ids ) ) {
				trigger_error( "Number of changes loaded mismatches the number of change IDs provided: "
					. count( $this->changes ) . " != " . count( $ids ) . ". "
					. " Some changes were lost, possibly due to premature pruning.",
					E_USER_WARNING );
			}
		}

		return $this->changes;
	}

	/**
	 * @return bool success
	 */
	public function run() {
		$changes = $this->getChanges();

		$changeHandler = $this->getChangeHandler();
		$changeHandler->handleChanges( $changes, $this->getRootJobParams() );

		if ( $changes ) {
			/** @var EntityChange $last */
			$changeCount = count( $changes );
			$last = end( $changes );

			$this->logger->debug(
				"{method}: processed {changeCount} notifications, up to {lastChange}, timestamp {lastTime}; Lag is {lastAge} seconds.",
				[
					'method' => __METHOD__,
					'changeCount' => $changeCount,
					'lastChange' => $last->getId(),
					'lastTime' => $last->getTime(),
					'lastAge' => $last->getAge(),
				]
			);
		} else {
			$this->logger->debug( '{method}: processed no notifications.', [ 'method' => __METHOD__ ] );
		}

		return true;
	}

	/**
	 * @return ChangeHandler
	 */
	private function getChangeHandler() {
		if ( !$this->changeHandler ) {
			$this->changeHandler = WikibaseClient::getDefaultInstance()->getChangeHandler();
		}

		return $this->changeHandler;
	}

}
