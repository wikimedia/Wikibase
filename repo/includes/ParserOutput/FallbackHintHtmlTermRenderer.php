<?php

namespace Wikibase\Repo\ParserOutput;

use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermFallback;
use Wikibase\Lib\FallbackHtmlIndicator;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\View\HtmlTermRenderer;
use Wikibase\View\LanguageDirectionalityLookup;

/**
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Adrian Heine <adrian.heine@wikimedia.de>
 */
class FallbackHintHtmlTermRenderer implements HtmlTermRenderer {

	/**
	 * @var FallbackHtmlIndicator
	 */
	private $fallbackHtmlIndicator;

	/**
	 * @var LanguageDirectionalityLookup
	 */
	private $languageDirectionalityLookup;

	/**
	 * @param LanguageNameLookup $languageNameLookup
	 */
	public function __construct(
		LanguageDirectionalityLookup $languageDirectionalityLookup,
		LanguageNameLookup $languageNameLookup
	) {
		$this->fallbackHtmlIndicator = new FallbackHtmlIndicator( $languageNameLookup );
		$this->languageDirectionalityLookup = $languageDirectionalityLookup;
	}

	/**
	 * @param Term $term
	 * @return string HTML representing the term; This will be used in an HTML language and directionality context
	 *   that corresponds to $term->getLanguageCode().
	 */
	public function renderTerm( Term $term ) {
		$html = htmlspecialchars( $term->getText() );
		if ( $term instanceof TermFallback ) {
			$actualLanguageCode = $term->getActualLanguageCode();
			if ( $actualLanguageCode !== $term->getLanguageCode() ) {
				$html = '<span ' .
					'lang="' . htmlspecialchars( $actualLanguageCode ) . '" ' .
					'dir="' . ( $this->languageDirectionalityLookup->getDirectionality( $actualLanguageCode ) ?: 'auto' ) . '"' .
				'>' . $html . '</span>';
			}
			$html .= $this->fallbackHtmlIndicator->getHtml( $term );
		}
		return $html;
	}

}
