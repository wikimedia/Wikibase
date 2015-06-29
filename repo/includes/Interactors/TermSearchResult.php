<?php

namespace Wikibase\Repo\Interactors;

use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Term\Term;
use Wikimedia\Assert\Assert;

/**
 * @since 0.5
 *
 * @author Adam Shorland
 */
class TermSearchResult {

	/**
	 * @var Term
	 */
	private $matchedTerm;

	/**
	 * @var string
	 */
	private $matchedTermType;

	/**
	 * @var EntityId
	 */
	private $entityId;

	/**
	 * @var null|Term
	 */
	private $displayLabel;

	/**
	 * @var null|Term
	 */
	private $displayDescription;

	/**
	 * @param Term $matchedTerm
	 * @param string $matchedTermType
	 * @param EntityId $entityId
	 * @param Term|null $displayLabel
	 * @param Term|null $displayDescription
	 */
	public function __construct(
		Term $matchedTerm,
		$matchedTermType,
		EntityId $entityId,
		Term $displayLabel = null,
		Term $displayDescription = null
	) {
		Assert::parameterType( 'string', $matchedTermType, '$matchedTermType' );
		$this->matchedTerm = $matchedTerm;
		$this->matchedTermType = $matchedTermType;
		$this->entityId = $entityId;
		$this->displayLabel = $displayLabel;
		$this->displayDescription = $displayDescription;
	}

	/**
	 * @return Term
	 */
	public function getMatchedTerm() {
		return $this->matchedTerm;
	}

	/**
	 * @return string
	 */
	public function getMatchedTermType() {
		return $this->matchedTermType;
	}

	/**
	 * @return EntityId
	 */
	public function getEntityId() {
		return $this->entityId;
	}

	/**
	 * @return Term|null
	 */
	public function getDisplayLabel() {
		return $this->displayLabel;
	}

	/**
	 * @return Term|null
	 */
	public function getDisplayDescription() {
		return $this->displayDescription;
	}

}
