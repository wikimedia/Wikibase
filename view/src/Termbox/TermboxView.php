<?php

namespace Wikibase\View\Termbox;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Term\AliasGroupList;
use Wikibase\DataModel\Term\TermList;
use Wikibase\LanguageFallbackChain;
use Wikibase\Repo\ParserOutput\TextInjector;
use Wikibase\View\CacheableEntityTermsView;
use Wikibase\View\EntityTermsView;
use Wikibase\View\LocalizedTextProvider;
use Wikibase\View\SpecialPageLinker;
use Wikibase\View\Termbox\Renderer\TermboxRenderer;
use Wikibase\View\Termbox\Renderer\TermboxRenderingException;

/**
 * @license GPL-2.0-or-later
 */
class TermboxView implements CacheableEntityTermsView {

	// render the root element and give client side re-rendering a chance
	/* public */ const FALLBACK_HTML = '<div class="wikibase-entitytermsview renderer-fallback"></div>';

	/* public */ const TERMBOX_PLACEHOLDER = 'wb-ui';

	/* public */ const TERMBOX_MARKUP_BLOB = 'termbox-markup';

	private $fallbackChain;
	private $renderer;
	private $specialPageLinker;
	private $textInjector;

	/**
	 * @var LocalizedTextProvider
	 */
	private $textProvider;

	public function __construct(
		LanguageFallbackChain $fallbackChain,
		TermboxRenderer $renderer,
		LocalizedTextProvider $textProvider,
		SpecialPageLinker $specialPageLinker,
		TextInjector $textInjector
	) {
		$this->fallbackChain = $fallbackChain;
		$this->renderer = $renderer;
		$this->textProvider = $textProvider;
		$this->specialPageLinker = $specialPageLinker;
		$this->textInjector = $textInjector;
	}

	public function getHtml(
		$mainLanguageCode,
		TermList $labels,
		TermList $descriptions,
		AliasGroupList $aliasGroups = null,
		EntityId $entityId = null
	) {
		return $this->textInjector->newMarker( self::TERMBOX_PLACEHOLDER );
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
		return [
			'wikibase-view-chunks' => $this->textInjector->getMarkers(),
			self::TERMBOX_MARKUP_BLOB => $this->renderTermbox( $languageCode, $entity->getId() ),
		];
	}

	/**
	 * @param $mainLanguageCode
	 * @param EntityId $entityId
	 * @return string
	 */
	private function renderTermbox( $mainLanguageCode, EntityId $entityId ) {
		try {
			return $this->renderer->getContent(
				$entityId,
				$mainLanguageCode,
				$this->specialPageLinker->getLink(
					EntityTermsView::TERMS_EDIT_SPECIAL_PAGE,
					[ $entityId->getSerialization() ]
				),
				$this->fallbackChain
			);
		} catch ( TermboxRenderingException $exception ) {
			// TODO Log
			return self::FALLBACK_HTML;
		}
	}

}
