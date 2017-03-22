<?php

namespace Wikibase;

use Diff\Comparer\ComparableComparer;
use Diff\Differ\OrderedListDiffer;
use Html;
use IContextSource;
use Linker;
use MWException;
use OOUI\ButtonInputWidget;
use OOUI\ButtonWidget;
use OOUI\FieldLayout;
use OOUI\HtmlSnippet;
use OOUI\TextInputWidget;
use Page;
use Revision;
use Status;
use ValueFormatters\FormatterOptions;
use ValueFormatters\ValueFormatter;
use WebRequest;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Services\Lookup\EntityRetrievingTermLookup;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookupFactory;
use Wikibase\Repo\Content\EntityContentDiff;
use Wikibase\Repo\Diff\ClaimDiffer;
use Wikibase\Repo\Diff\ClaimDifferenceVisualizer;
use Wikibase\Repo\Diff\DifferencesSnakVisualizer;
use Wikibase\Repo\Diff\BasicEntityDiffVisualizer;
use Wikibase\Repo\WikibaseRepo;

/**
 * Handles the edit action for Wikibase entities.
 * This shows the forms for the undo and restore operations if requested.
 * Otherwise it will just show the normal entity view.
 *
 * @license GPL-2.0+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Jens Ohlig
 * @author Daniel Kinzler
 */
class EditEntityAction extends ViewEntityAction {

	/**
	 * @var BasicEntityDiffVisualizer
	 */
	private $entityDiffVisualizer;

	/**
	 * @see Action::__construct
	 *
	 * @param Page $page
	 * @param IContextSource|null $context
	 */
	public function __construct( Page $page, IContextSource $context = null ) {
		parent::__construct( $page, $context );

		$languageCode = $this->getContext()->getLanguage()->getCode();

		//TODO: proper injection
		$options = new FormatterOptions( array(
			//TODO: fallback chain
			ValueFormatter::OPT_LANG => $languageCode
		) );

		$wikibaseRepo = WikibaseRepo::getDefaultInstance();

		$termLookup = new EntityRetrievingTermLookup( $wikibaseRepo->getEntityLookup() );
		$labelDescriptionLookupFactory = new LanguageFallbackLabelDescriptionLookupFactory(
			$wikibaseRepo->getLanguageFallbackChainFactory(),
			$termLookup
		);
		$labelDescriptionLookup = $labelDescriptionLookupFactory->newLabelDescriptionLookup(
			$this->getContext()->getLanguage(),
			array() // TODO: populate ids of entities to prefetch
		);

		$htmlFormatterFactory = $wikibaseRepo->getEntityIdHtmlLinkFormatterFactory();
		$entityIdFormatter = $htmlFormatterFactory->getEntityIdFormatter( $labelDescriptionLookup );

		$formatterFactory = $wikibaseRepo->getSnakFormatterFactory();
		$snakDetailsFormatter = $formatterFactory->getSnakFormatter( SnakFormatter::FORMAT_HTML_DIFF, $options );
		$snakBreadCrumbFormatter = $formatterFactory->getSnakFormatter( SnakFormatter::FORMAT_HTML, $options );

		$this->entityDiffVisualizer = new BasicEntityDiffVisualizer(
			$this->getContext(),
			new ClaimDiffer( new OrderedListDiffer( new ComparableComparer() ) ),
			new ClaimDifferenceVisualizer(
				new DifferencesSnakVisualizer(
					$entityIdFormatter,
					$snakDetailsFormatter,
					$snakBreadCrumbFormatter,
					$languageCode
				),
				$languageCode
			),
			$wikibaseRepo->getSiteLookup(),
			$entityIdFormatter
		);
	}

	/**
	 * @see Action::getName()
	 *
	 * @return string
	 */
	public function getName() {
		return 'edit';
	}

	/**
	 * Show an error page if the user is not allowed to perform the given action.
	 *
	 * @param string $action The action to check
	 *
	 * @return bool true if there were permission errors
	 */
	protected function showPermissionError( $action ) {
		$rigor = $this->getRequest()->wasPosted() ? 'secure' : 'full';

		if ( !$this->getTitle()->userCan( $action, $this->getUser(), $rigor ) ) {
			$this->getOutput()->showPermissionsErrorPage(
				[ $this->getTitle()
					->getUserPermissionsErrors( $action, $this->getUser(), $rigor ) ],
				$action
			);

			return true;
		}

		return false;
	}

	/**
	 * Loads the revisions specified by the web request and returns them as a three element array
	 * wrapped in a Status object. If any error arises, it will be reported using the status object.
	 *
	 * @return Status A Status object containing an array with three revisions, array(
	 * $olderRevision, $newerRevision, $latestRevision ).
	 * @throws MWException if the page's latest revision cannot be loaded
	 */
	protected function loadRevisions() {
		$latestRevId = $this->getTitle()->getLatestRevID();

		if ( $latestRevId === 0 ) {
			// XXX: Better message
			return Status::newFatal( 'missing-article', $this->getTitle()->getPrefixedText(), '' );
		}

		$latestRevision = Revision::newFromId( $latestRevId );

		if ( !$latestRevId ) {
			throw new MWException( "latest revision not found: $latestRevId" );
		}

		return $this->getStatus( $this->getRequest(), $latestRevision );
	}

	/**
	 * @param WebRequest $req
	 * @param Revision $latestRevision
	 *
	 * @return Status
	 */
	private function getStatus( WebRequest $req, Revision $latestRevision ) {
		if ( $req->getCheck( 'restore' ) ) { // nearly the same as undoafter without undo
			$olderRevision = Revision::newFromId( $req->getInt( 'restore' ) );

			if ( !$olderRevision ) {
				return Status::newFatal( 'undo-norev', $req->getInt( 'restore' ) );
			}

			// ignore undo, even if set
			$newerRevision = $latestRevision;
		} elseif ( $req->getCheck( 'undo' ) ) {
			$newerRevision = Revision::newFromId( $req->getInt( 'undo' ) );

			if ( !$newerRevision ) {
				return Status::newFatal( 'undo-norev', $req->getInt( 'undo' ) );
			}

			if ( $req->getCheck( 'undoafter' ) ) {
				$olderRevision = Revision::newFromId( $req->getInt( 'undoafter' ) );

				if ( !$olderRevision ) {
					return Status::newFatal( 'undo-norev', $req->getInt( 'undoafter' ) );
				}
			} else {
				$olderRevision = $newerRevision->getPrevious();

				if ( !$olderRevision ) {
					return Status::newFatal( 'wikibase-undo-firstrev' );
				}
			}
		} elseif ( $req->getCheck( 'undoafter' ) ) {
			$olderRevision = Revision::newFromId( $req->getInt( 'undoafter' ) );

			if ( !$olderRevision ) {
				return Status::newFatal( 'undo-norev', $req->getInt( 'undo' ) );
			}

			// we already know that undo is not set
			$newerRevision = $latestRevision;
		} else {
			return Status::newFatal( 'edit_form_incomplete' ); //XXX: better message?
		}

		if ( $olderRevision->getId() == $newerRevision->getId() ) {
			return Status::newFatal( 'wikibase-undo-samerev', $this->getTitle() );
		}

		if ( $newerRevision->getPage() != $latestRevision->getPage() ) {
			return Status::newFatal( 'wikibase-undo-badpage', $this->getTitle(), $newerRevision->getId() );
		}

		if ( $olderRevision->getPage() != $latestRevision->getPage() ) {
			return Status::newFatal( 'wikibase-undo-badpage', $this->getTitle(), $olderRevision->getId() );
		}

		if ( $olderRevision->getContent() === null ) {
			return Status::newFatal( 'wikibase-undo-nocontent', $this->getTitle(), $olderRevision->getId() );
		}

		if ( $newerRevision->getContent() === null ) {
			return Status::newFatal( 'wikibase-undo-nocontent', $this->getTitle(), $newerRevision->getId() );
		}

		if ( $latestRevision->getContent() === null ) {
			return Status::newFatal( 'wikibase-undo-nocontent', $this->getTitle(), $latestRevision->getId() );
		}

		return Status::newGood( array( $olderRevision, $newerRevision, $latestRevision ) );
	}

	/**
	 * Output an error page showing the given status
	 *
	 * @param Status $status The status to report.
	 */
	protected function showUndoErrorPage( Status $status ) {
		$this->getOutput()->prepareErrorPage(
			$this->msg( 'wikibase-undo-revision-error' ),
			$this->msg( 'errorpagetitle' )
		);

		$this->getOutput()->addHTML( $status->getMessage()->parse() );

		$this->getOutput()->returnToMain();
	}

	/**
	 * @see FormlessAction::show
	 *
	 * Calls parent show() action to just display the entity, unless an undo action is requested.
	 */
	public function show() {
		$req = $this->getRequest();

		if ( $req->getCheck( 'undo' ) || $req->getCheck( 'undoafter' ) || $req->getCheck( 'restore' ) ) {
			$this->showUndoForm();
		} else {
			parent::show();
		}
	}

	private function showUndoForm() {
		$this->getOutput()->enableOOUI();
		$req = $this->getRequest();

		if ( $this->showPermissionError( 'read' ) || $this->showPermissionError( 'edit' ) ) {
			return;
		}

		$revisions = $this->loadRevisions();
		if ( !$revisions->isOK() ) {
			$this->showUndoErrorPage( $revisions );
			return;
		}

		/**
		 * @var Revision $olderRevision
		 * @var Revision $newerRevision
		 * @var Revision $latestRevision
		 */
		list( $olderRevision, $newerRevision, $latestRevision ) = $revisions->getValue();

		/**
		 * @var EntityContent $olderContent
		 * @var EntityContent $newerContent
		 * @var EntityContent $latestContent
		 */
		$olderContent = $olderRevision->getContent();
		$newerContent = $newerRevision->getContent();
		$latestContent = $latestRevision->getContent();

		$restore = $req->getCheck( 'restore' );

		$this->getOutput()->setPageTitle(
			$this->msg(
				$restore ? 'wikibase-restore-title' : 'wikibase-undo-title',
				$this->getTitleText(),
				$olderRevision->getId(),
				$newerRevision->getId()
			)
		);

		// diff from newer to older
		$diff = $newerContent->getDiff( $olderContent );

		if ( $newerContent->getEntityRedirect() === null ) {
			$entityId = $newerContent->getEntity()->getId();
		} else {
			$entityId = $newerContent->getEntityRedirect()->getTargetId();
		}

		if ( $newerRevision->getId() == $latestRevision->getId() ) {
			// if the revision to undo is the latest revision, then there can be no conflicts
			$appDiff = $diff;
		} else {
			$patchedCurrent = $latestContent->getPatchedCopy( $diff );
			$appDiff = $latestContent->getDiff( $patchedCurrent );
		}

		if ( !$restore ) {
			$omitted = $diff->count() - $appDiff->count();

			if ( !$appDiff->isEmpty() ) {
				$this->getOutput()->addHTML( Html::openElement( 'p' ) );
				$this->getOutput()->addWikiMsg( $omitted > 0 ? 'wikibase-partial-undo' : 'undo-success' );
				$this->getOutput()->addHTML( Html::closeElement( 'p' ) );
			}

			if ( $omitted > 0 ) {
				$this->getOutput()->addHTML( Html::openElement( 'p' ) );
				$this->getOutput()->addWikiMsg( 'wikibase-omitted-undo-ops', $omitted );
				$this->getOutput()->addHTML( Html::closeElement( 'p' ) );
			}
		}

		if ( $appDiff->isEmpty() ) {
			$this->getOutput()->addHTML( Html::openElement( 'p' ) );
			$this->getOutput()->addWikiMsg( 'wikibase-empty-undo' );
			$this->getOutput()->addHTML( Html::closeElement( 'p' ) );
			return;
		}

		$this->displayUndoDiff( $appDiff, $entityId );

		if ( $restore ) {
			$this->showConfirmationForm();
		} else {
			$this->showConfirmationForm( $newerRevision->getId() );
		}
	}

	/**
	 * Used for overriding the page HTML title with the label, if available, or else the id.
	 * This is passed via parser output and output page to save overhead on view / edit actions.
	 *
	 * @return string
	 */
	private function getTitleText() {
		$meta = $this->getOutput()->getProperty( 'wikibase-meta-tags' );

		return isset( $meta['title'] ) ? $meta['title'] : $this->getTitle()->getPrefixedText();
	}

	/**
	 * Returns a cancel link back to viewing the entity's page
	 *
	 * @return string
	 */
	private function getCancelLink() {
		return ( new ButtonWidget( [
			'id' => 'mw-editform-cancel',
			'href' => $this->getContext()->getTitle()->getLocalURL(),
			'label' => $this->msg( 'cancel' )->parse(),
			'framed' => false,
			'flags' => 'destructive'
		] ) )->toString();
	}

	/**
	 * Add style sheets and supporting JS for diff display.
	 */
	private function showDiffStyle() {
		$this->getOutput()->addModuleStyles( 'mediawiki.diff.styles' );
	}

	/**
	 * Generate standard summary input and label (wgSummary), compatible to EditPage.
	 *
	 * @param string $labelText The html to place inside the label
	 *
	 * @return string HTML
	 */
	private function getSummaryInput( $labelText ) {
		$inputAttrs = [
			'name' => 'wpSummary',
			'maxLength' => 200,
			'size' => 60,
			'spellcheck' => 'true',
		] + Linker::tooltipAndAccesskeyAttribs( 'summary' );
		return ( new FieldLayout(
			new TextInputWidget( $inputAttrs ),
			[
				'label' => new HtmlSnippet( $labelText ),
				'align' => 'top',
				'id' => 'wpSummaryLabel',
				'classes' => [ 'mw-summary' ],
			]
		) )->toString();
	}

	/**
	 * @param EntityContentDiff $diff
	 * @param EntityId $entityId
	 */
	private function displayUndoDiff( EntityContentDiff $diff, EntityId $entityId ) {
		$tableClass = 'diff diff-contentalign-' . htmlspecialchars( $this->getTitle()->getPageLanguage()->alignStart() );

		$this->getOutput()->addHTML( Html::openElement( 'table', array( 'class' => $tableClass ) ) );

		$this->getOutput()->addHTML( '<colgroup>'
			. '<col class="diff-marker"><col class="diff-content">'
			. '<col class="diff-marker"><col class="diff-content">'
			. '</colgroup>' );
		$this->getOutput()->addHTML( Html::openElement( 'tbody' ) );

		$old = $this->msg( 'currentrev' )->parse();
		$new = $this->msg( 'yourtext' )->parse(); //XXX: better message?

		$this->getOutput()->addHTML( Html::openElement( 'tr', array( 'style' => 'vertical-align: top;' ) ) );
		$this->getOutput()->addHTML(
			Html::rawElement( 'td', array( 'colspan' => '2' ),
				Html::rawElement( 'div', array( 'id' => 'mw-diff-otitle1' ), $old )
			)
		);
		$this->getOutput()->addHTML(
			Html::rawElement( 'td', array( 'colspan' => '2' ),
				Html::rawElement( 'div', array( 'id' => 'mw-diff-ntitle1' ), $new )
			)
		);
		$this->getOutput()->addHTML( Html::closeElement( 'tr' ) );

		$this->getOutput()->addHTML(
			$this->entityDiffVisualizer->visualizeEntityContentDiff( $diff, $entityId )
		);

		$this->getOutput()->addHTML( Html::closeElement( 'tbody' ) );
		$this->getOutput()->addHTML( Html::closeElement( 'table' ) );

		$this->showDiffStyle();
	}

	/**
	 * @return string HTML
	 */
	private function getEditButton() {
		return ( new ButtonInputWidget( [
				'name' => 'wpSave',
				'value' => $this->msg( 'savearticle' )->text(),
				'label' => $this->msg( 'savearticle' )->text(),
				'accesskey' => $this->msg( 'accesskey-save' )->text(),
				'flags' => [ 'primary', 'progressive' ],
				'type' => 'submit',
				'title' => $this->msg( 'tooltip-save' )->text() . ' [' . $this->msg( 'accesskey-save' )->text() . ']',
			] ) )->toString();
	}

	/**
	 * Shows a form that can be used to confirm the requested undo/restore action.
	 *
	 * @param int $undidRevision
	 */
	private function showConfirmationForm( $undidRevision = 0 ) {
		$req = $this->getRequest();

		$args = array(
			'action' => 'submit',
		);

		if ( $req->getInt( 'undo' ) ) {
			$args[ 'undo' ] = $req->getInt( 'undo' );
		}

		if ( $req->getInt( 'undoafter' ) ) {
			$args[ 'undoafter' ] = $req->getInt( 'undoafter' );
		}

		if ( $req->getInt( 'restore' ) ) {
			$args[ 'restore' ] = $req->getInt( 'restore' );
		}

		$actionUrl = $this->getTitle()->getLocalURL( $args );

		$this->getOutput()->addHTML( Html::openElement( 'div', array( 'style' => 'margin-top: 1em;' ) ) );

		$this->getOutput()->addHTML( Html::openElement( 'form', array(
			'id' => 'undo',
			'name' => 'undo',
			'method' => 'post',
			'action' => $actionUrl,
			'enctype' => 'multipart/form-data' ) ) );

		$this->getOutput()->addHTML( "<div class='editOptions'>\n" );

		$labelText = $this->msg( 'wikibase-summary-generated' )->text();
		$this->getOutput()->addHTML( $this->getSummaryInput( $labelText ) );
		$this->getOutput()->addHTML( Html::rawElement( 'br' ) );
		$this->getOutput()->addHTML( "<div class='editButtons'>\n" );
		$this->getOutput()->addHTML( $this->getEditButton() . "\n" );
		$this->getOutput()->addHTML( $this->getCancelLink() );

		$this->getOutput()->addHTML( "</div><!-- editButtons -->\n</div><!-- editOptions -->\n" );

		$hidden = array(
			'wpEditToken' => $this->getUser()->getEditToken(),
			'wpBaseRev' => $this->getTitle()->getLatestRevID(),
		);
		if ( !empty( $undidRevision ) ) {
			$hidden['wpUndidRevision'] = $undidRevision;
		}
		foreach ( $hidden as $name => $value ) {
			$this->getOutput()->addHTML( "\n" . Html::hidden( $name, $value ) . "\n" );
		}

		$this->getOutput()->addHTML( Html::closeElement( 'form' ) );
		$this->getOutput()->addHTML( Html::closeElement( 'div' ) );
	}

}
