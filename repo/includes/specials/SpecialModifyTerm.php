<?php

namespace Wikibase\Repo\Specials;

use Html;
use InvalidArgumentException;
use Language;
use PermissionsError;
use Wikibase\ChangeOp\ChangeOpException;
use Wikibase\ChangeOp\FingerprintChangeOpFactory;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\Lib\ContentLanguages;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Summary;

/**
 * Abstract special page for setting a value of a Wikibase entity.
 *
 * @since 0.4
 * @licence GNU GPL v2+
 * @author Bene* < benestar.wikimedia@gmail.com >
 * @author Daniel Kinzler
 */
abstract class SpecialModifyTerm extends SpecialModifyEntity {

	/**
	 * The language the value is set in.
	 *
	 * @var string
	 */
	private $languageCode;

	/**
	 * The value to set.
	 *
	 * @var string
	 */
	private $value;

	/**
	 * @var FingerprintChangeOpFactory
	 */
	protected $termChangeOpFactory;

	/**
	 * @var ContentLanguages
	 */
	private $termsLanguages;

	/**
	 * @since 0.4
	 *
	 * @param string $title The title of the special page
	 * @param string $restriction The required user right, 'edit' per default.
	 */
	public function __construct( $title, $restriction = 'edit' ) {
		parent::__construct( $title, $restriction );

		$changeOpFactoryProvider = WikibaseRepo::getDefaultInstance()->getChangeOpFactoryProvider();
		$this->termChangeOpFactory = $changeOpFactoryProvider->getFingerprintChangeOpFactory();
		$this->termsLanguages = WikibaseRepo::getDefaultInstance()->getTermsLanguages();
	}

	/**
	 * @see SpecialModifyEntity::prepareArguments()
	 *
	 * @since 0.4
	 *
	 * @param string $subPage
	 */
	protected function prepareArguments( $subPage ) {
		parent::prepareArguments( $subPage );

		$request = $this->getRequest();
		$parts = ( $subPage === '' ) ? array() : explode( '/', $subPage, 2 );

		// Language
		$this->languageCode = $request->getVal( 'language', isset( $parts[1] ) ? $parts[1] : '' );

		if ( $this->languageCode === '' ) {
			$this->languageCode = null;
		}

		$this->checkSubPageLanguage();

		// Value
		$this->value = $this->getPostedValue();
		if ( $this->value === null ) {
			$this->value = $request->getVal( 'value' );
		}
	}

	/**
	 * Check the language given as sup page argument.
	 */
	private function checkSubPageLanguage() {
		if ( $this->languageCode !== null && !$this->termsLanguages->hasLanguage( $this->languageCode ) ) {
			$errorMessage = $this->msg(
				'wikibase-wikibaserepopage-invalid-langcode',
				$this->languageCode
			)->parse();

			$this->showErrorHTML( $errorMessage );
		}
	}

	/**
	 * @see SpecialModifyEntity::validateInput()
	 *
	 * @return bool
	 */
	protected function validateInput() {
		$request = $this->getRequest();

		if ( !parent::validateInput() ) {
			return false;
		}

		try{
			$this->checkTermChangePermissions( $this->entityRevision->getEntity() );
		} catch( PermissionsError $e ) {
			$this->showErrorHTML( $this->msg( 'permissionserrors' ) . ': ' . $e->permission );
			return false;
		}

		// to provide removing after posting the full form
		if ( $request->getVal( 'remove' ) === null && $this->value === '' ) {
			$id = $this->entityRevision->getEntity()->getId();

			$this->showErrorHTML(
			// Messages: wikibase-setlabel-warning-remove, wikibase-setdescription-warning-remove,
			// wikibase-setaliases-warning-remove
				$this->msg(
					'wikibase-' . strtolower( $this->getName() ) . '-warning-remove',
					$this->getEntityTitle( $id )->getText()
				)->parse(),
				'warning'
			);
			return false;
		}

		return true;
	}

	/**
	 * @see SpecialModifyEntity::modifyEntity()
	 *
	 * @since 0.5
	 *
	 * @param Entity $entity
	 *
	 * @return Summary|bool
	 */
	protected function modifyEntity( Entity $entity ) {
		try {
			$summary = $this->setValue( $entity, $this->languageCode, $this->value );
		} catch ( ChangeOpException $e ) {
			$this->showErrorHTML( $e->getMessage() );
			return false;
		}

		return $summary;
	}

	/**
	 * @param Entity $entity
	 *
	 * @throws PermissionsError
	 * @throws InvalidArgumentException
	 */
	private function checkTermChangePermissions( Entity $entity ) {
		if( $entity instanceof Item ) {
			$type = 'item';
		} else if ( $entity instanceof Property ) {
			$type = 'property';
		} else {
			throw new InvalidArgumentException( 'Unexpected Entity type when checking special page term change permissions' );
		}
		$restriction = $type . '-term';
		if ( !$this->getUser()->isAllowed( $restriction ) ) {
			throw new PermissionsError( $restriction );
		}
	}

	/**
	 * @see SpecialModifyEntity::getFormElements()
	 *
	 * @param Entity $entity
	 *
	 * @return string
	 */
	protected function getFormElements( Entity $entity = null ) {
		if ( $this->languageCode === null ) {
			$this->languageCode = $this->getLanguage()->getCode();
		}
		if ( $this->value === null ) {
			$this->value = $this->getValue( $entity, $this->languageCode );
		}

		$valueinput = Html::input(
			'value',
			$this->getRequest()->getVal( 'value' ) ? $this->getRequest()->getVal( 'value' ) : $this->value,
			'text',
			array(
				'class' => 'wb-input',
				'id' => 'wb-modifyterm-value',
			)
		)
		. Html::element( 'br' );

		$languageName = Language::fetchLanguageName( $this->languageCode, $this->getLanguage()->getCode() );

		if ( $entity !== null && $this->languageCode !== null && $languageName !== '' ) {
			return Html::rawElement(
				'p',
				array(),
				// Messages: wikibase-setlabel-introfull, wikibase-setdescription-introfull,
				// wikibase-setaliases-introfull
				$this->msg(
					'wikibase-' . strtolower( $this->getName() ) . '-introfull',
					$this->getEntityTitle( $entity->getId() )->getPrefixedText(),
					$languageName
				)->parse()
			)
			. Html::input( 'language', $this->languageCode, 'hidden' )
			. Html::input( 'id', $entity->getId()->getSerialization(), 'hidden' )
			. Html::input( 'remove', 'remove', 'hidden' )
			. $valueinput;
		} else {
			return Html::rawElement(
				'p',
				array(),
				// Messages: wikibase-setlabel-intro, wikibase-setdescription-intro,
				// wikibase-setaliases-intro
				$this->msg( 'wikibase-' . strtolower( $this->getName() ) . '-intro' )->parse()
			)
			. parent::getFormElements( $entity )
			. Html::element(
				'label',
				array(
					'for' => 'wb-modifyterm-language',
					'class' => 'wb-label'
				),
				$this->msg( 'wikibase-modifyterm-language' )->text()
			)
			. Html::input(
				'language',
				$this->languageCode,
				'text',
				array(
					'class' => 'wb-input',
					'id' => 'wb-modifyterm-language'
				)
			)
			. Html::element( 'br' )
			. Html::element(
				'label',
				array(
					'for' => 'wb-modifyterm-value',
					'class' => 'wb-label'
				),
				// Messages: wikibase-setlabel-label, wikibase-setdescription-label,
				// wikibase-setaliases-label
				$this->msg( 'wikibase-' . strtolower( $this->getName() ) . '-label' )->text()
			)
			. $valueinput;
		}
	}

	/**
	 * Returning the posted value of the request.
	 *
	 * @since 0.4
	 *
	 * @return string
	 */
	abstract protected function getPostedValue();

	/**
	 * Returning the value of the entity name by the given language
	 *
	 * @since 0.5
	 *
	 * @param Entity|null $entity
	 * @param string $languageCode
	 *
	 * @return string
	 */
	abstract protected function getValue( $entity, $languageCode );

	/**
	 * Setting the value of the entity name by the given language
	 *
	 * @since 0.5
	 *
	 * @param Entity|null $entity
	 * @param string $languageCode
	 * @param string $value
	 *
	 * @return Summary
	 */
	abstract protected function setValue( $entity, $languageCode, $value );

}
