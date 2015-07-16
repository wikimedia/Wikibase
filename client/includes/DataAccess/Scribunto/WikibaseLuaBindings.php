<?php

namespace Wikibase\Client\DataAccess\Scribunto;

use InvalidArgumentException;
use OutOfBoundsException;
use ParserOptions;
use Wikibase\Client\Usage\UsageAccumulator;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Store\EntityLookup;
use Wikibase\Lib\Store\LabelDescriptionLookup;
use Wikibase\Lib\Store\SiteLinkLookup;
use Wikibase\Lib\Store\StorageException;
use Wikibase\SettingsArray;

/**
 * Actual implementations of various functions to access Wikibase functionality
 * through Scribunto.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Jens Ohlig < jens.ohlig@wikimedia.de >
 * @author Marius Hoch < hoo@online.de >
 */
class WikibaseLuaBindings {

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var SiteLinkLookup
	 */
	private $siteLinkTable;

	/**
	 * @var SettingsArray
	 */
	private $settings;

	/**
	 * @var LabelDescriptionLookup
	 */
	private $labelDescriptionLookup;

	/**
	 * @var UsageAccumulator
	 */
	private $usageAccumulator;

	/**
	 * @var ParserOptions
	 */
	private $parserOptions;

	/**
	 * @var string
	 */
	private $siteId;

	/**
	 * @param EntityIdParser $entityIdParser
	 * @param EntityLookup $entityLookup
	 * @param SiteLinkLookup $siteLinkTable
	 * @param SettingsArray $settings
	 * @param LabelDescriptionLookup $labelDescriptionLookup
	 * @param UsageAccumulator $usageAccumulator for tracking title usage via getEntityId.
	 * @param ParserOptions $parserOptions
	 * @param string $siteId
	 *
	 * @note: label usage is not tracked in $usageAccumulator. This should be done inside
	 *        the $labelDescriptionLookup or an underlying TermsLookup.
	 */
	public function __construct(
		EntityIdParser $entityIdParser,
		EntityLookup $entityLookup,
		SiteLinkLookup $siteLinkTable,
		SettingsArray $settings,
		LabelDescriptionLookup $labelDescriptionLookup,
		UsageAccumulator $usageAccumulator,
		ParserOptions $parserOptions,
		$siteId
	) {
		$this->entityIdParser = $entityIdParser;
		$this->entityLookup = $entityLookup;
		$this->siteLinkTable = $siteLinkTable;
		$this->settings = $settings;
		$this->labelDescriptionLookup = $labelDescriptionLookup;
		$this->usageAccumulator = $usageAccumulator;
		$this->parserOptions = $parserOptions;
		$this->siteId = $siteId;
	}

	/**
	 * Get entity id from page title.
	 *
	 * @since 0.5
	 *
	 * @param string $pageTitle
	 *
	 * @return string|null
	 */
	public function getEntityId( $pageTitle ) {
		$id = $this->siteLinkTable->getItemIdForLink( $this->siteId, $pageTitle );

		if ( !$id ) {
			return null;
		}

		$this->usageAccumulator->addTitleUsage( $id );
		return $id->getSerialization();
	}

	/**
	 * @param string $setting
	 *
	 * @return mixed
	 */
	public function getSetting( $setting ) {
		return $this->settings->getSetting( $setting );
	}

	/**
	 * @param string $prefixedEntityId
	 *
	 * @since 0.5
	 * @return string|null Null if entity couldn't be found/ no label present
	 */
	public function getLabel( $prefixedEntityId ) {
		try {
			$entityId = $this->entityIdParser->parse( $prefixedEntityId );
		} catch ( EntityIdParsingException $e ) {
			return null;
		}

		try {
			$term = $this->labelDescriptionLookup->getLabel( $entityId );
		} catch ( StorageException $ex ) {
			return null;
		} catch ( OutOfBoundsException $ex ) {
			return null;
		}

		// NOTE: This tracks a label usage in the wiki's content language.
		return $term->getText();
	}

	/**
	 * @param string $prefixedEntityId
	 *
	 * @since 0.5
	 * @return string|null Null if entity couldn't be found/ no description present
	 */
	public function getDescription( $prefixedEntityId ) {
		try {
			$entityId = $this->entityIdParser->parse( $prefixedEntityId );
		} catch ( EntityIdParsingException $e ) {
			return null;
		}

		try {
			$term = $this->labelDescriptionLookup->getDescription( $entityId );
		} catch ( StorageException $ex ) {
			return null;
		} catch ( OutOfBoundsException $ex ) {
			return null;
		}

		// XXX: This. Sucks. A lot.
		// Also notes about language fallbacks from getLabel apply
		$this->usageAccumulator->addOtherUsage( $entityId );
		return $term->getText();
	}

	/**
	 * @param string $prefixedEntityId
	 *
	 * @since 0.5
	 * @return string|null Null if entity couldn't be found/ no label present
	 */
	public function getSiteLinkPageName( $prefixedEntityId ) {
		try {
			$itemId = new ItemId( $prefixedEntityId );
		} catch ( InvalidArgumentException $e ) {
			return null;
		}

		/** @var Item $item */
		$item = $this->entityLookup->getEntity( $itemId );
		if ( !$item || !$item->getSiteLinkList()->hasLinkWithSiteId( $this->siteId ) ) {
			return null;
		}

		$this->usageAccumulator->addTitleUsage( $itemId );
		return $item->getSiteLinkList()->getBySiteId( $this->siteId )->getPageName();
	}

	/**
	 * Get the user's language.
	 * Side effect: Splits the parser cache by user language!
	 *
	 * @since 0.5
	 *
	 * @return string Language code
	 */
	public function getUserLang() {
		// Note: We can't just inject the user language, as getting it from ParserOptions
		// already splits the cache (which also is an intended side effect here).
		return $this->parserOptions->getUserLang();
	}

}
