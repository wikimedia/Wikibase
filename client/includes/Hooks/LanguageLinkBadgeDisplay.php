<?php

namespace Wikibase\Client\Hooks;

use Language;
use OutputPage;
use ParserOutput;
use Sanitizer;
use Title;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\LabelDescriptionLookup;
use Wikibase\DataModel\Services\Lookup\LabelDescriptionLookupException;
use Wikibase\DataModel\SiteLink;

/**
 * Provides access to the badges of the current page's sitelinks
 * and adds some properties to the HTML output to display them.
 *
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Bene* < benestar.wikimedia@gmail.com >
 * @author Daniel Kinzler
 */
class LanguageLinkBadgeDisplay {

	/**
	 * @var LabelDescriptionLookup;
	 */
	protected $labelDescriptionLookup;

	/**
	 * @var array
	 */
	protected $badgeClassNames;

	/**
	 * @var Language
	 */
	protected $language;

	/**
	 * @param LabelDescriptionLookup $labelDescriptionLookup
	 * @param array $badgeClassNames
	 * @param Language $language
	 */
	public function __construct(
		LabelDescriptionLookup $labelDescriptionLookup,
		array $badgeClassNames,
		Language $language
	) {
		$this->labelDescriptionLookup = $labelDescriptionLookup;
		$this->badgeClassNames = $badgeClassNames;
		$this->language = $language;
	}

	/**
	 * Attaches info about link badges in the given OutputPage, for later retrieval
	 * and processing by applyBadges().
	 *
	 * This is typically called in the context of parsing a wiki page.
	 *
	 * @param SiteLink[] $langLinks Site links indexed by local interwiki prefix.
	 * @param ParserOutput $parserOutput The output page to set the wikibase_badges property on.
	 */
	public function attachBadgesToOutput( array $langLinks, ParserOutput $parserOutput ) {
		$badgeInfoForAllLinks = array();

		foreach ( $langLinks as $key => $link ) {
			$badges = $link->getBadges();

			if ( !empty( $badges ) ) {
				$badgeInfoForAllLinks[$key] = $this->getBadgeInfo( $badges );
			}
		}

		$parserOutput->setExtensionData( 'wikibase_badges', $badgeInfoForAllLinks );
	}

	/**
	 * Applies the badges described in the wikibase_badges property of $output to
	 * the language link to $languageLinkTitle. The badge info for this linked is
	 * looked up in the wikibase_badges data using the key returned by
	 * $languageLinkTitle->getInterwiki().
	 *
	 * This is generally called in the context of generating skin output.
	 *
	 * @since 0.5
	 *
	 * @param array &$languageLink
	 * @param Title $languageLinkTitle
	 * @param OutputPage $output The output page to take the wikibase_badges property from.
	 */
	public function applyBadges( array &$languageLink, Title $languageLinkTitle, OutputPage $output ) {
		$badges = $output->getProperty( 'wikibase_badges' );

		if ( empty( $badges ) ) {
			return;
		}

		$navId = $languageLinkTitle->getInterwiki();
		if ( !isset( $badges[$navId] ) ) {
			return;
		}

		/** @var array $linksBadgeInfo an associative array with the keys 'class', and 'itemtitle'. */
		$linksBadgeInfo = $badges[$navId];

		if ( isset( $languageLink['class'] ) ) {
			$languageLink['class'] .= ' ' . $linksBadgeInfo['class'];
		} else {
			$languageLink['class'] = $linksBadgeInfo['class'];
		}

		$languageLink['itemtitle'] = $linksBadgeInfo['label'];
	}

	/**
	 * Builds badge information for the given badges.
	 * CSS classes are derived from the given list of badges, and any extra badge class
	 * names specified in the badgeClassNames setting are added.
	 * For badges that have a such an extra class name assigned, this also
	 * adds a title according to the items' labels. Other badges do not have labels
	 * added to the link's title attribute, so the can be effectively ignored
	 * on this client wiki.
	 *
	 * @param ItemId[] $badgeIds
	 *
	 * @return array An associative array with the keys 'class' and 'itemtitle' with assigned
	 * string values. These fields correspond to the fields in the description array for language
	 * links used by the SkinTemplateGetLanguageLink hook and expected by the applyBadges()
	 * function.
	 */
	private function getBadgeInfo( array $badgeIds ) {
		$classes = array();
		$labels = array();

		foreach ( $badgeIds as $badgeId ) {
			$badgeSerialization = $badgeId->getSerialization();
			$classes[] = 'badge-' . Sanitizer::escapeClass( $badgeSerialization );

			// nicer classes for well known badges
			if ( isset( $this->badgeClassNames[$badgeSerialization] ) ) {
				// add class name
				$classes[] = Sanitizer::escapeClass( $this->badgeClassNames[$badgeSerialization] );

				// add label (but only if this badge is well known on this wiki)
				$label = $this->getLabel( $badgeId );

				if ( $label !== null ) {
					$labels[] = $label;
				}
			}
		}

		$info = array(
			'class' => implode( ' ', $classes ),
			'label' => $this->language->commaList( $labels ),
		);

		return $info;
	}

	/**
	 * Returns the label for the given badge.
	 *
	 * @param ItemId $badgeId
	 *
	 * @return string|null
	 */
	private function getLabel( ItemId $badgeId ) {
		try {
			$term = $this->labelDescriptionLookup->getLabel( $badgeId );
		} catch ( LabelDescriptionLookupException $ex ) {
			return null;
		}

		if ( $term !== null ) {
			return $term->getText();
		}

		return null;
	}

}
