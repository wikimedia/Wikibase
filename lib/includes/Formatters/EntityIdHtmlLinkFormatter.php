<?php

namespace Wikibase\Lib;

use Html;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Services\EntityId\EntityIdLabelFormatter;
use Wikibase\DataModel\Services\Lookup\LabelDescriptionLookup;
use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermFallback;
use Wikibase\Lib\Store\EntityTitleLookup;

/**
 * Formats entity IDs by generating an HTML link to the corresponding page title.
 *
 * @license GPL-2.0+
 * @author Adrian Heine <adrian.heine@wikimedia.de>
 * @author Thiemo Mättig
 */
class EntityIdHtmlLinkFormatter extends EntityIdLabelFormatter {

	/**
	 * @var LanguageFallbackIndicator
	 */
	private $languageFallbackIndicator;

	/**
	 * @var EntityTitleLookup
	 */
	protected $entityTitleLookup;

	public function __construct(
		LabelDescriptionLookup $labelDescriptionLookup,
		EntityTitleLookup $entityTitleLookup,
		LanguageNameLookup $languageNameLookup
	) {
		parent::__construct( $labelDescriptionLookup );

		$this->entityTitleLookup = $entityTitleLookup;
		$this->languageFallbackIndicator = new LanguageFallbackIndicator( $languageNameLookup );
	}

	/**
	 * @see EntityIdFormatter::formatEntityId
	 *
	 * @param EntityId $entityId
	 *
	 * @return string HTML
	 */
	public function formatEntityId( EntityId $entityId ) {
		$title = $this->entityTitleLookup->getTitleForId( $entityId );

		if ( $title === null ) {
			return $this->getHtmlForNonExistent( $entityId );
		}

		$term = $this->lookupEntityLabel( $entityId );

		$url = $title->isLocal() ? $title->getLocalURL() : $title->getFullURL();
		$isRedirect = $title->isLocal() && $title->isRedirect();

		if ( $term ) {
			return $this->getHtmlForTerm( $url, $term, $title->getPrefixedText(), $isRedirect );
		} elseif ( $title->isLocal() && !$title->exists() ) {
			return $this->getHtmlForNonExistent( $entityId );
		}

		$attributes = [
			'title' => $title->getPrefixedText(),
			'href' => $url
		];
		if ( $isRedirect ) {
			$attributes['class'] = 'mw-redirect';
		}

		$html = Html::element( 'a', $attributes, $entityId->getSerialization() );

		return $html;
	}

	/**
	 * @param string $targetUrl
	 * @param Term $term
	 * @param string $titleText
	 * @param bool $isRedirect
	 *
	 * @return string HTML
	 */
	private function getHtmlForTerm( $targetUrl, Term $term, $titleText = '', $isRedirect = false ) {
		$fallbackIndicatorHtml = '';

		$attributes = [
			'title' => $titleText,
			'href' => $targetUrl
		];

		if ( $term instanceof TermFallback ) {
			$fallbackIndicatorHtml = $this->languageFallbackIndicator->getHtml( $term );

			if ( $term->getActualLanguageCode() !== $term->getLanguageCode() ) {
				$attributes['lang'] = $term->getActualLanguageCode();
				//TODO: mark as rtl/ltr if appropriate.
			}
		}

		if ( $isRedirect ) {
			$attributes['class'] = 'mw-redirect';
		}

		$html = Html::element( 'a', $attributes, $term->getText() );

		return $html . $fallbackIndicatorHtml;
	}

	/**
	 * @param EntityId $entityId
	 *
	 * @return string HTML
	 */
	private function getHtmlForNonExistent( EntityId $entityId ) {
		$attributes = [ 'class' => 'wb-entity-undefinedinfo' ];

		$message = wfMessage( 'parentheses',
			wfMessage( 'wikibase-deletedentity-' . $entityId->getEntityType() )->text()
		);

		$undefinedInfo = Html::element( 'span', $attributes, $message );

		$separator = wfMessage( 'word-separator' )->text();
		return $entityId->getSerialization() . $separator . $undefinedInfo;
	}

}
