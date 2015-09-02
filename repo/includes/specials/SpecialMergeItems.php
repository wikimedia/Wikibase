<?php

namespace Wikibase\Repo\Specials;

use Exception;
use Html;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\EntityRevision;
use Wikibase\Lib\UserInputException;
use Wikibase\Repo\Interactors\ItemMergeInteractor;
use Wikibase\Repo\Interactors\TokenCheckInteractor;
use Wikibase\Repo\Localizer\ExceptionLocalizer;
use Wikibase\Repo\WikibaseRepo;

/**
 * Special page for merging one item to another.
 *
 * @since 0.5
 * @licence GNU GPL v2+
 * @author Bene* < benestar.wikimedia@gmail.com >
 * @author Daniel Kinzler
 */
class SpecialMergeItems extends SpecialWikibasePage {

	/**
	 * @var EntityIdParser
	 */
	private $idParser;

	/**
	 * @var ExceptionLocalizer
	 */
	private $exceptionLocalizer;

	/**
	 * @var ItemMergeInteractor
	 */
	private $interactor;

	/**
	 * @var TokenCheckInteractor
	 */
	private $tokenCheck;

	/**
	 * @since 0.5
	 */
	public function __construct() {
		parent::__construct( 'MergeItems', 'item-merge' );

		$wikibaseRepo = WikibaseRepo::getDefaultInstance();

		$this->initServices(
			$wikibaseRepo->getEntityIdParser(),
			$wikibaseRepo->getExceptionLocalizer(),
			new TokenCheckInteractor(
				$this->getUser()
			),
			new ItemMergeInteractor(
				$wikibaseRepo->getChangeOpFactoryProvider()->getMergeChangeOpFactory(),
				$wikibaseRepo->getEntityRevisionLookup( 'uncached' ),
				$wikibaseRepo->getEntityStore(),
				$wikibaseRepo->getEntityPermissionChecker(),
				$wikibaseRepo->getSummaryFormatter(),
				$this->getUser(),
				$wikibaseRepo->newRedirectCreationInteractor( $this->getUser(), $this->getContext() )
			)
		);
	}

	public function initServices(
		EntityIdParser $idParser,
		ExceptionLocalizer $exceptionLocalizer,
		TokenCheckInteractor $tokenCheck,
		ItemMergeInteractor $interactor
	) {
		$this->idParser = $idParser;
		$this->exceptionLocalizer = $exceptionLocalizer;
		$this->tokenCheck = $tokenCheck;
		$this->interactor = $interactor;
	}

	/**
	 * @param string $name
	 *
	 * @return ItemId|null
	 * @throws UserInputException
	 */
	private function getItemIdParam( $name ) {
		$rawId = $this->getTextParam( $name );

		if ( $rawId === '' ) {
			return null;
		}

		try {
			$id = $this->idParser->parse( $rawId );

			if ( !( $id instanceof ItemId ) ) {
				throw new UserInputException(
					'wikibase-itemmerge-not-item',
					array( $name ),
					'Id does not refer to an item: ' . $name
				);
			}

			return $id;
		} catch ( EntityIdParsingException $ex ) {
			throw new UserInputException(
				'wikibase-wikibaserepopage-invalid-id',
				array( $rawId ),
				'Entity id is not valid'
			);
		}
	}

	private function getStringListParam( $name ) {
		$list = $this->getTextParam( $name );

		return $list === '' ? array() : explode( '|', $list );
	}

	private function getTextParam( $name ) {
		$value = $this->getRequest()->getText( $name, '' );
		return trim( $value );
	}

	/**
	 * @see SpecialWikibasePage::execute
	 *
	 * @since 0.5
	 *
	 * @param string|null $subPage
	 */
	public function execute( $subPage ) {
		parent::execute( $subPage );

		$this->checkReadOnly();

		$this->setHeaders();
		$this->outputHeader();

		try {
			$fromId = $this->getItemIdParam( 'fromid' );
			$toId = $this->getItemIdParam( 'toid' );

			$ignoreConflicts = $this->getStringListParam( 'ignoreconflicts' );
			$summary = $this->getTextParam( 'summary' );

			if ( $fromId && $toId ) {
				$this->mergeItems( $fromId, $toId, $ignoreConflicts, $summary );
			}
		} catch ( Exception $ex ) {
			$this->showExceptionMessage( $ex );
		}

		$this->createForm();
	}

	protected function showExceptionMessage( Exception $ex ) {
		$msg = $this->exceptionLocalizer->getExceptionMessage( $ex );

		$this->showErrorHTML( $msg->parse(), 'error' );

		// Report chained exceptions recursively
		if ( $ex->getPrevious() ) {
			$this->showExceptionMessage( $ex->getPrevious() );
		}
	}

	/**
	 * @param ItemId $fromId
	 * @param ItemId $toId
	 * @param string[] $ignoreConflicts
	 * @param string $summary
	 */
	private function mergeItems( ItemId $fromId, ItemId $toId, array $ignoreConflicts, $summary ) {
		$this->tokenCheck->checkRequestToken( $this->getRequest(), 'token' );

		/** @var EntityRevision $newRevisionFrom  */
		/** @var EntityRevision $newRevisionTo */
		list( $newRevisionFrom, $newRevisionTo, )
			= $this->interactor->mergeItems( $fromId, $toId, $ignoreConflicts, $summary );

		//XXX: might be nicer to pass pre-rendered links as parameters
		$this->getOutput()->addWikiMsg(
			'wikibase-mergeitems-success',
			$fromId->getSerialization(),
			$newRevisionFrom->getRevisionId(),
			$toId->getSerialization(),
			$newRevisionTo->getRevisionId() );
	}

	/**
	 * Creates the HTML form for merging two items.
	 */
	protected function createForm() {
		$this->getOutput()->addModuleStyles( array( 'wikibase.special' ) );

		if ( $this->getUser()->isAnon() ) {
			$this->getOutput()->addHTML(
				Html::rawElement(
					'p',
					array( 'class' => 'warning' ),
					$this->msg(
						'wikibase-anonymouseditwarning',
						$this->msg( 'wikibase-entity-item' )->text()
					)->parse()
				)
			);
		}

		// Form header
		$this->getOutput()->addHTML(
			Html::openElement(
				'form',
				array(
					'method' => 'post',
					'action' => $this->getPageTitle()->getFullUrl(),
					'name' => 'mergeitems',
					'id' => 'wb-mergeitems-form1',
					'class' => 'wb-form'
				)
			)
			. Html::openElement(
				'fieldset',
				array( 'class' => 'wb-fieldset' )
			)
			. Html::element(
				'legend',
				array( 'class' => 'wb-legend' ),
				$this->msg( 'special-mergeitems' )->text()
			)
		);

		// Form elements
		$this->getOutput()->addHTML( $this->getFormElements() );

		// Form body
		$this->getOutput()->addHTML(
			Html::element( 'br' )
			. Html::input(
				'wikibase-mergeitems-submit',
				$this->msg( 'wikibase-mergeitems-submit' )->text(),
				'submit',
				array(
					'id' => 'wb-mergeitems-submit',
					'class' => 'wb-button'
				)
			)
			. Html::input(
				'token',
				$this->getUser()->getEditToken(),
				'hidden'
			)
			. Html::closeElement( 'fieldset' )
			. Html::closeElement( 'form' )
		);
	}

	/**
	 * Returns the form elements.
	 *
	 * @return string
	 */
	protected function getFormElements() {
		return Html::rawElement(
			'p',
			array(),
			// Message: wikibase-mergeitems-intro
			$this->msg( 'wikibase-mergeitems-intro' )->parse()
		)
		. Html::element(
			'label',
			array(
				'for' => 'wb-mergeitems-fromid',
				'class' => 'wb-label'
			),
			$this->msg( 'wikibase-mergeitems-fromid' )->text()
		)
		. Html::input(
			'fromid',
			$this->getRequest()->getVal( 'fromid' ),
			'text',
			array(
				'class' => 'wb-input',
				'id' => 'wb-mergeitems-fromid'
			)
		)
		. Html::element( 'br' )
		. Html::element(
			'label',
			array(
				'for' => 'wb-mergeitems-toid',
				'class' => 'wb-label'
			),
			$this->msg( 'wikibase-mergeitems-toid' )->text()
		)
		. Html::input(
			'toid',
			$this->getRequest()->getVal( 'toid' ),
			'text',
			array(
				'class' => 'wb-input',
				'id' => 'wb-mergeitems-toid'
			)
		);
		// TODO: Selector for ignoreconflicts
	}

}
