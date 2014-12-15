<?php

namespace Wikibase;

use OutOfBoundsException;
use ParserOutput;
use ValueFormatters\FormatterOptions;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\SiteLinkList;
use Wikibase\DataModel\StatementListProvider;
use Wikibase\Lib\Store\EntityInfoBuilderFactory;
use Wikibase\Lib\Store\EntityInfoTermLookup;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Lib\Store\LanguageFallbackLabelLookup;
use Wikibase\Repo\View\EntityViewFactory;

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

	public function __construct(
		EntityViewFactory $entityViewFactory,
		ParserOutputJsConfigBuilder $configBuilder,
		EntityTitleLookup $entityTitleLookup,
		ValuesFinder $valuesFinder,
		EntityInfoBuilderFactory $entityInfoBuilderFactory,
		LanguageFallbackChain $languageFallbackChain,
		$languageCode
	) {
		$this->entityViewFactory = $entityViewFactory;
		$this->configBuilder = $configBuilder;
		$this->entityTitleLookup = $entityTitleLookup;
		$this->valuesFinder = $valuesFinder;
		$this->entityInfoBuilderFactory = $entityInfoBuilderFactory;
		$this->languageFallbackChain = $languageFallbackChain;
		$this->languageCode = $languageCode;

		$this->referencedEntitiesFinder = new ReferencedEntitiesFinder();
	}

	/**
	 * Creates the parser output for the given entity revision.
	 *
	 * @since 0.5
	 *
	 * @param EntityRevision $entityRevision
	 * @param bool $editable
	 * @param bool $generateHtml
	 *
	 * @return ParserOutput
	 */
	public function getParserOutput( EntityRevision $entityRevision, $editable = true,
		$generateHtml = true
	) {
		$parserOutput = new ParserOutput();

		$entity = $entityRevision->getEntity();

		if ( $entity instanceof StatementListProvider ) {
			$snaks = $entity->getStatements()->getAllSnaks();
		}
		else {
			$snaks = array();
		}

		$usedEntityIds = $this->referencedEntitiesFinder->findSnakLinks( $snaks );
		$entityInfo = $this->getEntityInfo( $usedEntityIds );

		$configVars = $this->configBuilder->build( $entity, $entityInfo );
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
		//@todo: record CommonsMedia values as imagelinks

		$this->addModules( $parserOutput, $editable );

		//FIXME: some places, like Special:NewItem, don't want to override the page title.
		//	 But we still want to use OutputPage::addParserOutput to apply the modules etc from the ParserOutput.
		//	 So, for now, we leave it to the caller to override the display title, if desired.
		// set the display title
		//$parserOutput->setTitleText( $entity>getLabel( $langCode ) );

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
		foreach ( $entityIds as $entityId ) {
			$parserOutput->addLink( $this->entityTitleLookup->getTitleForId( $entityId ) );
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
	 * @return array obtained from EntityInfoBuilder::getEntityInfo
	 */
	private function getEntityInfo( array $entityIds ) {
		wfProfileIn( __METHOD__ );

		$entityInfoBuilder = $this->entityInfoBuilderFactory->newEntityInfoBuilder( $entityIds );

		$entityInfoBuilder->resolveRedirects();
		$entityInfoBuilder->removeMissing();

		// @todo: apply language fallback!
		$entityInfoBuilder->collectTerms(
			array( 'label', 'description' ),
			array( $this->languageCode )
		);

		$entityInfoBuilder->collectDataTypes();
		$entityInfoBuilder->retainEntityInfo( $entityIds );

		$entityInfo = $entityInfoBuilder->getEntityInfo();

		wfProfileOut( __METHOD__ );
		return $entityInfo;
	}

	/**
	 * @param ParserOutput $parserOutput
	 * @param SiteLinkList $siteLinkList
	 */
	private function addBadgesToParserOutput( ParserOutput $parserOutput, SiteLinkList $siteLinkList ) {
		foreach ( $siteLinkList as $siteLink ) {
			foreach ( $siteLink->getBadges() as $badge ) {
				$parserOutput->addLink( $this->entityTitleLookup->getTitleForId( $badge ) );
			}
		}
	}

	/**
	 * @param ParserOutput $parserOutput
	 * @param Entity $entity
	 */
	private function addTitleTextToParserOutput( ParserOutput $parserOutput, Entity $entity ) {
		$preferred = $this->languageFallbackChain->extractPreferredValue( $entity->getLabels() );

		if ( is_array( $preferred ) ) {
			$titleText = $preferred['value'];
		} else {
			$entityId = $entity->getId();

			if ( !$entityId ) {
				return;
			}

			$titleText = $entityId->getSerialization();
		}

		$parserOutput->setExtensionData( 'wikibase-titletext', $titleText );
	}

	/**
	 * @param ParserOutput $parserOutput
	 * @param EntityRevision $entityRevision
	 * @param array $entityInfo obtained from EntityInfoBuilder::getEntityInfo
	 * @param boolean $editable
	 */
	private function addHtmlToParserOutput(
		ParserOutput $parserOutput,
		EntityRevision $entityRevision,
		array $entityInfo,
		$editable
	) {

		$labelLookup = new LanguageFallbackLabelLookup(
			new EntityInfoTermLookup( $entityInfo ),
			$this->languageFallbackChain
		);

		$entityView = $this->entityViewFactory->newEntityView(
			$entityRevision->getEntity()->getType(),
			$this->languageCode,
			$this->languageFallbackChain,
			$labelLookup
		);

		$html = $entityView->getHtml( $entityRevision, $entityInfo, $editable );
		$parserOutput->setText( $html );
		$parserOutput->setExtensionData( 'wikibase-view-chunks', $entityView->getPlaceholders() );
	}

	private function addModules( ParserOutput $parserOutput, $editable ) {
		// make css available for JavaScript-less browsers
		$parserOutput->addModuleStyles( array(
			'wikibase.common',
			'wikibase.toc',
			'jquery.ui.core',
			'jquery.wikibase.statementview',
			'jquery.wikibase.toolbar',
		) );

		if ( $editable ) {
			// make sure required client sided resources will be loaded:
			$parserOutput->addModules( 'wikibase.ui.entityViewInit' );
		}
	}

}
