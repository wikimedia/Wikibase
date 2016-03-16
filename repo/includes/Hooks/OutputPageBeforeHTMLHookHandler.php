<?php

namespace Wikibase\Repo\Hooks;

use OutputPage;
use Revision;
use Wikibase\DataModel\Term\AliasesProvider;
use Wikibase\DataModel\Term\DescriptionsProvider;
use Wikibase\DataModel\Term\LabelsProvider;
use Wikibase\Lib\ContentLanguages;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\UserLanguageLookup;
use Wikibase\Repo\BabelUserLanguageLookup;
use Wikibase\Repo\Content\EntityContentFactory;
use Wikibase\Repo\MediaWikiLocalizedTextProvider;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Repo\Store\EntityPerPage;
use Wikibase\Repo\Store\TermIndexAliasesProvider;
use Wikibase\Repo\Store\TermIndexDescriptionsProvider;
use Wikibase\Repo\Store\TermIndexLabelsProvider;
use Wikibase\TermIndex;
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
	 * @var EntityContentFactory
	 */
	private $entityContentFactory;

	/**
	 * @var EntityPerPage
	 */
	private $entityPerPage;

	/**
	 * @var TermIndex
	 */
	private $termIndex;

	/**
	 * @param TemplateFactory $templateFactory
	 * @param UserLanguageLookup $userLanguageLookup
	 * @param ContentLanguages $termsLanguages
	 * @param EntityRevisionLookup $entityRevisionLookup
	 * @param LanguageNameLookup $languageNameLookup
	 * @param OutputPageEntityIdReader $outputPageEntityIdReader
	 * @param EntityContentFactory $entityContentFactory
	 * @param EntityPerPage $entityPerPage
	 * @param TermIndex $termIndex
	 */
	public function __construct(
		TemplateFactory $templateFactory,
		UserLanguageLookup $userLanguageLookup,
		ContentLanguages $termsLanguages,
		EntityRevisionLookup $entityRevisionLookup,
		LanguageNameLookup $languageNameLookup,
		OutputPageEntityIdReader $outputPageEntityIdReader,
		EntityContentFactory $entityContentFactory,
		EntityPerPage $entityPerPage,
		TermIndex $termIndex
	) {
		$this->templateFactory = $templateFactory;
		$this->userLanguageLookup = $userLanguageLookup;
		$this->termsLanguages = $termsLanguages;
		$this->entityRevisionLookup = $entityRevisionLookup;
		$this->languageNameLookup = $languageNameLookup;
		$this->outputPageEntityIdReader = $outputPageEntityIdReader;
		$this->entityContentFactory = $entityContentFactory;
		$this->entityPerPage = $entityPerPage;
		$this->termIndex = $termIndex;
	}

	/**
	 * @return self
	 */
	public static function newFromGlobalState() {
		global $wgLang;

		$wikibaseRepo = WikibaseRepo::getDefaultInstance();

		$entityContentFactory = $wikibaseRepo->getEntityContentFactory();

		return new self(
			TemplateFactory::getDefaultInstance(),
			new BabelUserLanguageLookup,
			$wikibaseRepo->getTermsLanguages(),
			$wikibaseRepo->getEntityRevisionLookup(),
			new LanguageNameLookup( $wgLang->getCode() ),
			new OutputPageEntityIdReader(
				$entityContentFactory,
				$wikibaseRepo->getEntityIdParser()
			),
			$entityContentFactory,
			$wikibaseRepo->getStore()->newEntityPerPage(),
			$wikibaseRepo->getStore()->getTermIndex()
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
			$injector = new TextInjector( $placeholders );
			$expander = $this->getEntityViewPlaceholderExpander( $out );

			$html = $injector->inject( $html, array( $expander, 'getHtmlForPlaceholder' ) );

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
	 * @param OutputPage $out
	 *
	 * @return EntityViewPlaceholderExpander
	 */
	private function getEntityViewPlaceholderExpander( OutputPage $out ) {
		$entityId = $this->outputPageEntityIdReader->getEntityIdFromOutputPage( $out );
		$revisionId = $out->getRevisionId();
		if ( $revisionId === null || Revision::newFromId( $revisionId )->isCurrent() ) {
			$emptyEntity = $this->entityContentFactory->getContentHandlerForType( $entityId->getEntityType() )->makeEmptyEntity();
			$labelsProvider = $emptyEntity instanceof LabelsProvider ?
				new TermIndexLabelsProvider( $this->termIndex, $entityId ) :
				null;
			$descriptionsProvider = $emptyEntity instanceof DescriptionsProvider ?
				new TermIndexDescriptionsProvider( $this->termIndex, $entityId ) :
				null;
			$aliasesProvider = $emptyEntity instanceof AliasesProvider ?
				new TermIndexAliasesProvider( $this->termIndex, $entityId ) :
				null;
		} else {
			$entity = $this->entityRevisionLookup->getEntityRevision( $entityId, $revisionId )->getEntity();
			$labelsProvider = $entity;
			$descriptionsProvider = $entity;
			$aliasesProvider = $entity instanceof AliasesProvider ? $entity : null;
		}

		return new EntityViewPlaceholderExpander(
			$this->templateFactory,
			$out->getUser(),
			$out->getLanguage(),
			$labelsProvider,
			$descriptionsProvider,
			$aliasesProvider,
			$this->userLanguageLookup,
			$this->termsLanguages,
			$this->languageNameLookup,
			new MediaWikiLocalizedTextProvider( $out->getLanguage()->getCode() )
		);
	}

}
