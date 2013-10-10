<?php

namespace Wikibase;

use Html, ParserOutput, Title, Language, OutputPage, Sites, MediaWikiSite;
use Wikibase\DataModel\SimpleSiteLink;
use Wikibase\Repo\WikibaseRepo;

/**
 * Class for creating views for Wikibase\Item instances.
 * For the Wikibase\Item this basically is what the Parser is for WikitextContent.
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 * @author Daniel Werner
 * @author Adam Shorland
 */
class ItemView extends EntityView {

	/**
	 * @see EntityView::getInnerHtml
	 */
	public function getInnerHtml( EntityRevision $entityRevision, Language $lang, $editable = true ) {
		$html = parent::getInnerHtml( $entityRevision, $lang, $editable );

		// add site-links to default entity stuff
		$html .= $this->getHtmlForSiteLinks( $entityRevision->getEntity() );

		return $html;
	}

	/**
	 * Builds and returns the HTML representing a WikibaseEntity's site-links.
	 *
	 * @since 0.1
	 *
	 * @param Item $item the entity to render
	 *
	 * @return string
	 */
	public function getHtmlForSiteLinks( Item $item ) {
		$groups = WikibaseRepo::getDefaultInstance()->getSettings()->getSetting( "siteLinkGroups" );
		$html = '';

		foreach ( $groups as $group ) {
			$html .= $this->getHtmlForSiteLinkGroup( $item, $group );
		}

		return $html;
	}

	/**
	 * Builds and returns the HTML representing a group of a WikibaseEntity's site-links.
	 *
	 * @since 0.4
	 *
	 * @param Item $item the entity to render
	 * @param string $group a site group ID
	 *
	 * @return string
	 */
	public function getHtmlForSiteLinkGroup( Item $item, $group ) {
		$siteLinksHeadingHtml = wfTemplate(
			'wb-section-heading-sitelinks',
			wfMessage( 'wikibase-sitelinks-' . $group )->parse(), // heading
			htmlspecialchars( 'sitelinks-' . $group, ENT_QUOTES ) // ID
		// TODO: support entity-id as prefix for element IDs.
		);

		$siteLinks = $this->getSiteLinks( $item, $group );
		$specialGroups = WikibaseRepo::getDefaultInstance()->getSettings()->getSetting( "specialSiteLinkGroups" );
		$editLink = $this->getEditUrl( 'SetSiteLink', $item, null );
		$groupName = in_array( $group, $specialGroups ) ? 'special' : $group;

		$sitelinksTableHtml = wfTemplate(
			'wb-sitelinks-table',
			$this->getHead( $siteLinks, $group, $specialGroups ),
			$this->getBody( $siteLinks, $group, $specialGroups, $editLink ),
			$this->getFoot( $siteLinks, $group, $editLink ),
			htmlspecialchars( $groupName )
		);

		return $siteLinksHeadingHtml . $sitelinksTableHtml;
	}

	/**
	 * Returns an array of sitelinks that are on the given Item and in the given group
	 * @param Item $item the entity to render
	 * @param string $group a site group ID
	 * @return SiteLink[] $siteLinks
	 */
	private function getSiteLinks( Item $item, $group ) {
		$siteLinks = array();
		foreach( $item->getSimpleSiteLinks() as $siteLink ) {
			// FIXME: depracted method usage
			$site = \Sites::singleton()->getSite( $siteLink->getSiteId() );

			if ( $site === null ) {
				continue;
			}

			$link = new SiteLink( $site, $siteLink->getPageName() );

			if ( $site->getGroup() === $group ) {
				$siteLinks[] = $link;
			}
		}
		return $siteLinks;
	}

	/**
	 * @param SiteLink[] $siteLinks
	 * @param string $group
	 * @param array $specialGroups
	 *
	 * @return string
	 */
	private function getHead( array $siteLinks, $group, array $specialGroups ) {
		// FIXME: quickfix to allow a custom site-name / handling for groups defined in $wgSpecialSiteLinkGroups
		$siteNameMessageKey = 'wikibase-sitelinks-sitename-columnheading';
		if ( in_array( $group, $specialGroups ) ) {
			$siteNameMessageKey .= '-special';
		}

		if( !empty( $siteLinks ) ) {
			return wfTemplate( 'wb-sitelinks-thead',
				wfMessage( $siteNameMessageKey )->parse(),
				wfMessage( 'wikibase-sitelinks-siteid-columnheading' )->parse(),
				wfMessage( 'wikibase-sitelinks-link-columnheading' )->parse()
			);
		}
		return '';
	}

	/**
	 * @param SiteLink[] $siteLinks
	 * @param string $group
	 * @param array $specialGroups
	 * @param string $editLink
	 * @return string
	 */
	private function getBody( array $siteLinks, $group, array $specialGroups, $editLink ) {
		$tbody = '';
		$siteLinks = $this->sortSiteLinks( $siteLinks );
		$i = 0;

		foreach( $siteLinks as $link ) {
			$alternatingClass = ( $i++ % 2 ) ? 'even' : 'uneven';
			$tbody .= $this->getBodyForSitelink( $link, $alternatingClass, $group, $specialGroups, $editLink );
		}
		return $tbody;
	}

	/**
	 * @param SiteLink $link
	 * @param string $class
	 * @param string $group
	 * @param array $specialGroups
	 * @param string $editLink
	 *
	 * @return string
	 */
	private function getBodyForSitelink( SiteLink $link, $class, $group, $specialGroups, $editLink ) {
		$site = $link->getSite();

		if ( $site->getDomain() === '' ) {
			// XXX: link is pointing to an unknown site. hide it? make it red? strike it out?
			return wfTemplate( 'wb-sitelink-unknown',
				$class,
				htmlspecialchars( $link->getSite()->getGlobalId() ),
				htmlspecialchars( $link->getPage() ),
				$this->getHtmlForEditSection( $editLink, 'td' )
			);

		} else {
			$languageCode = $site->getLanguageCode();
			$escapedSiteId = htmlspecialchars( $site->getGlobalId() );

			// FIXME: this is a quickfix to allow a custom site-name for groups defined in $wgSpecialSiteLinkGroups instead of showing the language-name
			if ( in_array( $group, $specialGroups ) ) {
				$siteNameMsg = wfMessage( 'wikibase-sitelinks-sitename-' . $site->getGlobalId() );
				$siteName = $siteNameMsg->exists() ? $siteNameMsg->parse() : $site->getGlobalId();
			} else {
				// TODO: get an actual site name rather then just the language
				$siteName = htmlspecialchars( Utils::fetchLanguageName( $languageCode ) );
			}

			// TODO: for non-JS, also set the dir attribute on the link cell;
			// but do not build language objects for each site since it causes too much load
			// and will fail when having too much site links
			return wfTemplate( 'wb-sitelink',
				$languageCode,
				$class,
				$siteName,
				$escapedSiteId, // displayed site ID
				htmlspecialchars( $link->getUrl() ),
				htmlspecialchars( $link->getPage() ),
				$this->getHtmlForEditSection( $editLink . '/' . $escapedSiteId, 'td' ),
				$escapedSiteId // ID used in classes
			);
		}
	}

	/**
	 * @param SiteLink[] $siteLinks
	 * @param string $group
	 * @param string $editLink
	 *
	 * @return string
	 */
	private function getFoot( array $siteLinks, $group, $editLink ) {
		// Batch load the sites we need info about during the building of the sitelink list.
		$sites = Sites::singleton()->getSiteGroup( $group );

		// built table footer with button to add site-links, consider list could be complete!
		$isFull = count( $siteLinks ) >= count( $sites );

		return wfTemplate( 'wb-sitelinks-tfoot',
			$isFull ? wfMessage( 'wikibase-sitelinksedittool-full' )->parse() : '',
			$this->getHtmlForEditSection( $editLink, 'td', 'add', ! $isFull )
		);
	}

	/**
	 * Sort the sitelinks according to their global id
	 * @param SiteLink[] $siteLinks
	 * @return SiteLink[]
	 */
	private function sortSiteLinks( $siteLinks ) {
		$safetyCopy = $siteLinks; // keep a shallow copy;
		$sortOk = usort(
			$siteLinks,
			function( SiteLink $a, SiteLink $b ) {
				return strcmp( $a->getSite()->getGlobalId(), $b->getSite()->getGlobalId() );
			}
		);

		if ( !$sortOk ) {
			$siteLinks = $safetyCopy;
		}
		return $siteLinks;
	}

}
