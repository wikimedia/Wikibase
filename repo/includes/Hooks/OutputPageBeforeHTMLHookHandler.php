<?php

namespace Wikibase\Repo\Hooks;

use Language;
use OutputPage;
use User;
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
use Wikibase\Repo\ParserOutput\EntityViewPlaceholderExpander;
use Wikibase\Repo\ParserOutput\ExternallyRenderedEntityViewPlaceholderExpander;
use Wikibase\Repo\ParserOutput\TextInjector;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\View\Template\TemplateFactory;
use Wikibase\View\Termbox\TermboxView;

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

	public function __construct(
		TemplateFactory $templateFactory,
		UserLanguageLookup $userLanguageLookup,
		ContentLanguages $termsLanguages,
		EntityRevisionLookup $entityRevisionLookup,
		LanguageNameLookup $languageNameLookup,
		OutputPageEntityIdReader $outputPageEntityIdReader,
		EntityFactory $entityFactory,
		$cookiePrefix
	) {
		$this->templateFactory = $templateFactory;
		$this->userLanguageLookup = $userLanguageLookup;
		$this->termsLanguages = $termsLanguages;
		$this->entityRevisionLookup = $entityRevisionLookup;
		$this->languageNameLookup = $languageNameLookup;
		$this->outputPageEntityIdReader = $outputPageEntityIdReader;
		$this->entityFactory = $entityFactory;
		$this->cookiePrefix = $cookiePrefix;
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
			$wgCookiePrefix
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

		$entityId = $this->outputPageEntityIdReader->getEntityIdFromOutputPage( $out );
		if ( $entityId instanceof EntityId ) {
			$termsListItemsHtml = $out->getProperty( 'wikibase-terms-list-items' );
			$entity = $this->getEntity(
				$entityId,
				$out->getRevisionId(),
				$termsListItemsHtml !== null // FIXME why!?
				|| $this->isExternallyRenderedEntityView( $out )
			);
			if ( $entity instanceof EntityDocument ) {
				$expander = $this->isExternallyRenderedEntityView( $out )
					? $this->getExternallyRenderedEntityViewPlaceholderExpander( $out )
					: $this->getLocallyRenderedEntityViewPlaceholderExpander(
						$entity,
						$out->getUser(),
						$this->getTermsLanguagesCodes( $out ),
						$termsListItemsHtml,
						$out->getLanguage()
					);
				$getHtmlCallback = [ $expander, 'getHtmlForPlaceholder' ];
			}
		}

		$html = $injector->inject( $html, $getHtmlCallback );
	}

	private function isExternallyRenderedEntityView( OutputPage $out ) {
		return $this->getExternallyRenderedHtmlBlob( $out ) !== null;
	}

	/**
	 * @param EntityId $entityId
	 * @param int $revisionId
	 * @param bool $termsListPrerendered
	 *
	 * @return EntityDocument|null
	 */
	private function getEntity( EntityId $entityId, $revisionId, $termsListPrerendered ) {
		if ( $termsListPrerendered ) {
			$entity = $this->entityFactory->newEmpty( $entityId->getEntityType() );
		} else {
			// The parser cache content is too old to contain the terms list items
			// Pass the correct entity to generate terms list items on the fly
			$entityRev = $this->entityRevisionLookup->getEntityRevision( $entityId, $revisionId );
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

	/**
	 * @param EntityDocument $entity
	 * @param User $user
	 * @param string[] $termsLanguages
	 * @param string[]|null $termsListItemsHtml
	 * @param Language $language
	 *
	 * @return EntityViewPlaceholderExpander
	 */
	private function getLocallyRenderedEntityViewPlaceholderExpander(
		EntityDocument $entity,
		User $user,
		array $termsLanguages,
		array $termsListItemsHtml = null,
		Language $language
	) {
		return new EntityViewPlaceholderExpander(
			$this->templateFactory,
			$user,
			$entity,
			array_unique( array_merge( [ $language->getCode() ], $termsLanguages ) ),
			new MediaWikiLanguageDirectionalityLookup(),
			$this->languageNameLookup,
			new MediaWikiLocalizedTextProvider( $language ),
			$this->cookiePrefix,
			$termsListItemsHtml ?: []
		);
	}

	private function getExternallyRenderedEntityViewPlaceholderExpander( $out ) {
		return new ExternallyRenderedEntityViewPlaceholderExpander(
			$this->getExternallyRenderedHtmlBlob( $out )
		);
	}

	/**
	 * @param OutputPage $out
	 * @return string|null
	 */
	private function getExternallyRenderedHtmlBlob( OutputPage $out ) {
		return $out->getProperty( TermboxView::TERMBOX_MARKUP_BLOB );
	}

}
