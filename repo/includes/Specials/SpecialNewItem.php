<?php

namespace Wikibase\Repo\Specials;

use OutputPage;
use SiteLookup;
use Status;
use WebRequest;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Term\Term;
use Wikibase\Repo\Specials\HTMLForm\HTMLTrimmedTextField;
use Wikibase\Summary;

/**
 * Page for creating new Wikibase items.
 *
 * @since 0.1
 *
 * @license GPL-2.0+
 * @author John Erling Blad < jeblad@gmail.com >
 */
class SpecialNewItem extends SpecialNewEntity {

	const FIELD_LANG = 'lang';
	const FIELD_LABEL = 'label';
	const FIELD_DESCRIPTION = 'description';
	const FIELD_ALIASES = 'aliases';
	const FIELD_SITE = 'site';
	const FIELD_PAGE = 'page';

	/**
	 * @since 0.1
	 * @param SiteLookup|null $siteStore
	 */
	public function __construct( SiteLookup $siteStore = null ) {
		parent::__construct( 'NewItem' );
		if ( $siteStore ) {
			$this->siteLookup = $siteStore;
		}
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * @return bool
	 */
	private function isSiteLinkProvided( WebRequest $request ) {
		return $request->getVal( self::FIELD_SITE ) !== null
			   && $request->getVal( self::FIELD_PAGE ) !== null;
	}

	/**
	 * @param array $formData
	 * @return Item
	 */
	protected function createEntityFromFormData( array $formData ) {
		$languageCode = $formData[ self::FIELD_LANG ];

		$item = new Item();
		$fingerprint = $item->getFingerprint();
		$fingerprint->setLabel( $languageCode, $formData[ self::FIELD_LABEL ] );
		$fingerprint->setDescription( $languageCode, $formData[ self::FIELD_DESCRIPTION ] );

		$aliases = explode( '|', (string)$formData[ self::FIELD_ALIASES ] );
		$aliases = array_map( [ $this->stringNormalizer, 'trimToNFC' ], $aliases );
		$fingerprint->setAliasGroup( $languageCode, $aliases );

		if ( isset( $formData[ self::FIELD_SITE ] ) ) {
			$site = $this->siteLookup->getSite( $formData[ self::FIELD_SITE ] );
			$normalizedPageName = $site->normalizePageName( $formData[ self::FIELD_PAGE ] );

			$item->getSiteLinkList()->addNewSiteLink( $site->getGlobalId(), $normalizedPageName );
		}

		return $item;
	}

	/**
	 * @return array[]
	 */
	protected function getFormFields() {
		$langCode = $this->getLanguage()->getCode();

		$formFields = [
			self::FIELD_LANG => [
				'name' => self::FIELD_LANG,
				'options' => $this->getLanguageOptions(),
				'default' => $langCode,
				'type' => 'combobox',
				'id' => 'wb-newentity-language',
				'filter-callback' => [ $this->stringNormalizer, 'trimToNFC' ],
				'validation-callback' => function ( $language ) {
					if ( !in_array( $language, $this->languageCodes ) ) {
						return [ $this->msg( 'wikibase-newitem-not-recognized-language' )->text() ];
					}

					return true;
				},
				'label-message' => 'wikibase-newentity-language',
			],
			self::FIELD_LABEL => [
				'name' => self::FIELD_LABEL,
				'default' => isset( $this->parts[0] ) ? $this->parts[0] : '',
				'class' => HTMLTrimmedTextField::class,
				'id' => 'wb-newentity-label',
				'placeholder-message' => 'wikibase-label-edit-placeholder',
				'label-message' => 'wikibase-newentity-label',
			],
			self::FIELD_DESCRIPTION => [
				'name' => self::FIELD_DESCRIPTION,
				'default' => isset( $this->parts[1] ) ? $this->parts[1] : '',
				'class' => HTMLTrimmedTextField::class,
				'id' => 'wb-newentity-description',
				'placeholder-message' => 'wikibase-description-edit-placeholder',
				'label-message' => 'wikibase-newentity-description',
			],
			self::FIELD_ALIASES => [
				'name' => self::FIELD_ALIASES,
				'class' => HTMLTrimmedTextField::class,
				'id' => 'wb-newentity-aliases',
				'placeholder-message' => 'wikibase-aliases-edit-placeholder',
				'label-message' => 'wikibase-newentity-aliases',
			],
		];

		if ( $this->isSiteLinkProvided( $this->getRequest() ) ) {
			$formFields[ self::FIELD_SITE ] = [
				'name' => self::FIELD_SITE,
				'default' => $this->getRequest()->getVal( self::FIELD_SITE ),
				'type' => 'text',
				'id' => 'wb-newitem-site',
				'readonly' => 'readonly',
				'validation-callback' => function ( $siteId, $formData ) {
					$site = $this->siteLookup->getSite( $siteId );

					if ( $site === null ) {
						return [ $this->msg( 'wikibase-newitem-not-recognized-siteid' )->text() ];
					}

					return true;
				},
				'label-message' => 'wikibase-newitem-site'
			];

			$formFields[ self::FIELD_PAGE ] = [
				'name' => self::FIELD_PAGE,
				'default' => $this->getRequest()->getVal( self::FIELD_PAGE ),
				'type' => 'text',
				'id' => 'wb-newitem-page',
				'readonly' => 'readonly',
				'validation-callback' => function ( $pageName, $formData ) {
					$siteId = $formData['site'];
					$site = $this->siteLookup->getSite( $siteId );
					if ( $site === null ) {
						return true;
					}

					$normalizedPageName = $site->normalizePageName( $pageName );
					if ( $normalizedPageName === false ) {
						return [
							$this->msg(
								'wikibase-newitem-no-external-page',
								$siteId,
								$pageName
							)->text(),
						];
					}

					return true;
				},
				'label-message' => 'wikibase-newitem-page'
			];
		}

		return $formFields;
	}

	/**
	 * @see SpecialNewEntity::getLegend
	 *
	 * @return string
	 */
	protected function getLegend() {
		return $this->msg( 'wikibase-newitem-fieldset' );
	}

	/**
	 * @see SpecialCreateEntity::getWarnings
	 *
	 * @return string[]
	 */
	protected function getWarnings() {
		if ( $this->getUser()->isAnon() ) {
			return [
				$this->msg( 'wikibase-anonymouseditwarning', $this->msg( 'wikibase-entity-item' ) ),
			];
		}

		return [];
	}

	/**
	 * @param array $formData
	 *
	 * @return Status
	 */
	protected function validateFormData( array $formData ) {
		if ( $formData[ self::FIELD_LABEL ] == ''
			 && $formData[ self::FIELD_DESCRIPTION ] == ''
			 && $formData[ self::FIELD_ALIASES ] == ''
		) {
			return Status::newFatal( 'wikibase-newitem-insufficient-data' );
		}

		return Status::newGood();
	}

	/**
	 * @param Item $item
	 *
	 * @return Summary
	 */
	protected function createSummary( $item ) {
		$uiLanguageCode = $this->getLanguage()->getCode();

		$summary = new Summary( 'wbeditentity', 'create' );
		$summary->setLanguage( $uiLanguageCode );
		/** @var Term|null $labelTerm */
		$labelTerm = $item->getFingerprint()->getLabels()->getIterator()->current();
		/** @var Term|null $descriptionTerm */
		$descriptionTerm = $item->getFingerprint()->getDescriptions()->getIterator()->current();
		$summary->addAutoSummaryArgs(
			$labelTerm ? $labelTerm->getText() : '',
			$descriptionTerm ? $descriptionTerm->getText() : ''
		);

		return $summary;
	}

	protected function displayBeforeForm( OutputPage $output ) {
		parent::displayBeforeForm( $output );
		$output->addModules( 'wikibase.special.languageLabelDescriptionAliases' );
	}

}
