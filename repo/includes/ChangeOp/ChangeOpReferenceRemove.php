<?php

namespace Wikibase\ChangeOp;

use InvalidArgumentException;
use ValueValidators\Result;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\ReferenceList;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikibase\Summary;

/**
 * Class for reference removal change operation
 *
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Addshore
 */
class ChangeOpReferenceRemove extends ChangeOpBase {

	/**
	 * @var string
	 */
	private $statementGuid;

	/**
	 * @var string
	 */
	private $referenceHash;

	/**
	 * Constructs a new reference removal change operation
	 *
	 * @since 0.5
	 *
	 * @param string $statementGuid
	 * @param string $referenceHash
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( $statementGuid, $referenceHash ) {
		if ( !is_string( $statementGuid ) || $statementGuid === '' ) {
			throw new InvalidArgumentException( '$statementGuid needs to be a string and must not be empty' );
		}

		if ( !is_string( $referenceHash ) || $referenceHash === '' ) {
			throw new InvalidArgumentException( '$referenceHash needs to be a string and must not be empty' );
		}

		$this->statementGuid = $statementGuid;
		$this->referenceHash = $referenceHash;
	}

	/**
	 * @see ChangeOp::apply()
	 *
	 * @param EntityDocument $entity
	 * @param Summary|null $summary
	 *
	 * @throws InvalidArgumentException
	 * @throws ChangeOpException
	 */
	public function apply( EntityDocument $entity, Summary $summary = null ) {
		if ( !( $entity instanceof StatementListProvider ) ) {
			throw new InvalidArgumentException( '$entity must be a StatementListProvider' );
		}

		$statements = $entity->getStatements();
		$statement = $statements->getFirstStatementWithGuid( $this->statementGuid );

		if ( $statement === null ) {
			throw new ChangeOpException( "Entity does not have claim with GUID $this->statementGuid" );
		}

		$references = $statement->getReferences();
		$this->removeReference( $references, $summary );

		if ( $summary !== null ) {
			$summary->addAutoSummaryArgs( $this->getSnakSummaryArgs( $statement->getMainSnak() ) );
		}

		$statement->setReferences( $references );
	}

	/**
	 * @since 0.4
	 *
	 * @param ReferenceList $references
	 * @param Summary|null $summary
	 *
	 * @throws ChangeOpException
	 */
	protected function removeReference( ReferenceList $references, Summary $summary = null ) {
		if ( !$references->hasReferenceHash( $this->referenceHash ) ) {
			throw new ChangeOpException( "Reference with hash $this->referenceHash does not exist" );
		}
		$references->removeReferenceHash( $this->referenceHash );
		$this->updateSummary( $summary, 'remove' );
		if ( $summary !== null ) {
			$summary->addAutoCommentArgs( 1 ); //atomic edit, only one reference changed
		}
	}

	/**
	 * @since 0.4
	 *
	 * @param Snak $snak
	 * @return array
	 */
	protected function getSnakSummaryArgs( Snak $snak ) {
		$propertyId = $snak->getPropertyId();

		return array( array( $propertyId->getSerialization() => $snak ) );
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
