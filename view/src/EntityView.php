<?php

namespace Wikibase\View;

use Html;
use InvalidArgumentException;
use Language;
use ParserOutput;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Term\FingerprintProvider;
use Wikibase\EntityRevision;
use Wikibase\View\Template\TemplateFactory;

/**
 * Base class for creating views for all different kinds of Wikibase\Entity.
 * For the Wikibase\Entity this basically is what the Parser is for WikitextContent.
 *
 * @todo  We might want to re-design this at a later point, designing this as a more generic and encapsulated rendering
 *        of DataValue instances instead of having functions here for generating different parts of the HTML. Right now
 *        these functions require an EntityRevision while a DataValue (if it were implemented) should be sufficient.
 *
 * @since 0.1
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki at snater.com >
 * @author Daniel Werner
 * @author Daniel Kinzler
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
abstract class EntityView {

	/**
	 * @var TemplateFactory
	 */
	protected $templateFactory;

	/**
	 * @var EntityTermsView
	 */
	private $entityTermsView;

	/**
	 * @var Language
	 */
	protected $language;

	/**
	 * @var TextInjector
	 */
	private $textInjector;

	/**
	 * @param TemplateFactory $templateFactory
	 * @param EntityTermsView $entityTermsView
	 * @param Language $language
	 */
	public function __construct(
		TemplateFactory $templateFactory,
		EntityTermsView $entityTermsView,
		Language $language
	) {
		$this->entityTermsView = $entityTermsView;
		$this->language = $language;

		$this->textInjector = new TextInjector();
		$this->templateFactory = $templateFactory;
	}

	/**
	 * Returns the placeholder map build while generating HTML.
	 * The map returned here may be used with TextInjector.
	 *
	 * @return array[] string -> array
	 */
	public function getPlaceholders() {
		return $this->textInjector->getMarkers();
	}

	/**
	 * Builds and returns the HTML representing a whole WikibaseEntity.
	 *
	 * @note: The HTML returned by this method may contain placeholders. Such placeholders can be
	 * expanded with the help of TextInjector::inject() calling back to
	 * EntityViewPlaceholderExpander::getExtraUserLanguages()
	 * @note: In order to keep the list of placeholders small, this calls resetPlaceholders().
	 *
	 * @since 0.1
	 *
	 * @param EntityRevision $entityRevision the entity to render
	 *
	 * @return string HTML
	 */
	public function getHtml( EntityRevision $entityRevision ) {
		$entity = $entityRevision->getEntity();

		$entityId = $entity->getId() ?: 'new'; // if id is not set, use 'new' suffix for css classes

		$html = $this->templateFactory->render( 'wikibase-entityview',
			$entity->getType(),
			$entityId,
			$this->language->getCode(),
			$this->language->getDir(),
			$this->getMainHtml( $entityRevision ),
			$this->getSideHtml( $entityRevision )
		);

		return $html;
	}

	/**
	 * Returns the html used for the title of the page.
	 * @see ParserOutput::setDisplayTitle
	 *
	 * @since 0.5
	 *
	 * @param EntityRevision $entityRevision
	 *
	 * @return string HTML
	 */
	public function getTitleHtml( EntityRevision $entityRevision ) {
		$entity = $entityRevision->getEntity();

		if ( $entity instanceof FingerprintProvider ) {
			return $this->entityTermsView->getTitleHtml(
				$entity->getFingerprint(),
				$entity->getId()
			);
		}

		return '';
	}

	/**
	 * Builds and returns the HTML to be put into the main container of an entity's HTML structure.
	 *
	 * @param EntityRevision $entityRevision
	 *
	 * @throws InvalidArgumentException
	 * @return string
	 */
	protected function getMainHtml( EntityRevision $entityRevision ) {
		$entity = $entityRevision->getEntity();

		$html = $this->getHtmlForFingerprint(
			$entity,
			$this->getHtmlForTermBox( $entityRevision )
		);

		$html .= $this->templateFactory->render( 'wikibase-toc' );

		return $html;
	}

	/**
	 * Builds and Returns HTML to put into the sidebar of the entity's HTML structure.
	 *
	 * @param EntityRevision $entityRevision
	 *
	 * @return string
	 */
	protected function getSideHtml( EntityRevision $entityRevision ) {
		return '';
	}

	/**
	 * Builds and returns the HTML for the entity's fingerprint.
	 *
	 * @param Entity $entity
	 * @param string $termBoxHtml
	 *
	 * @return string
	 */
	private function getHtmlForFingerprint( Entity $entity, $termBoxHtml ) {
		return $this->entityTermsView->getHtml(
			$entity->getFingerprint(),
			$entity->getId(),
			$termBoxHtml,
			$this->textInjector
		);
	}

	/**
	 * @param EntityRevision $entityRevision
	 *
	 * @return string
	 */
	private function getHtmlForTermBox( EntityRevision $entityRevision ) {
		$entityId = $entityRevision->getEntity()->getId();

		if ( $entityId !== null ) {
			// Placeholder for a termbox for the present item.
			// EntityViewPlaceholderExpander must know about the parameters used here.
			return $this->textInjector->newMarker(
				'termbox',
				$entityId->getSerialization(),
				$entityRevision->getRevisionId()
			);
		}

		return '';
	}

}
