<?php

namespace Wikibase\Repo\Hooks;

use OutputPage;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\EntityFactory;
use Wikibase\Lib\Store\EntityRevision;
use Wikibase\Lib\ContentLanguages;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\UserLanguageLookup;
use Wikibase\Repo\BabelUserLanguageLookup;
use Wikibase\Repo\MediaWikiLanguageDirectionalityLookup;
use Wikibase\Repo\MediaWikiLocalizedTextProvider;
use Wikibase\Repo\ParserOutput\PlaceholderExpander\EntityViewPlaceholderExpander;
use Wikibase\Repo\ParserOutput\PlaceholderExpander\ExternallyRenderedEntityViewPlaceholderExpander;
use Wikibase\Repo\ParserOutput\PlaceholderExpander\PlaceholderExpander;
use Wikibase\Repo\ParserOutput\TermboxFlag;
use Wikibase\Repo\ParserOutput\TextInjector;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\View\Template\TemplateFactory;
use Wikibase\Repo\ParserOutput\TermboxView;

/**
 * Handler for the "OutputPageBeforeHTML" hook.
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch < hoo@online.de >
 */
class OutputPageBeforeHTMLHookHandler {

	/**
	 * @var TemplateFactory
	 */
	private $templateFactory;

	/**
	 * @var UserLanguageLookup
	 */
	private $userLanguageLookup;

	/**
	 * @var ContentLanguages
	 */
	private $termsLanguages;

	/**
	 * @var EntityRevisionLookup
	 */
	private $entityRevisionLookup;

	/**
	 * @var LanguageNameLookup
	 */
	private $languageNameLookup;

	/**
	 * @var OutputPageEntityIdReader
	 */
	private $outputPageEntityIdReader;

	/**
	 * @var EntityFactory
	 */
	private $entityFactory;

	/**
	 * @var string
	 */
	private $cookiePrefix;

	/**
	 * @var bool
	 */
	private $isExternallyRendered;

	public function __construct(
		TemplateFactory $templateFactory,
		UserLanguageLookup $userLanguageLookup,
		ContentLanguages $termsLanguages,
		EntityRevisionLookup $entityRevisionLookup,
		LanguageNameLookup $languageNameLookup,
		OutputPageEntityIdReader $outputPageEntityIdReader,
		EntityFactory $entityFactory,
		$cookiePrefix,
		$isExternallyRendered = false
	) {
		$this->templateFactory = $templateFactory;
		$this->userLanguageLookup = $userLanguageLookup;
		$this->termsLanguages = $termsLanguages;
		$this->entityRevisionLookup = $entityRevisionLookup;
		$this->languageNameLookup = $languageNameLookup;
		$this->outputPageEntityIdReader = $outputPageEntityIdReader;
		$this->entityFactory = $entityFactory;
		$this->cookiePrefix = $cookiePrefix;
		$this->isExternallyRendered = $isExternallyRendered;
	}

	/**
	 * @return self
	 */
	public static function newFromGlobalState() {
		global $wgLang, $wgCookiePrefix;

		$wikibaseRepo = WikibaseRepo::getDefaultInstance();

		return new self(
			TemplateFactory::getDefaultInstance(),
			new BabelUserLanguageLookup,
			$wikibaseRepo->getTermsLanguages(),
			$wikibaseRepo->getEntityRevisionLookup(),
			new LanguageNameLookup( $wgLang->getCode() ),
			new OutputPageEntityIdReader(
				$wikibaseRepo->getEntityContentFactory(),
				$wikibaseRepo->getEntityIdParser()
			),
			$wikibaseRepo->getEntityFactory(),
			$wgCookiePrefix,
			TermboxFlag::getInstance()->shouldRenderTermbox()
		);
	}

	/**
	 * Called when pushing HTML from the ParserOutput into OutputPage.
	 * Used to expand any placeholders in the OutputPage's 'wb-placeholders' property
	 * in the HTML.
	 *
	 * @param OutputPage $out
	 * @param string &$html the HTML to mangle
	 */
	public static function onOutputPageBeforeHTML( OutputPage $out, &$html ) {
		self::newFromGlobalState()->doOutputPageBeforeHTML( $out, $html );
	}

	/**
	 * @param OutputPage $out
	 * @param string &$html
	 */
	public function doOutputPageBeforeHTML( OutputPage $out, &$html ) {
		$placeholders = $out->getProperty( 'wikibase-view-chunks' );

		if ( !empty( $placeholders ) ) {
			$this->replacePlaceholders( $placeholders, $out, $html );

			$out->addJsConfigVars(
				'wbUserSpecifiedLanguages',
				// All user-specified languages, that are valid term languages
				// Reindex the keys so that javascript still works if an unknown
				// language code in the babel box causes an index to miss
				array_values( array_intersect(
					$this->userLanguageLookup->getUserSpecifiedLanguages( $out->getUser() ),
					$this->termsLanguages->getLanguages()
				) )
			);
		}
	}

	/**
	 * @param string[] $placeholders
	 * @param OutputPage $out
	 * @param string &$html
	 */
	private function replacePlaceholders( array $placeholders, OutputPage $out, &$html ) {
		$injector = new TextInjector( $placeholders );
		$getHtmlCallback = function() {
			return '';
		};

		$entity = $this->getEntity( $out );
		if ( $entity instanceof EntityDocument ) {
			$getHtmlCallback = [ $this->getPlaceholderExpander( $entity, $out ), 'getHtmlForPlaceholder' ];
		}

		$html = $injector->inject( $html, $getHtmlCallback );
	}

	/**
	 * @param OutputPage $out
	 *
	 * @return EntityDocument|null
	 */
	private function getEntity( OutputPage $out ) {
		$entityId = $this->outputPageEntityIdReader->getEntityIdFromOutputPage( $out );

		if ( !$entityId ) {
			return null;
		}

		if ( $this->isPrerendered( $out ) ) {
			$entity = $this->entityFactory->newEmpty( $entityId->getEntityType() );
		} else {
			// The parser cache content is too old to contain the terms list items
			// Pass the correct entity to generate terms list items on the fly
			$entityRev = $this->entityRevisionLookup->getEntityRevision( $entityId, $out->getRevisionId() );
			if ( !( $entityRev instanceof EntityRevision ) ) {
				return null;
			}
			$entity = $entityRev->getEntity();
		}
		return $entity;
	}

	/**
	 * @param OutputPage $out
	 *
	 * @return string[]
	 */
	private function getTermsLanguagesCodes( OutputPage $out ) {
		// All user languages that are valid term languages
		return array_intersect(
			$this->userLanguageLookup->getAllUserLanguages( $out->getUser() ),
			$this->termsLanguages->getLanguages()
		);
	}

	private function getPlaceholderExpander(
		EntityDocument $entity,
		OutputPage $out
	): PlaceholderExpander {
		return $this->isExternallyRendered
			? $this->getExternallyRenderedEntityViewPlaceholderExpander( $out )
			: $this->getLocallyRenderedEntityViewPlaceholderExpander(
				$entity,
				$out
			);
	}

	/**
	 * @param EntityDocument $entity
	 * @param OutputPage $out
	 *
	 * @return EntityViewPlaceholderExpander
	 */
	private function getLocallyRenderedEntityViewPlaceholderExpander(
		EntityDocument $entity,
		OutputPage $out
	) {
		$language = $out->getLanguage();

		return new EntityViewPlaceholderExpander(
			$this->templateFactory,
			$out->getUser(),
			$entity,
			array_unique( array_merge( [ $language->getCode() ], $this->getTermsLanguagesCodes( $out ) ) ),
			new MediaWikiLanguageDirectionalityLookup(),
			$this->languageNameLookup,
			new MediaWikiLocalizedTextProvider( $language ),
			$this->cookiePrefix,
			$this->getEntityTermsListHtml( $out ) ?: []
		);
	}

	private function getExternallyRenderedEntityViewPlaceholderExpander( $out ) {
		return new ExternallyRenderedEntityViewPlaceholderExpander(
			$this->getExternallyRenderedHtml( $out )
		);
	}

	/**
	 * @param OutputPage $out
	 * @return string|null
	 */
	private function getExternallyRenderedHtml( OutputPage $out ) {
		return $out->getProperty( TermboxView::TERMBOX_MARKUP );
	}

	private function isPrerendered( OutputPage $out ) {
		return $this->getEntityTermsListHtml( $out ) || $this->getExternallyRenderedHtml( $out );
	}

	private function getEntityTermsListHtml( OutputPage $out ) {
		return $out->getProperty( 'wikibase-terms-list-items' );
	}

}
