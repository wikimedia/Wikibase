<?php

namespace Wikibase\Repo\Hook;

use DummyLinker;
use Html;
use Language;
use OutputPage;
use RequestContext;
use Title;
use Wikibase\LanguageFallbackChain;
use Wikibase\Lib\Store\StorageException;
use Wikibase\Lib\Store\TermLookup;
use Wikibase\Repo\Store\PageEntityIdLookup;
use Wikibase\Repo\WikibaseRepo;

/**
 * @since 0.5
 *
 * @licence GNU GPL v2+
 */
class LinkBeginHookHandler {

	/**
	 * @var PageEntityIdLookup
	 */
	private $entityIdLookup;

	/**
	 * @var TermLookup
	 */
	private $termLookup;

	/**
	 * @var LanguageFallbackChain
	 */
	private $languageFallback;

	/**
	 * @var Language
	 */
	private $pageLanguage;

	/**
	 * @return LinkBeginHookHandler
	 */
	private static function newFromGlobalState() {
		$context = RequestContext::getMain();

		$entityIdLookup = WikibaseRepo::getDefaultInstance()->getPageEntityIdLookup();
		$termLookup = WikibaseRepo::getDefaultInstance()->getTermLookup();

		$languageFallbackChainFactory = WikibaseRepo::getDefaultInstance()->getLanguageFallbackChainFactory();
		$languageFallbackChain = $languageFallbackChainFactory->newFromContext( $context );

		return new self( $entityIdLookup, $termLookup, $languageFallbackChain, $context->getLanguage() );
	}

	/**
	 * Special page handling where we want to display meaningful link labels instead of just the items ID.
	 * This is only handling special pages right now and gets disabled in normal pages.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LinkBegin
	 *
	 * @param DummyLinker $skin
	 * @param Title $target
	 * @param string $html
	 * @param array $customAttribs
	 * @param string $query
	 * @param array $options
	 * @param mixed $ret
	 * @return bool true
	 */
	public static function onLinkBegin( $skin, $target, &$html, array &$customAttribs, &$query,
		&$options, &$ret
	) {
		$handler = self::newFromGlobalState();
		$context = RequestContext::getMain();

		$handler->doOnLinkBegin( $target, $html, $customAttribs, $context->getOutput() );

		return true;
	}

	/**
	 * @param PageEntityIdLookup $entityIdLookup
	 * @param TermLookup $termLookup
	 * @param LanguageFallbackChain $languageFallback
	 * @param Language $pageLanguage
	 *
	 * @todo: Would be nicer to take a LabelLookup instead of TermLookup + FallbackChain.
	 *        But LabelLookup does not support descriptions at the moment.
	 */
	public function __construct(
		PageEntityIdLookup $entityIdLookup,
		TermLookup $termLookup,
		LanguageFallbackChain $languageFallback,
		Language $pageLanguage
	) {
		$this->entityIdLookup = $entityIdLookup;
		$this->termLookup = $termLookup;
		$this->languageFallback = $languageFallback;
		$this->pageLanguage = $pageLanguage;
	}

	/**
	 * @param Title $target
	 * @param string &$html
	 * @param array &$customAttribs
	 * @param OutputPage $out
	 */
	public function doOnLinkBegin( Title $target, &$html, array &$customAttribs, OutputPage $out ) {
		if ( !$target->exists() ) {
			// The link points to a non-existing item.
			return;
		}

		$currentTitle = $out->getTitle();

		if ( $currentTitle === null || !$currentTitle->isSpecialPage() ) {
			// Note: this may not work right with special page transclusion. If $out->getTitle()
			// doesn't return the transcluded special page's title, the transcluded text will
			// not have entity IDs resolved to labels.
			return;
		}

		// if custom link text is given, there is no point in overwriting it
		// but not if it is similar to the plain title
		if ( $html !== null && $target->getFullText() !== $html ) {
			return;
		}

		wfProfileIn( __METHOD__ );

		$entityId = $this->entityIdLookup->getPageEntityId( $target );

		if ( !$entityId ) {
			wfProfileOut( __METHOD__ );
			return;
		}

		try {
			//@todo: only fetch the labels we need for the fallback chain
			$labels = $this->termLookup->getLabels( $entityId );
			$descriptions = $this->termLookup->getDescriptions( $entityId );
		} catch ( StorageException $ex ) {
			// This shouldn't happen if $target->exists() return true!
			wfProfileOut( __METHOD__ );
			return;
		}

		$labelData = $this->getPreferredTerm( $labels );
		$descriptionData = $this->getPreferredTerm( $descriptions );

		$html = $this->getHtml( $target, $labelData );

		$customAttribs['title'] = $this->getTitleAttribute(
			$target,
			$labelData,
			$descriptionData
		);

		// add wikibase styles in all cases, so we can format the link properly:
		$out->addModuleStyles( array( 'wikibase.common' ) );

		wfProfileOut( __METHOD__ );
	}

	private function getPreferredTerm( $termsByLanguage ) {
		if ( empty( $termsByLanguage ) ) {
			return null;
		}

		return $this->languageFallback->extractPreferredValueOrAny(
			$termsByLanguage
		);
	}

	/**
	 * @param array $termData A term record as returned by
	 * LanguageFallbackChain::extractPreferredValueOrAny(),
	 * containing the 'value' and 'language' fields, or null
	 * or an empty array.
	 *
	 * @see LanguageFallbackChain::extractPreferredValueOrAny
	 *
	 * @return array list( string $text, Language $language )
	 */
	private function extractTextAndLanguage( $termData ) {
		if ( $termData ) {
			return array(
				$termData['value'],
				Language::factory( $termData['language'] )
			);
		} else {
			return array(
				'',
				$this->pageLanguage
			);
		}
	}

	private function getHtml( Title $title, $labelData ) {
		/** @var Language $labelLang */
		list( $labelText, $labelLang ) = $this->extractTextAndLanguage( $labelData );

		$idHtml = Html::openElement( 'span', array( 'class' => 'wb-itemlink-id' ) )
			. wfMessage(
				'wikibase-itemlink-id-wrapper',
				$title->getText()
			)->inContentLanguage()->escaped()
			. Html::closeElement( 'span' );

		$labelHtml = Html::openElement( 'span', array(
				'class' => 'wb-itemlink-label',
				'lang' => $labelLang->getHtmlCode(),
				'dir' => $labelLang->getDir()
			) )
			. htmlspecialchars( $labelText )
			. Html::closeElement( 'span' );

		return Html::openElement( 'span', array( 'class' => 'wb-itemlink' ) )
			. wfMessage( 'wikibase-itemlink' )->rawParams(
				$labelHtml,
				$idHtml
			)->inContentLanguage()->escaped()
			. Html::closeElement( 'span' );
	}

	private function getTitleAttribute( Title $title, $labelData, $descriptionData ) {
		/** @var Language $labelLang */
		/** @var Language $descriptionLang */

		list( $labelText, $labelLang ) = $this->extractTextAndLanguage( $labelData );
		list( $descriptionText, $descriptionLang ) = $this->extractTextAndLanguage( $descriptionData );

		// Set title attribute for constructed link, and make tricks with the directionality to get it right
		$titleText = ( $labelText !== '' )
			? $labelLang->getDirMark() . $labelText
				. $this->pageLanguage->getDirMark()
			: $title->getPrefixedText();

		$descriptionText = $descriptionLang->getDirMark() . $descriptionText
			. $this->pageLanguage->getDirMark();

		return ( $descriptionText !== '' ) ?
			wfMessage(
				'wikibase-itemlink-title',
				$titleText,
				$descriptionText
			)->inContentLanguage()->text() :
			$titleText; // no description, just display the title then
	}

}
