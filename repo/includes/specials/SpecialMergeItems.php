<?php

namespace Wikibase\Repo\Specials;

use Html;
use UserInputException;
use InvalidArgumentException;
use Wikibase\ChangeOp\ChangeOpFactory;
use Wikibase\ChangeOp\ChangeOpsMerge;
use Wikibase\ChangeOp\ChangeOpException;
use Wikibase\EntityRevision;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\LabelDescriptionDuplicateDetector;
use Wikibase\Summary;
use Wikibase\DataModel\Entity\EntityId;
use Status;

/**
 * Special page for merging one item to another.
 *
 * @since 0.5
 * @licence GNU GPL v2+
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class SpecialMergeItems extends SpecialWikibaseRepoPage {

	/**
	 * The item content to merge from.
	 *
	 * @var EntityRevision
	 */
	private $fromItemRevision;

	/**
	 * The item content to merge to.
	 *
	 * @var EntityRevision
	 */
	private $toItemRevision;

	/**
	 * The conflicts that should be ignored
	 *
	 * @var string[]
	 */
	private $ignoreConflicts;

	/**
	 * Constructor.
	 *
	 * @since 0.5
	 */
	public function __construct() {
		parent::__construct( 'MergeItems', 'item-merge' );
	}

	/**
	 * Main method
	 *
	 * @since 0.5
	 *
	 * @param string $subPage
	 *
	 * @return boolean
	 */
	public function execute( $subPage ) {
		if ( !parent::execute( $subPage ) ) {
			return false;
		}

		$this->checkPermissions();
		$this->checkBlocked();
		$this->checkReadOnly();

		$this->setHeaders();
		$this->outputHeader();

		try {
			$this->prepareArguments();
		} catch ( UserInputException $ex ) {
			$error = $this->msg( $ex->getKey(), $ex->getParams() )->parse();
			$this->showErrorHTML( $error );
		}

		/**
		 * @var Status $status
		 */
		$status = Status::newGood();

		if ( $this->modifyEntity( $status ) ) {
			if ( !$status->isGood() ) {
				$this->showErrorHTML( $this->msg('wikibase-special-mergeitems-error-prefix')->text() . ' ' . $status->getMessage() );
			} elseif ( $this->saveChanges() ) {
				return true;
			}
		}

		$this->createForm();

		return true;
	}

	/**
	 * Prepares the arguments.
	 */
	protected function prepareArguments() {
		$request = $this->getRequest();

		// Get from id
		$rawFromId = $request->getVal( 'fromid', null );
		$rawToId = $request->getVal( 'toid', null );

		if ( !$rawFromId || !$rawToId ) {
			return;
		}

		$fromId = $this->parseItemId( $rawFromId );
		$toId = $this->parseItemId( $rawToId );

		$this->fromItemRevision = $this->loadEntity( $fromId );
		$this->toItemRevision = $this->loadEntity( $toId );

		// Get ignore conflicts
		$ignoreConflicts = $request->getVal( 'ignoreconflicts', null );

		if ( $ignoreConflicts ) {
			$this->ignoreConflicts = explode( '|', 'ignoreconflicts' );
		} else {
			$this->ignoreConflicts = array();
		}
	}

	/**
	 * Modifies the entity.
	 *
	 * @param Status $status
	 *
	 * @return boolean
	 */
	protected function modifyEntity( Status $status ) {
		if ( $this->fromItemRevision === null || $this->toItemRevision === null ) {
			return false;
		}
		$sitelinkCache = WikibaseRepo::getDefaultInstance()->getStore()->newSiteLinkCache();
		$termIndex = WikibaseRepo::getDefaultInstance()->getStore()->getTermIndex();
		try {
			$changeOps = $this->changeOpFactory->newMergeOps(
				$this->fromItemRevision->getEntity(),
				$this->toItemRevision->getEntity(),
				$this->ignoreConflicts
			);
			$changeOps->apply();
		} catch( InvalidArgumentException $e ) {
			// caution, this does not return a message key but a hardcoded message
			$status->fatal( $e->getMessage() );
		} catch( ChangeOpException $e ) {
			// caution, this does not return a message key but a hardcoded message
			$status->fatal( $e->getMessage() );
		}
		return true;
	}

	/**
	 * Saves the changes made by the ChangeOps.
	 *
	 * @return boolean
	 */
	protected function saveChanges() {
		// remove the content from the "from" item
		$toSummary = $this->getSummary( 'to', $this->toItemRevision->getEntity()->getId() );
		$fromStatus = $this->saveEntity( $this->fromItemRevision->getEntity(), $toSummary, $this->getRequest()->getVal( 'wpEditToken' ) );

		if ( !$fromStatus->isOK() ) {
			$this->showErrorHTML( $fromStatus->getMessage() );
		} else {
			// add the content to the "to" item
			$fromSummary = $this->getSummary( 'from', $this->fromItemRevision->getEntity()->getId() );
			$toStatus = $this->saveEntity( $this->toItemRevision->getEntity(), $fromSummary, $this->getRequest()->getVal( 'wpEditToken' ) );

			if ( !$toStatus->isOK() ) {
				$this->showErrorHTML( $toStatus->getMessage() );
			} else {
				// Everything went well so redirect to the merged item
				// TODO: instead of redirecting, we should display a success message containing links to the merged items
				//       and the changes that were made as well as some instructions to undo the merge.
				$title = $this->getEntityTitle( $this->toItemRevision->getEntity()->getId() );
				$toEntityUrl = $title->getFullUrl();
				$this->getOutput()->redirect( $toEntityUrl );
				return true; // no need to create the form now
			}
		}
		return false;
	}

	/**
	 * Creates the summary.
	 *
	 * @param string $direction
	 * @param EntityId $id
	 *
	 * @return Summary
	 */
	protected function getSummary( $direction, $id ) {
		$summary = new Summary(
			'wbmergeitems',
			$direction,
			null,
			array( $id->getSerialization() )
		);
		return $summary;
	}

	/**
	 * Creates the HTML form for merging two items.
	 */
	protected function createForm() {
		$this->getOutput()->addModuleStyles( array( 'wikibase.special' ) );

		if ( $this->getUser()->isAnon() ) {
			$this->showErrorHTML(
				$this->msg(
					'wikibase-anonymouseditwarning',
					$this->msg( 'wikibase-entity-item' )->text()
				)->parse(),
				'warning'
			);
		}

		// Form header
		$this->getOutput()->addHTML(
			Html::openElement(
				'form',
				array(
					'method' => 'post',
					'action' => $this->getPageTitle()->getFullUrl(),
					'name' => strtolower( $this->getName() ),
					'id' => 'wb-' . strtolower( $this->getName() ) . '-form1',
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
				// Message: special-mergeitems
				$this->msg( 'special-' . strtolower( $this->getName() ) )->text()
			)
		);

		// Form elements
		$this->getOutput()->addHTML( $this->getFormElements() );

		// Form body
		$this->getOutput()->addHTML(
			Html::input(
				'wikibase-' . strtolower( $this->getName() ) . '-submit',
				// Message: wikibase-mergeitems-submit
				$this->msg( 'wikibase-' . strtolower( $this->getName() ) . '-submit' )->text(),
				'submit',
				array(
					'id' => 'wb-' . strtolower( $this->getName() ) . '-submit',
					'class' => 'wb-button'
				)
			)
			. Html::input(
				'wpEditToken',
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
			$this->msg( 'wikibase-' . strtolower( $this->getName() ) . '-intro' )->parse()
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
		)
		. Html::element( 'br' );
		// TODO: Here should be a way to easily ignore conflicts
	}
}
