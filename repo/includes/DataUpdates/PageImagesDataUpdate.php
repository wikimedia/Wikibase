<?php

namespace Wikibase\Repo\DataUpdates;

use DataValues\StringValue;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;

/**
 * Code to make the PageImages extension aware of pages in the Wikibase namespaces.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Thiemo Mättig
 */
class PageImagesDataUpdate {

	/**
	 * @var int[]
	 */
	private $propertyPriorities = array();

	/**
	 * @var string
	 */
	private $bestProperty;

	/**
	 * @var int
	 */
	private $bestRank;

	/**
	 * @var string
	 */
	private $bestFileName;

	/**
	 * @param string[] $imagePropertyIds
	 */
	public function __construct( array $imagePropertyIds ) {
		$this->setPropertyIds( $imagePropertyIds );
	}

	/**
	 * @param string[] $ids
	 */
	private function setPropertyIds( array $ids ) {
		$this->propertyPriorities = array_flip( array_unique( array_values( $ids ) ) );
	}

	/**
	 * @param StatementList $statements
	 *
	 * @return string|null The file's page name without the NS_FILE namespace, or null if not found.
	 */
	public function getBestImageFileName( StatementList $statements ) {
		$this->bestProperty = null;
		$this->bestRank = null;
		$this->bestFileName = null;

		foreach ( $statements->toArray() as $statement ) {
			$this->processStatement( $statement );
		}

		return $this->bestFileName;
	}

	/**
	 * @param Statement $statement
	 */
	public function processStatement( Statement $statement ) {
		$this->processSnak(
			$statement->getMainSnak(),
			$statement->getPropertyId(),
			$statement->getRank()
		);
	}

	/**
	 * @param Snak $snak
	 * @param PropertyId $propertyId
	 * @param int $rank
	 */
	private function processSnak(
		Snak $snak,
		PropertyId $propertyId,
		$rank = Statement::RANK_NORMAL
	) {
		$id = $propertyId->getSerialization();
		$fileName = $this->getString( $snak );

		if ( $fileName === null || $fileName === '' ) {
			return;
		}

		if ( !$this->isAcceptableRank( $rank ) ) {
			return;
		}

		if ( !$this->isAcceptablePriority( $id ) ) {
			return;
		}

		if ( $this->isSamePriority( $id ) && !$this->isBetterRank( $rank ) ) {
			return;
		}

		$this->bestProperty = $id;
		$this->bestRank = $rank;
		$this->bestFileName = $fileName;
	}

	/**
	 * @param Snak $snak
	 *
	 * @return string|null
	 */
	private function getString( Snak $snak ) {
		if ( $snak instanceof PropertyValueSnak ) {
			$value = $snak->getDataValue();

			if ( $value instanceof StringValue ) {
				return $value->getValue();
			}
		}

		return null;
	}

	/**
	 * @param string $propertyId
	 *
	 * @return bool True if the property is configured as one of the image properties and it's
	 * priority is equal or better than the current best.
	 */
	private function isAcceptablePriority( $propertyId ) {
		if ( !array_key_exists( $propertyId, $this->propertyPriorities ) ) {
			return false;
		}

		if ( $this->bestProperty === null ) {
			return true;
		}

		$priority = $this->propertyPriorities[$propertyId];
		$bestPriority = $this->propertyPriorities[$this->bestProperty];
		return $priority <= $bestPriority;
	}

	/**
	 * @param string $propertyId
	 *
	 * @return bool True if the property's priority is identical to the current best.
	 */
	private function isSamePriority( $propertyId ) {
		if ( $this->bestProperty === null ) {
			return false;
		}

		$priority = $this->propertyPriorities[$propertyId];
		$bestPriority = $this->propertyPriorities[$this->bestProperty];
		return $priority === $bestPriority;
	}

	/**
	 * @param int $rank
	 *
	 * @return bool True if the rank is better than deprecated.
	 */
	private function isAcceptableRank( $rank ) {
		// Ranks are guaranteed to be in increasing, numerical order.
		return $rank > Statement::RANK_DEPRECATED;
	}

	/**
	 * @param int $rank
	 *
	 * @return bool
	 */
	private function isBetterRank( $rank ) {
		if ( $this->bestRank === null ) {
			// Everything is better than nothing.
			return true;
		}

		// Ranks are guaranteed to be in increasing, numerical order.
		return $rank > $this->bestRank;
	}

}
