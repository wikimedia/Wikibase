<?php

namespace Wikibase\Repo\ParserOutput;

use InvalidArgumentException;
use Language;
use ParserOutput;
use SpecialPage;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\LanguageFallbackChain;
use Wikibase\Lib\Store\EntityInfo;
use Wikibase\Lib\Store\EntityInfoBuilder;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Repo\LinkedData\EntityDataFormatProvider;
use Wikibase\View\LocalizedTextProvider;
use Wikibase\View\Template\TemplateFactory;

/**
 * Creates the parser output for an entity.
 *
 * @license GPL-2.0-or-later
 * @author Bene* < benestar.wikimedia@gmail.com >
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Adrian Heine <adrian.heine@wikimedia.de>
 */
class EntityParserOutputGenerator {

	/**
	 * @var DispatchingEntityViewFactory
	 */
	private $entityViewFactory;

	/**
	 * @var ParserOutputJsConfigBuilder
	 */
	private $configBuilder;

	/**
	 * @var EntityTitleLookup
	 */
	private $entityTitleLookup;

	/**
	 * @var EntityInfoBuilder
	 */
	private $entityInfoBuilder;

	/**
	 * @var LanguageFallbackChain
	 */
	private $languageFallbackChain;

	/**
	 * @var TemplateFactory
	 */
	private $templateFactory;

	/**
	 * @var LocalizedTextProvider
	 */
	private $textProvider;

	/**
	 * @var EntityDataFormatProvider
	 */
	private $entityDataFormatProvider;

	/**
	 * @var EntityParserOutputDataUpdater[]
	 */
	private $dataUpdaters;

	/**
	 * @var string
	 */
	private $languageCode;

	/*
	 * @var Language
	 */
	private $language;

	/**
	 * @param DispatchingEntityViewFactory $entityViewFactory
	 * @param ParserOutputJsConfigBuilder $configBuilder
	 * @param EntityTitleLookup $entityTitleLookup
	 * @param EntityInfoBuilder $entityInfoBuilder
	 * @param LanguageFallbackChain $languageFallbackChain
	 * @param TemplateFactory $templateFactory
	 * @param LocalizedTextProvider $textProvider
	 * @param EntityDataFormatProvider $entityDataFormatProvider
	 * @param EntityParserOutputDataUpdater[] $dataUpdaters
	 * @param Language $language
	 */
	public function __construct(
		DispatchingEntityViewFactory $entityViewFactory,
		DispatchingEntityMetaTagsCreatorFactory $entityMetaTagsCreatorFactory,
		ParserOutputJsConfigBuilder $configBuilder,
		EntityTitleLookup $entityTitleLookup,
		EntityInfoBuilder $entityInfoBuilder,
		LanguageFallbackChain $languageFallbackChain,
		TemplateFactory $templateFactory,
		LocalizedTextProvider $textProvider,
		EntityDataFormatProvider $entityDataFormatProvider,
		array $dataUpdaters,
		Language $language
	) {
		$this->entityViewFactory = $entityViewFactory;
		$this->entityMetaTagsCreatorFactory = $entityMetaTagsCreatorFactory;
		$this->configBuilder = $configBuilder;
		$this->entityTitleLookup = $entityTitleLookup;
		$this->entityInfoBuilder = $entityInfoBuilder;
		$this->languageFallbackChain = $languageFallbackChain;
		$this->templateFactory = $templateFactory;
		$this->textProvider = $textProvider;
		$this->entityDataFormatProvider = $entityDataFormatProvider;
		$this->dataUpdaters = $dataUpdaters;
		$this->language = $language;
		$this->languageCode = $language->getCode();
	}

	/**
	 * Creates the parser output for the given entity revision.
	 *
	 * @param EntityDocument $entity
	 * @param bool $generateHtml
	 *
	 * @throws InvalidArgumentException
	 * @return ParserOutput
	 */
	public function getParserOutput(
		EntityDocument $entity,
		$generateHtml = true
	) {
		$parserOutput = new ParserOutput();

		$updaterCollection = new EntityParserOutputDataUpdaterCollection( $parserOutput, $this->dataUpdaters );
		$updaterCollection->updateParserOutput( $entity );

		$configVars = $this->configBuilder->build( $entity );
		$parserOutput->addJsConfigVars( $configVars );

		$entityMetaTagsCreator = $this->entityMetaTagsCreatorFactory->newEntityMetaTags( $entity->getType(), $this->language );

		$parserOutput->setExtensionData( 'wikibase-meta-tags', $entityMetaTagsCreator->getMetaTags( $entity ) );

		if ( $generateHtml ) {
			$this->addHtmlToParserOutput(
				$parserOutput,
				$entity,
				$this->getEntityInfo( $parserOutput )
			);
		} else {
			// If we don't have HTML, the ParserOutput in question
			// shouldn't be cacheable.
			$parserOutput->updateCacheExpiry( 0 );
		}

		//@todo: record sitelinks as iwlinks

		$this->addModules( $parserOutput );

		//FIXME: some places, like Special:NewItem, don't want to override the page title.
		//	 But we still want to use OutputPage::addParserOutput to apply the modules etc from the ParserOutput.
		//	 So, for now, we leave it to the caller to override the display title, if desired.
		// set the display title
		//$parserOutput->setTitleText( $entity>getLabel( $langCode ) );

		// Sometimes extensions like SpamBlacklist might call getParserOutput
		// before the id is assigned, during the process of creating a new entity.
		// in that case, no alternate links are added, which probably is no problem.
		$entityId = $entity->getId();
		if ( $entityId !== null ) {
			$this->addAlternateLinks( $parserOutput, $entityId );
		}

		return $parserOutput;
	}

	/**
	 * Fetches some basic entity information from a set of entity IDs.
	 *
	 * @param ParserOutput $parserOutput
	 *
	 * @return EntityInfo
	 */
	private function getEntityInfo( ParserOutput $parserOutput ) {
		/**
		 * Set in ReferencedEntitiesDataUpdater.
		 *
		 * @see ReferencedEntitiesDataUpdater::updateParserOutput
		 * @fixme Use ReferencedEntitiesDataUpdater::getEntityIds instead.
		 */
		$entityIds = $parserOutput->getExtensionData( 'referenced-entities' );

		if ( !is_array( $entityIds ) ) {
			wfLogWarning( '$entityIds from ParserOutput "referenced-entities" extension data'
				. ' expected to be an array' );
			$entityIds = [];
		}

		return $this->entityInfoBuilder->collectEntityInfo( $entityIds, $this->languageFallbackChain->getFetchLanguageCodes() );
	}

	private function addHtmlToParserOutput(
		ParserOutput $parserOutput,
		EntityDocument $entity,
		EntityInfo $entityInfo
	) {
		$entityView = $this->entityViewFactory->newEntityView(
			$this->language,
			$this->languageFallbackChain,
			$entity,
			$entityInfo
		);

		// Set the display title to display the label together with the item's id
		$titleHtml = $entityView->getTitleHtml( $entity );
		$parserOutput->setTitleText( $titleHtml );

		$viewContent = $entityView->getContent( $entity );
		$parserOutput->setText( $viewContent->getHtml() );

		$placeholders = $viewContent->getPlaceholders();
		foreach ( $placeholders as $key => $value ) {
			$parserOutput->setExtensionData( $key, $value );
		}
	}

	private function addModules( ParserOutput $parserOutput ) {
		// make css available for JavaScript-less browsers
		$parserOutput->addModuleStyles( [
			'wikibase.common',
			'jquery.ui.core.styles',
			'jquery.wikibase.statementview.RankSelector.styles',
			'jquery.wikibase.toolbar.styles',
			'jquery.wikibase.toolbarbutton.styles',
		] );

		// make sure required client-side resources will be loaded
		// FIXME: Separate the JavaScript that is also needed in read-only mode from
		// the JavaScript that is only necessary for editing.
		$parserOutput->addModules( 'wikibase.ui.entityViewInit' );
		$parserOutput->addModules( 'wikibase.entityPage.entityLoaded' );
	}

	/**
	 * Add alternate links as extension data.
	 * OutputPageBeforeHTMLHookHandler will add these to the OutputPage.
	 *
	 * @param ParserOutput $parserOutput
	 * @param EntityId $entityId
	 */
	private function addAlternateLinks( ParserOutput $parserOutput, EntityId $entityId ) {
		$entityDataFormatProvider = $this->entityDataFormatProvider;
		$subPagePrefix = $entityId->getSerialization() . '.';

		$links = [];

		foreach ( $entityDataFormatProvider->getSupportedFormats() as $format ) {
			$ext = $entityDataFormatProvider->getExtension( $format );

			if ( $ext !== null ) {
				$entityDataTitle = SpecialPage::getTitleFor( 'EntityData', $subPagePrefix . $ext );

				$links[] = [
					'rel' => 'alternate',
					'href' => $entityDataTitle->getCanonicalURL(),
					'type' => $entityDataFormatProvider->getMimeType( $format )
				];
			}
		}

		$parserOutput->setExtensionData( 'wikibase-alternate-links', $links );
	}

}
