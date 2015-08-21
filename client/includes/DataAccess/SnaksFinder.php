<?php

namespace Wikibase\Client\DataAccess;

use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Statement\StatementListProvider;

/**
 * Find Snaks for claims in a given Entity, based on PropertyId.
 *
 * @since 0.5
 *
 * @license GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 */
class SnaksFinder {

	/**
	 * @param StatementListProvider $statementListProvider
	 * @param PropertyId $propertyId The PropertyId for which we want the formatted Snaks
	 * @param int[]|null $acceptableRanks
	 *
	 * @return Snak[]
	 */
	public function findSnaks(
		StatementListProvider $statementListProvider,
		PropertyId $propertyId,
		array $acceptableRanks = null
	) {
		$statementList = $this->getStatementsWithPropertyId( $statementListProvider, $propertyId );
		if ( $acceptableRanks === null ) {
			return $statementList->getBestStatements()->getMainSnaks();
		} else {
			return $statementList->getByRank( $acceptableRanks )->getMainSnaks();
		}
	}

	/**
	 * @param StatementListProvider $statementListProvider
	 * @param PropertyId $propertyId
	 *
	 * @return StatementList
	 */
	private function getStatementsWithPropertyId( StatementListProvider $statementListProvider, PropertyId $propertyId ) {
		return $statementListProvider
			->getStatements()
			->getByPropertyId( $propertyId );
	}

}
