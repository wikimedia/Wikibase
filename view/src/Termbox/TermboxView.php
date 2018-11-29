<?php

namespace Wikibase\View;

use Exception;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Term\AliasGroupList;
use Wikibase\DataModel\Term\TermList;
use Wikibase\LanguageFallbackChain;
use Wikibase\View\Termbox\Renderer\TermboxRenderer;

/**
 * @license GPL-2.0-or-later
 */
class TermboxView implements CacheableEntityTermsView {

	// render the root element and give client side re-rendering a chance
	/* public */ const FALLBACK_HTML = '<div class="wikibase-entitytermsview renderer-fallback"></div>';

	private $fallbackChain;
	private $renderer;

	/**
	 * @var LocalizedTextProvider
	 */
	private $textProvider;

	public function __construct(
		LanguageFallbackChain $fallbackChain,
		TermboxRenderer $renderer,
		LocalizedTextProvider $textProvider
	) {
		$this->fallbackChain = $fallbackChain;
		$this->renderer = $renderer;
		$this->textProvider = $textProvider;
	}

	public function getHtml(
		$mainLanguageCode,
		TermList $labels,
		TermList $descriptions,
		AliasGroupList $aliasGroups = null,
		EntityId $entityId = null
	) {
		try {
			return $this->renderer->getContent( $entityId, $mainLanguageCode );
		} catch ( Exception $exception ) {
			return self::FALLBACK_HTML;
		}
	}

	public function getTitleHtml( EntityId $entityId = null ) {
		return htmlspecialchars(
			$this->textProvider->get( 'parentheses', [ $entityId->getSerialization() ] )
		);
	}

	/**
	 * @see \Wikibase\View\ViewPlaceHolderEmitter
	 */
	public function getPlaceholders(
		EntityDocument $entity,
		$languageCode
	) {
		return [];
	}

}
