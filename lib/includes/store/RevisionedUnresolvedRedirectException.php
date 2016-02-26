<?php

namespace Wikibase\Lib\Store;

use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Services\Lookup\UnresolvedEntityRedirectException;

/**
 * Exception indicating that an attempt was made to access a redirected EntityId
 * without resolving the redirect first.
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class RevisionedUnresolvedRedirectException extends UnresolvedEntityRedirectException {

	/**
	 * @var int
	 */
	private $revisionId;

	/**
	 * @var string
	 */
	private $revisionTimestamp;

	/**
	 * @param EntityId $entityId
	 * @param EntityId $redirectTargetId The ID of the target Entity of the redirect
	 * @param int $revisionId
	 * @param string $revisionTimestamp
	 */
	public function __construct( EntityId $entityId, EntityId $redirectTargetId, $revisionId = 0, $revisionTimestamp = '' ) {
		parent::__construct( $entityId, $redirectTargetId );

		$this->revisionId = $revisionId;
		$this->revisionTimestamp = $revisionTimestamp;
	}

	/**
	 * @return int
	 */
	public function getRevisionId() {
		return $this->revisionId;
	}

	/**
	 * @return string
	 */
	public function getRevisionTimestamp() {
		return $this->revisionTimestamp;
	}

}
