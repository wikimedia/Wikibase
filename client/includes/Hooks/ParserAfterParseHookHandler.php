<?php

namespace Wikibase\Client\Hooks;

use Parser;
use StripState;
use StubUserLang;
use Wikibase\Client\WikibaseClient;
use Wikibase\InterwikiSorter;
use Wikibase\LangLinkHandler;
use Wikibase\NamespaceChecker;

/**
 * @since 0.5.
 *
 * @license GPL 2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Daniel Kinzler
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Marius Hoch < hoo@online.de >
 */
class ParserAfterParseHookHandler {

	/**
	 * @var NamespaceChecker
	 */
	private $namespaceChecker;

	/**
	 * @var LangLinkHandler
	 */
	private $langLinkHandler;

	/**
	 * @var InterwikiSorter
	 */
	private $interwikiSorter;

	/**
	 * @var bool
	 */
	private $alwaysSort;

	public static function newFromGlobalState() {
		global $wgLang;
		StubUserLang::unstub( $wgLang );

		$wikibaseClient = WikibaseClient::getDefaultInstance();
		$settings = $wikibaseClient->getSettings();

		$interwikiSorter = new InterwikiSorter(
			$settings->getSetting( 'sort' ),
			$settings->getSetting( 'interwikiSortOrders' ),
			$settings->getSetting( 'sortPrepend' )
		);

		return new ParserAfterParseHookHandler(
			$wikibaseClient->getNamespaceChecker(),
			$wikibaseClient->getLangLinkHandler(),
			$interwikiSorter,
			$settings->getSetting( 'alwaysSort' )
		);
	}

	/**
	 * Static handler for the ParserAfterParse hook.
	 *
	 * @param Parser|null &$parser
	 * @param string|null &$text Unused.
	 * @param StripState|null $stripState Unused.
	 *
	 * @return bool
	 */
	public static function onParserAfterParse( Parser &$parser = null, &$text = null, StripState $stripState = null ) {
		if ( $parser === null ) {
			return true;
		}

		// This hook tries to access repo SiteLinkTable
		// it interferes with any test that parses something, like a page or a message.
		if ( defined( 'MW_PHPUNIT_TEST' ) ) {
			return true;
		}

		// We only care about existing page content being rendered to HTML, not interface
		// messages or dynamic text or template4 expansion via the API.
		// CAVEAT: This means we also bail out on edit previews.
		if ( $parser->getRevisionId() === null || $parser->OutputType() !== Parser::OT_HTML ) {
			return true;
		}

		$handler = self::newFromGlobalState();
		return $handler->doParserAfterParse( $parser );
	}

	/**
	 * @param NamespaceChecker $namespaceChecker
	 * @param LangLinkHandler $langLinkHandler
	 * @param InterwikiSorter $sorter
	 * @param boolean $alwaysSort
	 */
	public function __construct(
		NamespaceChecker $namespaceChecker,
		LangLinkHandler $langLinkHandler,
		InterwikiSorter $sorter,
		$alwaysSort
	) {

		$this->namespaceChecker = $namespaceChecker;
		$this->langLinkHandler = $langLinkHandler;
		$this->interwikiSorter = $sorter;
		$this->alwaysSort = $alwaysSort;
	}

	/**
	 * Hook runs after internal parsing
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserAfterParse
	 *
	 * @param Parser &$parser
	 *
	 * @return bool
	 */
	public function doParserAfterParse( Parser &$parser ) {
		$title = $parser->getTitle();

		if ( !$this->namespaceChecker->isWikibaseEnabled( $title->getNamespace() ) ) {
			// shorten out
			return true;
		}

		// @todo split up the multiple responsibilities here and in lang link handler

		$parserOutput = $parser->getOutput();
		$useRepoLinks = $this->langLinkHandler->useRepoLinks( $title, $parserOutput );

		if ( $useRepoLinks ) {
			// add links
			$this->langLinkHandler->addLinksFromRepository( $title, $parserOutput );
		}

		$this->langLinkHandler->updateItemIdProperty( $title, $parserOutput );
		$this->langLinkHandler->updateOtherProjectsLinksData( $title, $parserOutput );

		if ( $useRepoLinks || $this->alwaysSort ) {
			$interwikiLinks = $parserOutput->getLanguageLinks();
			$sortedLinks = $this->interwikiSorter->sortLinks( $interwikiLinks );
			$parserOutput->setLanguageLinks( $sortedLinks );
		}

		return true;
	}

}
