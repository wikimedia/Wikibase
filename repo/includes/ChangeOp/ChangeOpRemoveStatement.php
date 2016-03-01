<?php

namespace Wikibase\ChangeOp;

use InvalidArgumentException;
use ValueValidators\Result;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\StatementListHolder;
use Wikibase\Summary;

/**
 * Class for statement remove operation.
 *
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Addshore
 * @author Thiemo Mättig
 */
class ChangeOpRemoveStatement extends ChangeOpBase {

	/**
	 * @var string
	 */
	private $guid;

	/**
	 * @return string
	 */
	public function getGuid() {
		return $this->guid;
	}

	/**
	 * Constructs a new mainsnak change operation
	 *
	 * @since 0.5
	 *
	 * @param string $guid
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( $guid ) {
		if ( !is_string( $guid ) || $guid === '' ) {
			throw new InvalidArgumentException( '$guid must be a non-empty string' );
		}

		$this->guid = $guid;
	}

	/**
	 * @see ChangeOp::apply
	 *
	 * @param EntityDocument $entity
	 * @param Summary|null $summary
	 *
	 * @throws InvalidArgumentException
	 * @throws ChangeOpException
	 */
	public function apply( EntityDocument $entity, Summary $summary = null ) {
		if ( !( $entity instanceof StatementListHolder ) ) {
			throw new InvalidArgumentException( '$entity must be a StatementListHolder' );
		}

		$statements = $entity->getStatements();
		$statement = $statements->getFirstStatementWithGuid( $this->guid );

		if ( $statement === null ) {
			throw new ChangeOpException( "Entity does not have statement with GUID $this->guid" );
		}

		$statements->removeStatementsWithGuid( $this->guid );
		$entity->setStatements( $statements );

		$removedSnak = $statement->getMainSnak();
		$this->updateSummary( $summary, 'remove', '', $this->getSummaryArgs( $removedSnak ) );
	}

	/**
	 * @param Snak $mainSnak
	 *
	 * @return array
	 */
	private function getSummaryArgs( Snak $mainSnak ) {
		$propertyId = $mainSnak->getPropertyId();
		return array( array( $propertyId->getSerialization() => $mainSnak ) );
	}

	/**
	 * @see ChangeOp::validate
	 *
	 * @param EntityDocument $entity
	 *
	 * @return Result Always successful.
	 */
	public function validate( EntityDocument $entity ) {
		//TODO: move validation logic from apply() here.
		return Result::newSuccess();
	}

}
