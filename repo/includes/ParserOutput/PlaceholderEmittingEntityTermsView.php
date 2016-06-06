<?php

namespace Wikibase\Repo\ParserOutput;

use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Services\Lookup\LabelDescriptionLookup;
use Wikibase\DataModel\Term\AliasesProvider;
use Wikibase\DataModel\Term\DescriptionsProvider;
use Wikibase\DataModel\Term\LabelsProvider;
use Wikibase\View\EditSectionGenerator;
use Wikibase\View\HtmlTermRenderer;
use Wikibase\View\LocalizedTextProvider;
use Wikibase\View\SimpleEntityTermsView;
use Wikibase\View\TermsListView;
use Wikibase\View\Template\TemplateFactory;

/**
 * An EntityTermsView that returns placeholders for some parts of the HTML
 *
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Adrian Heine <adrian.heine@wikimedia.de>
 */
class PlaceholderEmittingEntityTermsView extends SimpleEntityTermsView {

	/**
	 * @var TemplateFactory
	 */
	private $templateFactory;

	/**
	 * @var TermsListView
	 */
	private $termsListView;

	/**
	 * @var TextInjector
	 */
	private $textInjector;

	/**
	 * @param HtmlTermRenderer $htmlTermRenderer
	 * @param LabelDescriptionLookup $labelDescriptionLookup
	 * @param TemplateFactory $templateFactory
	 * @param EditSectionGenerator $sectionEditLinkGenerator
	 * @param LocalizedTextProvider $textProvider
	 * @param TermListView $termListView
	 * @param TextInjector $textInjector
	 */
	public function __construct(
		HtmlTermRenderer $htmlTermRenderer,
		LabelDescriptionLookup $labelDescriptionLookup,
		TemplateFactory $templateFactory,
		EditSectionGenerator $sectionEditLinkGenerator,
		LocalizedTextProvider $textProvider,
		TermsListView $termsListView,
		TextInjector $textInjector
	) {
		parent::__construct(
			$htmlTermRenderer,
			$labelDescriptionLookup,
			$templateFactory,
			$sectionEditLinkGenerator,
			$termsListView,
			$textProvider
		);
		$this->templateFactory = $templateFactory;
		$this->termsListView = $termsListView;
		$this->textInjector = $textInjector;
	}

	/**
	 * @param string $mainLanguageCode Desired language of the label, description and aliases in the
	 *  title and header section. Not necessarily identical to the interface language.
	 * @param LabelsProvider $labelsProvider
	 * @param DescriptionsProvider $descriptionsProvider
	 * @param AliasesProvider|null $aliasesProvider
	 * @param EntityId|null $entityId the id of the entity
	 *
	 * @return string HTML
	 */
	public function getHtml(
		$mainLanguageCode,
		LabelsProvider $labelsProvider,
		DescriptionsProvider $descriptionsProvider,
		AliasesProvider $aliasesProvider = null,
		EntityId $entityId = null
	) {
		$cssClasses = $this->textInjector->newMarker(
			'entityViewPlaceholder-entitytermsview-entitytermsforlanguagelistview-class'
		);

		return $this->templateFactory->render( 'wikibase-entitytermsview',
			$this->getHeadingHtml( $mainLanguageCode, $entityId, $aliasesProvider ),
			$this->textInjector->newMarker( 'termbox' ),
			$cssClasses,
			$this->getHtmlForLabelDescriptionAliasesEditSection( $mainLanguageCode, $entityId )
		);
	}

	/**
	 * @param string $mainLanguageCode Desired language of the label, description and aliases in the
	 *  title and header section. Not necessarily identical to the interface language.
	 * @param LabelsProvider $labelsProvider
	 * @param DescriptionsProvider $descriptionsProvider
	 * @param AliasesProvider|null $aliasesProvider
	 *
	 * @return string[] HTML snippets
	 */
	public function getTermsListItems(
		$mainLanguageCode,
		LabelsProvider $labelsProvider,
		DescriptionsProvider $descriptionsProvider,
		AliasesProvider $aliasesProvider = null
	) {
		$termsListItems = [];

		$termsListLanguages = $this->getTermsLanguageCodes(
			$mainLanguageCode,
			$labelsProvider,
			$descriptionsProvider,
			$aliasesProvider
		);
		foreach ( $termsListLanguages as $languageCode ) {
			$termsListItems[ $languageCode ] = $this->termsListView->getListItemHtml(
				$labelsProvider,
				$descriptionsProvider,
				$aliasesProvider,
				$languageCode
			);
		}

		return $termsListItems;
	}

}
