<?php

namespace Wikibase\Repo\Hooks;

use OutputPage;
use User;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Term\AliasesProvider;
use Wikibase\EntityRevision;
use Wikibase\Lib\ContentLanguages;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\UserLanguageLookup;
use Wikibase\Repo\BabelUserLanguageLookup;
use Wikibase\Repo\MediaWikiLocalizedTextProvider;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\View\EntityViewPlaceholderExpander;
use Wikibase\View\Template\TemplateFactory;
use Wikibase\View\TextInjector;

/**
 * Handler for the "OutputPageBeforeHTML" hook.
 *
 * @since 0.5
 *
 * @license GPL-2.0+
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
	 * @param TemplateFactory $templateFactory
	 * @param UserLanguageLookup $userLanguageLookup
	 * @param ContentLanguages $termsLanguages
	 * @param EntityRevisionLookup $entityRevisionLookup
	 * @param LanguageNameLookup $languageNameLookup
	 * @param OutputPageEntityIdReader $outputPageEntityIdReader
	 */
	public function __construct(
		TemplateFactory $templateFactory,
		UserLanguageLookup $userLanguageLookup,
		ContentLanguages $termsLanguages,
		EntityRevisionLookup $entityRevisionLookup,
		LanguageNameLookup $languageNameLookup,
		OutputPageEntityIdReader $outputPageEntityIdReader
	) {
		$this->templateFactory = $templateFactory;
		$this->userLanguageLookup = $userLanguageLookup;
		$this->termsLanguages = $termsLanguages;
		$this->entityRevisionLookup = $entityRevisionLookup;
		$this->languageNameLookup = $languageNameLookup;
		$this->outputPageEntityIdReader = $outputPageEntityIdReader;
	}

	/**
	 * @return self
	 */
	public static function newFromGlobalState() {
		global $wgLang;

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
			)
		);
	}

	/**
	 * Called when pushing HTML from the ParserOutput into OutputPage.
	 * Used to expand any placeholders in the OutputPage's 'wb-placeholders' property
	 * in the HTML.
	 *
	 * @param OutputPage $out
	 * @param string &$html the HTML to mangle
	 *
	 * @return bool
	 */
	public static function onOutputPageBeforeHTML( OutputPage $out, &$html ) {
		$self = self::newFromGlobalState();

		return $self->doOutputPageBeforeHTML( $out, $html );
	}

	/**
	 * @param OutputPage $out
	 * @param string &$html
	 *
	 * @return bool
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

		$entityId = $this->outputPageEntityIdReader->getEntityIdFromOutputPage( $out );

		if ( $entityId !== null ) {
			$entityRev = $this->entityRevisionLookup->getEntityRevision(
				$entityId,
				$out->getRevisionId()
			);

			if ( $entityRev instanceof EntityRevision ) {
				// All user languages that are valid term languages
				$termsLanguages = array_intersect(
					$this->userLanguageLookup->getAllUserLanguages( $out->getUser() ),
					$this->termsLanguages->getLanguages()
				);

				$expander = $this->getEntityViewPlaceholderExpander(
					$entityRev->getEntity(),
					$out->getUser(),
					$termsLanguages,
					$out->getLanguage()->getCode()
				);

				$html = $injector->inject( $html, [ $expander, 'getHtmlForPlaceholder' ] );

				return;
			}
		}

		$html = $injector->inject(
			$html,
			function() {
				return '';
			}
		);
	}

	/**
	 * @param EntityDocument $entity
	 * @param User $user
	 * @param string[] $termsLanguages
	 * @param string $languageCode
	 *
	 * @return EntityViewPlaceholderExpander
	 */
	private function getEntityViewPlaceholderExpander(
		EntityDocument $entity,
		User $user,
		array $termsLanguages,
		$languageCode
	 ) {
		$labelsProvider = $entity;
		$descriptionsProvider = $entity;
		$aliasesProvider = $entity instanceof AliasesProvider ? $entity : null;

		return new EntityViewPlaceholderExpander(
			$this->templateFactory,
			$user,
			$labelsProvider,
			$descriptionsProvider,
			$aliasesProvider,
			array_merge( [ $languageCode ], $termsLanguages ),
			$this->languageNameLookup,
			new MediaWikiLocalizedTextProvider( $languageCode )
		);
	}

}
