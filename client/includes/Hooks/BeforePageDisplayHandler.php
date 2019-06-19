<?php

namespace Wikibase\Client\Hooks;

use OutputPage;
use Title;
use User;
use Wikibase\Client\NamespaceChecker;

/**
 * @license GPL-2.0-or-later
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class BeforePageDisplayHandler {

	/**
	 * @var NamespaceChecker
	 */
	private $namespaceChecker;

	/**
	 * @var bool
	 */
	private $dataBridgeEnabled;

	public function __construct( NamespaceChecker $namespaceChecker, $dataBridgeEnabled ) {
		$this->namespaceChecker = $namespaceChecker;
		$this->dataBridgeEnabled = $dataBridgeEnabled;
	}

	/**
	 * @param OutputPage $out
	 * @param string $actionName
	 */
	public function addModules( OutputPage $out, $actionName ) {
		$title = $out->getTitle();

		if ( !$title || !$this->namespaceChecker->isWikibaseEnabled( $title->getNamespace() ) ) {
			return;
		}

		$this->addStyleModules( $out, $title, $actionName );
		$this->addJsModules( $out, $title, $actionName );
	}

	private function addStyleModules( OutputPage $out, Title $title, $actionName ) {
		// styles are not appropriate for cologne blue and should leave styling up to other skins
		if ( $this->hasEditOrAddLinks( $out, $title, $actionName ) ) {
			$out->addModuleStyles( 'wikibase.client.init' );
		}
	}

	private function addJsModules( OutputPage $out, Title $title, $actionName ) {
		$user = $out->getUser();

		if ( $this->hasLinkItemWidget( $user, $out, $title, $actionName ) ) {
			// Add the JavaScript which lazy-loads the link item widget
			// (needed as jquery.wikibase.linkitem has pretty heavy dependencies)
			$out->addModules( 'wikibase.client.linkitem.init' );
		}

		if ( $this->dataBridgeEnabled ) {
			$out->addModules( 'wikibase.client.data-bridge.init' );
		}
	}

	private function hasEditOrAddLinks( OutputPage $out, Title $title, $actionName ) {
		if (
			!in_array( $actionName, [ 'view', 'submit' ] ) ||
			$this->allLinksAreSuppressed( $out ) ||
			!$title->exists()
		) {
			return false;
		}

		return true;
	}

	private function allLinksAreSuppressed( OutputPage $out ) {
		$noexternallanglinks = $out->getProperty( 'noexternallanglinks' );

		if ( $noexternallanglinks !== null ) {
			return in_array( '*', $noexternallanglinks );
		}

		return false;
	}

	private function hasLinkItemWidget( User $user, OutputPage $out, Title $title, $actionName ) {
		if (
			$out->getLanguageLinks() !== [] || !$user->isLoggedIn()
			|| !$this->hasEditOrAddLinks( $out, $title, $actionName )
		) {
			return false;
		}

		return true;
	}

}
