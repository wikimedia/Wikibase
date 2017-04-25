<?php

namespace Wikibase\Repo\Specials;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Term\DescriptionsProvider;
use Wikibase\EditEntityFactory;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Summary;
use Wikibase\SummaryFormatter;

/**
 * Special page for setting the description of a Wikibase entity.
 *
 * @license GPL-2.0+
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class SpecialSetDescription extends SpecialModifyTerm {

	/**
	 * @param SpecialPageCopyrightView $copyrightView
	 * @param SummaryFormatter $summaryFormatter
	 * @param EntityRevisionLookup $entityRevisionLookup
	 * @param EntityTitleLookup $entityTitleLookup
	 * @param EditEntityFactory $editEntityFactory
	 */
	public function __construct(
		SpecialPageCopyrightView $copyrightView,
		SummaryFormatter $summaryFormatter,
		EntityRevisionLookup $entityRevisionLookup,
		EntityTitleLookup $entityTitleLookup,
		EditEntityFactory $editEntityFactory
	) {
		parent::__construct(
			'SetDescription',
			'edit',
			$copyrightView,
			$summaryFormatter,
			$entityRevisionLookup,
			$entityTitleLookup,
			$editEntityFactory
		);
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * @see SpecialModifyTerm::validateInput
	 *
	 * @return bool
	 */
	protected function validateInput() {
		if ( !parent::validateInput() ) {
			return false;
		}

		return $this->entityRevision->getEntity() instanceof DescriptionsProvider;
	}

	/**
	 * @see SpecialModifyTerm::getPostedValue()
	 *
	 * @return string|null
	 */
	protected function getPostedValue() {
		return $this->getRequest()->getVal( 'description' );
	}

	/**
	 * @see SpecialModifyTerm::getValue()
	 *
	 * @param EntityDocument $entity
	 * @param string $languageCode
	 *
	 * @throws InvalidArgumentException
	 * @return string
	 */
	protected function getValue( EntityDocument $entity, $languageCode ) {
		if ( !( $entity instanceof DescriptionsProvider ) ) {
			throw new InvalidArgumentException( '$entity must be a DescriptionsProvider' );
		}

		$descriptions = $entity->getDescriptions();

		if ( $descriptions->hasTermForLanguage( $languageCode ) ) {
			return $descriptions->getByLanguage( $languageCode )->getText();
		}

		return '';
	}

	/**
	 * @see SpecialModifyTerm::setValue()
	 *
	 * @param EntityDocument $entity
	 * @param string $languageCode
	 * @param string $value
	 *
	 * @return Summary
	 */
	protected function setValue( EntityDocument $entity, $languageCode, $value ) {
		$value = $value === '' ? null : $value;
		$summary = new Summary( 'wbsetdescription' );

		if ( $value === null ) {
			$changeOp = $this->termChangeOpFactory->newRemoveDescriptionOp( $languageCode );
		} else {
			$changeOp = $this->termChangeOpFactory->newSetDescriptionOp( $languageCode, $value );
		}

		$this->applyChangeOp( $changeOp, $entity, $summary );

		return $summary;
	}

}
