<?php

namespace Wikibase\ChangeOp;

use InvalidArgumentException;
use ValueValidators\Result;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Term\AliasesProvider;
use Wikibase\DataModel\Term\AliasGroupList;
use Wikibase\Repo\Validators\TermValidatorFactory;
use Wikibase\Summary;

/**
 * Class for aliases change operation
 *
 * @since 0.4
 *
 * @license GPL-2.0+
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 * @author Daniel Kinzler
 */
class ChangeOpAliases extends ChangeOpBase {

	/**
	 * @var string
	 */
	private $languageCode;

	/**
	 * @var string[]
	 */
	private $aliases;

	/**
	 * @var array
	 */
	private $action;

	/**
	 * @var TermValidatorFactory
	 */
	private $termValidatorFactory;

	/**
	 * @since 0.5
	 *
	 * @param string $languageCode
	 * @param string[] $aliases
	 * @param string $action should be set|add|remove
	 * @param TermValidatorFactory $termValidatorFactory
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct(
		$languageCode,
		array $aliases,
		$action,
		TermValidatorFactory $termValidatorFactory
	) {
		if ( !is_string( $languageCode ) ) {
			throw new InvalidArgumentException( 'Language code needs to be a string.' );
		}

		if ( !is_string( $action ) ) {
			throw new InvalidArgumentException( 'Action needs to be a string.' );
		}

		$this->languageCode = $languageCode;
		$this->aliases = $aliases;
		$this->action = $action;
		$this->termValidatorFactory = $termValidatorFactory;
	}

	/**
	 * Applies the change to the aliases
	 *
	 * @param AliasGroupList $aliases
	 *
	 * @throws ChangeOpException
	 */
	private function updateAliases( AliasGroupList $aliases ) {
		if ( $aliases->hasGroupForLanguage( $this->languageCode ) ) {
			$oldAliases = $aliases->getByLanguage( $this->languageCode )->getAliases();
		} else {
			$oldAliases = array();
		}

		if ( $this->action === 'set' || $this->action === '' ) {
			$newAliases = $this->aliases;
		} elseif ( $this->action === 'add' ) {
			$newAliases = array_merge( $oldAliases, $this->aliases );
		} elseif ( $this->action === 'remove' ) {
			$newAliases = array_diff( $oldAliases, $this->aliases );
		} else {
			throw new ChangeOpException( 'Bad action: ' . $this->action );
		}

		$aliases->setAliasesForLanguage( $this->languageCode, $newAliases );
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
		if ( !( $entity instanceof AliasesProvider ) ) {
			throw new InvalidArgumentException( '$entity must be a AliasesProvider' );
		}

		$this->updateSummary( $summary, $this->action, $this->languageCode, $this->aliases );

		$this->updateAliases( $entity->getAliasGroups() );
	}

	/**
	 * @see ChangeOp::validate
	 *
	 * @param EntityDocument $entity
	 *
	 * @throws ChangeOpException
	 * @return Result
	 */
	public function validate( EntityDocument $entity ) {
		$languageValidator = $this->termValidatorFactory->getLanguageValidator();
		$termValidator = $this->termValidatorFactory->getLabelValidator( $entity->getType() );

		// check that the language is valid
		$result = $languageValidator->validate( $this->languageCode );

		if ( !$result->isValid() ) {
			return $result;
		}

		// It should be possible to remove invalid aliases, but not to add/set new invalid ones
		if ( $this->action === 'set' || $this->action === '' || $this->action === 'add' ) {
			// Check that the new aliases are valid
			foreach ( $this->aliases as $alias ) {
				$result = $termValidator->validate( $alias );

				if ( !$result->isValid() ) {
					return $result;
				}
			}
		} elseif ( $this->action !== 'remove' ) {
			throw new ChangeOpException( 'Bad action: ' . $this->action );
		}

		//XXX: Do we want to check the updated fingerprint, as we do for labels and descriptions?
		return $result;
	}

}
