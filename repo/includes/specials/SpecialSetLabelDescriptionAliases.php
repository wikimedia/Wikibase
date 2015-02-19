<?php

namespace Wikibase\Repo\Specials;

use Html;
use Language;
use Wikibase\ChangeOp\ChangeOpException;
use Wikibase\ChangeOp\FingerprintChangeOpFactory;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\FingerprintProvider;
use Wikibase\Lib\ContentLanguages;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Summary;

/**
 * Special page for setting label, description and aliases of a Wikibase Entity that features a
 * Fingerprint.
 *
 * @since 0.5
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 * @author Thiemo Mättig
 */
class SpecialSetLabelDescriptionAliases extends SpecialModifyEntity {

	/**
	 * @var FingerprintChangeOpFactory
	 */
	private $changeOpFactory;

	/**
	 * @var ContentLanguages
	 */
	private $termsLanguages;

	/**
	 * @var string
	 */
	private $languageCode;

	/**
	 * @var string
	 */
	private $label = '';

	/**
	 * @var string
	 */
	private $description = '';

	/**
	 * @var string[]
	 */
	private $aliases = array();

	public function __construct() {
		parent::__construct( 'SetLabelDescriptionAliases', 'edit' );

		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$this->changeOpFactory = $wikibaseRepo->getChangeOpFactoryProvider()
			->getFingerprintChangeOpFactory();
		$this->termsLanguages = $wikibaseRepo->getTermsLanguages();
	}

	/**
	 * @see SpecialModifyEntity::validateInput
	 *
	 * @return bool
	 */
	protected function validateInput() {
		return parent::validateInput()
			&& $this->entityRevision->getEntity() instanceof FingerprintProvider
			&& $this->isValidLanguageCode( $this->languageCode )
			&& $this->isAllowedToChangeTerms( $this->entityRevision->getEntity() );
	}

	/**
	 * @param Entity $entity
	 *
	 * @return bool
	 */
	private function isAllowedToChangeTerms( Entity $entity ) {
		$action = $entity->getType() . '-term';

		if ( !$this->getUser()->isAllowed( $action ) ) {
			$this->showErrorHTML( $this->msg( 'permissionserrors' ) . ': ' . $action );
			return false;
		}

		return true;
	}

	/**
	 * @see SpecialModifyEntity::getFormElements
	 *
	 * @param Entity $entity
	 *
	 * @return string HTML
	 */
	protected function getFormElements( Entity $entity = null ) {
		if ( $entity !== null && $this->languageCode !== null ) {
			$languageName = Language::fetchLanguageName(
				$this->languageCode, $this->getLanguage()->getCode()
			);
			$intro = $this->msg(
				'wikibase-setlabeldescriptionaliases-introfull',
				$this->getEntityTitle( $entity->getId() )->getPrefixedText(),
				$languageName
			);

			$html = Html::rawElement(
					'p',
					array(),
					$intro->parse()
				)
				. Html::hidden(
					'language',
					$this->languageCode
				)
				. Html::hidden(
					'id',
					$entity->getId()->getSerialization()
				);
		} else {
			$intro = $this->msg( 'wikibase-setlabeldescriptionaliases-intro' );
			$fieldId = 'wikibase-setlabeldescriptionaliases-language';
			$languageCode = $this->languageCode ? : $this->getLanguage()->getCode();

			$html = Html::rawElement(
					'p',
					array(),
					$intro->parse()
				)
				. parent::getFormElements( $entity )
				. Html::label(
					$this->msg( 'wikibase-modifyterm-language' )->text(),
					$fieldId,
					array(
						'class' => 'wb-label',
					)
				)
				. Html::input(
					'language',
					$languageCode,
					'text',
					array(
						'class' => 'wb-input',
						'id' => $fieldId,
					)
				);
		}

		$html .= $this->getLabeledInputField( 'label', $this->label )
			. $this->getLabeledInputField( 'description', $this->description )
			. $this->getLabeledInputField( 'aliases', implode( '|', $this->aliases ) );

		return $html;
	}

	/**
	 * Returns an HTML label and text input element for a specific term.
	 *
	 * @param string $termType Either 'label', 'description' or 'aliases'.
	 * @param string $value Text to fill the input element with
	 *
	 * @return string HTML
	 */
	private function getLabeledInputField( $termType, $value ) {
		$fieldId = 'wikibase-setlabeldescriptionaliases-' . $termType;

		// Messages:
		// wikibase-setlabeldescriptionaliases-label-label
		// wikibase-setlabeldescriptionaliases-description-label
		// wikibase-setlabeldescriptionaliases-aliases-label
		return Html::label(
			$this->msg( $fieldId . '-label' )->text(),
			$fieldId,
			array(
				'class' => 'wb-label',
			)
		)
		. Html::input(
			$termType,
			$value,
			'text',
			array(
				'class' => 'wb-input',
				'id' => $fieldId,
				'size' => 50,
			)
		);
	}

	/**
	 * @see SpecialModifyEntity::prepareArguments
	 *
	 * @param string $subPage
	 */
	protected function prepareArguments( $subPage ) {
		$request = $this->getRequest();
		$parts = $subPage === '' ? array() : explode( '/', $subPage, 2 );

		$this->languageCode = $request->getVal( 'language', isset( $parts[1] ) ? $parts[1] : '' );
		$this->label = $request->getVal( 'label', '' );
		$this->description = $request->getVal( 'description', '' );
		$aliasesText = $request->getVal( 'aliases', '' );
		$this->aliases = $aliasesText === '' ? array() : explode( '|', $aliasesText );

		// Parse the 'id' parameter and throw an exception if the entity can not be loaded
		parent::prepareArguments( $subPage );

		if ( $this->languageCode === '' ) {
			$this->languageCode = $this->getLanguage()->getCode();
		} elseif ( !$this->isValidLanguageCode( $this->languageCode ) ) {
			$msg = $this->msg( 'wikibase-wikibaserepopage-invalid-langcode', $this->languageCode );
			$this->showErrorHTML( $msg->parse() );
			$this->languageCode = null;
		}

		if ( $this->languageCode !== null
			&& $this->entityRevision !== null
			&& $this->entityRevision->getEntity() instanceof FingerprintProvider
		) {
			$fingerprint = $this->entityRevision->getEntity()->getFingerprint();

			if ( $this->label === '' && $fingerprint->hasLabel( $this->languageCode ) ) {
				$this->label = $fingerprint->getLabel( $this->languageCode )->getText();
			}

			if ( $this->description === '' && $fingerprint->hasDescription( $this->languageCode ) ) {
				$this->description = $fingerprint->getDescription( $this->languageCode )->getText();
			}

			if ( empty( $this->aliases ) && $fingerprint->hasAliasGroup( $this->languageCode ) ) {
				$this->aliases = $fingerprint->getAliasGroup( $this->languageCode )->getAliases();
			}
		}
	}

	/**
	 * @param string $languageCode
	 *
	 * @return bool
	 */
	private function isValidLanguageCode( $languageCode ) {
		return $languageCode !== null && $this->termsLanguages->hasLanguage( $languageCode );
	}

	/**
	 * @see SpecialModifyEntity::modifyEntity
	 *
	 * @param Entity $entity
	 *
	 * @return Summary[]|bool
	 */
	protected function modifyEntity( Entity $entity ) {
		$changeOps = array(
			$this->changeOpFactory->newSetLabelOp(
				$this->languageCode,
				$this->label
			),
			$this->changeOpFactory->newSetDescriptionOp(
				$this->languageCode,
				$this->description
			),
			$this->changeOpFactory->newSetAliasesOp(
				$this->languageCode,
				$this->aliases
			),
		);

		$success = true;

		foreach ( $changeOps as $changeOp ) {
			try {
				$this->applyChangeOp( $changeOp, $entity );
			} catch ( ChangeOpException $e ) {
				$this->showErrorHTML( $e->getMessage() );
				$success = false;
			}
		}

		if ( !$success ) {
			return false;
		}

		return $this->getSummary( 'wbeditentity' );
	}

}
