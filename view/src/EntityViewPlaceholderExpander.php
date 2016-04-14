<?php

namespace Wikibase\View;

use InvalidArgumentException;
use MWException;
use RuntimeException;
use Title;
use User;
use Wikibase\DataModel\Term\AliasesProvider;
use Wikibase\DataModel\Term\DescriptionsProvider;
use Wikibase\DataModel\Term\LabelsProvider;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\View\Template\TemplateFactory;

/**
 * Utility for expanding the placeholders left in the HTML by EntityView.
 *
 * This is used to inject any non-cacheable information into the HTML
 * that was cached as part of the ParserOutput.
 *
 * @note This class encapsulated knowledge about which placeholders are used by
 * EntityView, and with what meaning.
 *
 * @see EntityView
 *
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 * @author Adrian Heine <adrian.heine@wikimedia.de>
 */
class EntityViewPlaceholderExpander {

	/**
	 * @var TemplateFactory
	 */
	private $templateFactory;

	/**
	 * @var Title
	 */
	private $targetPage;

	/**
	 * @var User
	 */
	private $user;

	/**
	 * @var LabelsProvider
	 */
	private $labelsProvider;

	/**
	 * @var DescriptionsProvider
	 */
	private $descriptionsProvider;

	/**
	 * @var AliasesProvider|null
	 */
	private $aliasesProvider;

	/**
	 * @var string[]
	 */
	private $termsLanguages;

	/**
	 * @var LanguageNameLookup
	 */
	private $languageNameLookup;

	/**
	 * @var LocalizedTextProvider
	 */
	private $textProvider;

	/**
	 * @param TemplateFactory $templateFactory
	 * @param Title $targetPage the page for which this expander is supposed to handle expansion.
	 * @param User $user the current user
	 * @param LabelsProvider $labelsProvider
	 * @param DescriptionsProvider $descriptionsProvider
	 * @param AliasesProvider|null $aliasesProvider
	 * @param string[] $termsLanguages
	 * @param LanguageNameLookup $languageNameLookup
	 * @param LocalizedTextProvider $textProvider
	 */
	public function __construct(
		TemplateFactory $templateFactory,
		Title $targetPage,
		User $user,
		LabelsProvider $labelsProvider,
		DescriptionsProvider $descriptionsProvider,
		AliasesProvider $aliasesProvider = null,
		array $termsLanguages,
		LanguageNameLookup $languageNameLookup,
		LocalizedTextProvider $textProvider
	) {
		$this->targetPage = $targetPage;
		$this->user = $user;
		$this->labelsProvider = $labelsProvider;
		$this->descriptionsProvider = $descriptionsProvider;
		$this->aliasesProvider = $aliasesProvider;
		$this->templateFactory = $templateFactory;
		$this->termsLanguages = $termsLanguages;
		$this->languageNameLookup = $languageNameLookup;
		$this->textProvider = $textProvider;
	}

	/**
	 * Callback for expanding placeholders to HTML,
	 * for use as a callback passed to with TextInjector::inject().
	 *
	 * @note This delegates to expandPlaceholder, which encapsulates knowledge about
	 * the meaning of each placeholder name, as used by EntityView.
	 *
	 * @param string $name the name (or kind) of placeholder; determines how the expansion is done.
	 *
	 * @return string HTML to be substituted for the placeholder in the output.
	 */
	public function getHtmlForPlaceholder( $name ) {
		try {
			return $this->expandPlaceholder( $name );
		} catch ( MWException $ex ) {
			wfWarn( "Expansion of $name failed: " . $ex->getMessage() );
		} catch ( RuntimeException $ex ) {
			wfWarn( "Expansion of $name failed: " . $ex->getMessage() );
		}

		return false;
	}

	/**
	 * Dispatch the expansion of placeholders based on the name.
	 *
	 * @note This encodes knowledge about which placeholders are used by EntityView with what
	 *       intended meaning.
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	protected function expandPlaceholder( $name ) {
		switch ( $name ) {
			case 'termbox':
				return $this->renderTermBox();
			case 'entityViewPlaceholder-entitytermsview-entitytermsforlanguagelistview-class':
				return $this->isInitiallyCollapsed() ? 'wikibase-initially-collapsed' : '';
			default:
				wfWarn( "Unknown placeholder: $name" );
				return '(((' . htmlspecialchars( $name ) . ')))';
		}
	}

	/**
	 * @return bool If the terms list should be initially collapsed for the current user.
	 */
	 private function isInitiallyCollapsed() {
		if ( $this->user->isAnon() ) {
			return isset( $_COOKIE['wikibase-entitytermsview-showEntitytermslistview'] )
				&& $_COOKIE['wikibase-entitytermsview-showEntitytermslistview'] === 'false';
		} else {
			return !$this->user->getBoolOption( 'wikibase-entitytermsview-showEntitytermslistview' );
		}
	 }

	/**
	 * Generates HTML of the term box, to be injected into the entity page.
	 *
	 * @throws InvalidArgumentException
	 * @return string HTML
	 */
	public function renderTermBox() {

		$entityTermsView = new EntityTermsView(
			$this->templateFactory,
			null,
			$this->languageNameLookup,
			$this->textProvider
		);

		$html = $entityTermsView->getEntityTermsForLanguageListView(
			$this->labelsProvider,
			$this->descriptionsProvider,
			$this->aliasesProvider,
			$this->termsLanguages,
			$this->targetPage
		);

		return $html;
	}

}
