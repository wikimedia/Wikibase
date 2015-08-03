<?php

namespace Wikibase;

use InvalidArgumentException;
use LinkBatch;
use ParserOptions;
use ParserOutput;
use SpecialPage;
use Title;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\SiteLink;
use Wikibase\DataModel\SiteLinkList;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikibase\DataModel\Term\FingerprintProvider;
use Wikibase\Lib\Store\EntityInfo;
use Wikibase\Lib\Store\EntityInfoBuilderFactory;
use Wikibase\Lib\Store\EntityInfoTermLookup;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookup;
use Wikibase\Repo\LinkedData\EntityDataFormatProvider;
use Wikibase\Repo\View\RepoSpecialPageLinker;
use Wikibase\View\EmptyEditSectionGenerator;
use Wikibase\View\EntityViewFactory;
use Wikibase\View\Template\TemplateFactory;
use Wikibase\View\ToolbarEditSectionGenerator;

/**
 * Creates the parser output for an entity.
 *
 * @note This class relies on Entity and behaves differently when you pass an item as paramater.
 *		 We should split this into classes for items and other types of entities.
 *
 * @since 0.5
 *
 * @license GNU GPL v2+
 * @author Bene* < benestar.wikimedia@gmail.com >
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class EntityParserOutputGenerator {

	/**
	 * @var EntityViewFactory
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
	 * @var ValuesFinder
	 */
	private $valuesFinder;

	/**
	 * @var EntityInfoBuilderFactory
	 */
	private $entityInfoBuilderFactory;

	/**
	 * @var LanguageFallbackChain
	 */
	private $languageFallbackChain;

	/**
	 * @var string
	 */
	private $languageCode;

	/**
	 * @var ReferencedEntitiesFinder
	 */
	private $referencedEntitiesFinder;

	/**
	 * @var TemplateFactory
	 */
	private $templateFactory;

	/**
	 * @var EntityDataFormatProvider
	 */
	private $entityDataFormatProvider;

	public function __construct(
		EntityViewFactory $entityViewFactory,
		ParserOutputJsConfigBuilder $configBuilder,
		EntityTitleLookup $entityTitleLookup,
		ValuesFinder $valuesFinder,
		EntityInfoBuilderFactory $entityInfoBuilderFactory,
		LanguageFallbackChain $languageFallbackChain,
		$languageCode,
		ReferencedEntitiesFinder $referencedEntitiesFinder,
		TemplateFactory $templateFactory,
		EntityDataFormatProvider $entityDataFormatProvider
	) {
		$this->entityViewFactory = $entityViewFactory;
		$this->configBuilder = $configBuilder;
		$this->entityTitleLookup = $entityTitleLookup;
		$this->valuesFinder = $valuesFinder;
		$this->entityInfoBuilderFactory = $entityInfoBuilderFactory;
		$this->languageFallbackChain = $languageFallbackChain;
		$this->languageCode = $languageCode;
		$this->referencedEntitiesFinder = $referencedEntitiesFinder;
		$this->templateFactory = $templateFactory;
		$this->entityDataFormatProvider = $entityDataFormatProvider;
	}

	/**
	 * Creates the parser output for the given entity revision.
	 *
	 * @since 0.5
	 *
	 * @note: the new ParserOutput will be registered as a watcher with $options by
	 *        calling $options->registerWatcher( array( $parserOutput, 'recordOption' ) ).
	 *
	 * @param EntityRevision $entityRevision
	 * @param ParserOptions $options
	 * @param bool $generateHtml
	 *
	 * @return ParserOutput
	 */
	public function getParserOutput(
		EntityRevision $entityRevision,
		ParserOptions $options,
		$generateHtml = true
	) {
		$parserOutput = new ParserOutput();
		$options->registerWatcher( array( $parserOutput, 'recordOption' ) );

		// @note: SIDE EFFECT: the call to $options->getUserLang() effectively splits
		// the parser cache. It gets reported to the ParserOutput which is registered
		// as a watcher to $options above.
		if ( $options->getUserLang() !== $this->languageCode ) {
			// The language requested by $parserOptions is different from what
			// this generator was configured for. This indicates an inconsistency.
			throw new InvalidArgumentException( 'Unexpected user language in ParserOptions' );
		}

		$entity = $entityRevision->getEntity();

		if ( $entity instanceof StatementListProvider ) {
			$snaks = $entity->getStatements()->getAllSnaks();
		} else {
			$snaks = array();
		}

		$editable = $options->getEditSection();

		$usedEntityIds = $this->referencedEntitiesFinder->findSnakLinks( $snaks );

		// FIXME: Bad
		if ( $entity instanceof Item ) {
			foreach ( $entity->getSiteLinkList()->getIterator() as $sitelink ) {
				$usedEntityIds = array_merge( $usedEntityIds, $sitelink->getBadges() );
			}
		}

		$entityInfo = $this->getEntityInfo( $usedEntityIds );

		$configVars = $this->configBuilder->build( $entity );
		$parserOutput->addJsConfigVars( $configVars );

		$this->addLinksToParserOutput( $parserOutput, $usedEntityIds, $snaks );

		// FIXME: OCP violation - https://phabricator.wikimedia.org/T75495
		if ( $entity instanceof Item ) {
			$this->addBadgesToParserOutput( $parserOutput, $entity->getSiteLinkList() );
		}

		$this->addTitleTextToParserOutput( $parserOutput, $entity );

		if ( $generateHtml ) {
			$this->addHtmlToParserOutput(
				$parserOutput,
				$entityRevision,
				$entityInfo,
				$editable
			);
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
		if ( $entity->getId() !== null ) {
			$this->addAlternateLinks( $parserOutput, $entity->getId() );
		}

		return $parserOutput;
	}

	/**
	 * @param ParserOutput $parserOutput
	 * @param EntityId[] $usedEntityIds
	 * @param Snak[] $snaks
	 */
	private function addLinksToParserOutput( ParserOutput $parserOutput, array $usedEntityIds,
		array $snaks
	) {
		$this->addEntityLinksToParserOutput( $parserOutput, $usedEntityIds );
		$this->addExternalLinksToParserOutput( $parserOutput, $snaks );
		$this->addImageLinksToParserOutput( $parserOutput, $snaks );
	}

	/**
	 * @param ParserOutput $parserOutput
	 * @param EntityId[] $entityIds
	 */
	private function addEntityLinksToParserOutput( ParserOutput $parserOutput, array $entityIds ) {
		$linkBatch = new LinkBatch();

		foreach ( $entityIds as $entityId ) {
			$linkBatch->addObj( $this->entityTitleLookup->getTitleForId( $entityId ) );
		}

		$pages = $linkBatch->doQuery();

		if ( $pages === false ) {
			return;
		}

		foreach ( $pages as $page ) {
			$title = Title::makeTitle( $page->page_namespace, $page->page_title );
			$parserOutput->addLink( $title, $page->page_id );
		}
	}

	/**
	 * @param ParserOutput $parserOutput
	 * @param Snak[] $snaks
	 */
	private function addExternalLinksToParserOutput( ParserOutput $parserOutput, array $snaks ) {
		// treat URL values as external links ------
		$usedUrls = $this->valuesFinder->findFromSnaks( $snaks, 'url' );

		foreach ( $usedUrls as $url ) {
			$value = $url->getValue();
			if ( is_string( $value ) ) {
				$parserOutput->addExternalLink( $value );
			}
		}
	}

	/**
	 * Treat CommonsMedia values as file transclusions
	 *
	 * @param ParserOutput $parserOutput
	 * @param array $snaks
	 */
	private function addImageLinksToParserOutput( ParserOutput $parserOutput, array $snaks ) {
		$usedImages = $this->valuesFinder->findFromSnaks( $snaks, 'commonsMedia' );

		foreach ( $usedImages as $image ) {
			$value = $image->getValue();
			if ( is_string( $value ) ) {
				$parserOutput->addImage( str_replace( ' ', '_', $value ) );
			}
		}
	}

	/**
	 * Fetches some basic entity information from a set of entity IDs.
	 *
	 * @param EntityId[] $entityIds
	 *
	 * @return EntityInfo
	 */
	private function getEntityInfo( array $entityIds ) {
		$entityInfoBuilder = $this->entityInfoBuilderFactory->newEntityInfoBuilder( $entityIds );

		$entityInfoBuilder->resolveRedirects();
		$entityInfoBuilder->removeMissing();

		$entityInfoBuilder->collectTerms(
			array( 'label', 'description' ),
			$this->languageFallbackChain->getFetchLanguageCodes()
		);

		$entityInfoBuilder->collectDataTypes();
		$entityInfoBuilder->retainEntityInfo( $entityIds );

		return $entityInfoBuilder->getEntityInfo();
	}

	/**
	 * @param ParserOutput $parserOutput
	 * @param SiteLinkList $siteLinkList
	 */
	private function addBadgesToParserOutput( ParserOutput $parserOutput, SiteLinkList $siteLinkList ) {
		/** @var SiteLink $siteLink */
		foreach ( $siteLinkList as $siteLink ) {
			foreach ( $siteLink->getBadges() as $badge ) {
				$parserOutput->addLink( $this->entityTitleLookup->getTitleForId( $badge ) );
			}
		}
	}

	/**
	 * @param ParserOutput $parserOutput
	 * @param EntityDocument $entity
	 */
	private function addTitleTextToParserOutput( ParserOutput $parserOutput, EntityDocument $entity ) {
		$titleText = null;

		if ( $entity instanceof FingerprintProvider ) {
			$labels = $entity->getFingerprint()->getLabels()->toTextArray();
			$preferred = $this->languageFallbackChain->extractPreferredValue( $labels );

			if ( is_array( $preferred ) ) {
				$titleText = $preferred['value'];
			}
		}

		if ( !is_string( $titleText ) ) {
			$entityId = $entity->getId();

			if ( $entityId !== null ) {
				$titleText = $entityId->getSerialization();
			}
		}

		$parserOutput->setExtensionData( 'wikibase-titletext', $titleText );
	}

	/**
	 * @param ParserOutput $parserOutput
	 * @param EntityRevision $entityRevision
	 * @param EntityInfo $entityInfo
	 * @param bool $editable
	 */
	private function addHtmlToParserOutput(
		ParserOutput $parserOutput,
		EntityRevision $entityRevision,
		EntityInfo $entityInfo,
		$editable = true
	) {

		$labelDescriptionLookup = new LanguageFallbackLabelDescriptionLookup(
			new EntityInfoTermLookup( $entityInfo ),
			$this->languageFallbackChain
		);

		$editSectionGenerator = $editable ? new ToolbarEditSectionGenerator(
			new RepoSpecialPageLinker(),
			$this->templateFactory
		) : new EmptyEditSectionGenerator();

		$entityView = $this->entityViewFactory->newEntityView(
			$entityRevision->getEntity()->getType(),
			$this->languageCode,
			$labelDescriptionLookup,
			$this->languageFallbackChain,
			$editSectionGenerator
		);

		// Set the display title to display the label together with the item's id
		$titleHtml = $entityView->getTitleHtml( $entityRevision );
		$parserOutput->setDisplayTitle( $titleHtml );

		$html = $entityView->getHtml( $entityRevision );
		$parserOutput->setText( $html );
		$parserOutput->setExtensionData( 'wikibase-view-chunks', $entityView->getPlaceholders() );

		// Force parser cache split by whether edit links are show.
		// MediaWiki core has the ability to split on editsection, but does not trigger it
		// automatically when $parserOptions->getEditSection() is called. Presumably this
		// is because core uses <mw:editsection> tags that are substituted by ParserOutput::getText
		// using the info from ParserOutput::getEditSectionTokens.
		$parserOutput->recordOption( 'editsection' );

		// Since the output depends on the user language, we must make sure
		// ParserCache::getKey() includes it in the cache key.
		$parserOutput->recordOption( 'userlang' );
	}

	/**
	 * @param ParserOutput $parserOutput
	 */
	private function addModules( ParserOutput $parserOutput ) {
		// make css available for JavaScript-less browsers
		$parserOutput->addModuleStyles( array(
			'wikibase.common',
			'jquery.ui.core.styles',
			'jquery.wikibase.statementview.RankSelector.styles',
			'jquery.wikibase.toolbar.styles',
		) );

		// make sure required client-side resources will be loaded
		// FIXME: Separate the JavaScript that is also needed in read-only mode from
		// the JavaScript that is only necessary for editing.
		// Then load JavaScript accordingly depending on $editable.
		$parserOutput->addModules( 'wikibase.ui.entityViewInit' );
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

		$links = array();

		foreach ( $entityDataFormatProvider->getSupportedFormats() as $format ) {
			$ext = $entityDataFormatProvider->getExtension( $format );

			if ( $ext !== null ) {
				$entityDataTitle = SpecialPage::getTitleFor( 'EntityData', $subPagePrefix . $ext );

				$links[] = array(
					'rel' => 'alternate',
					'href' => $entityDataTitle->getCanonicalURL(),
					'type' => $entityDataFormatProvider->getMimeType( $format )
				);
			}
		}

		$parserOutput->setExtensionData( 'wikibase-alternate-links', $links );
	}

}
