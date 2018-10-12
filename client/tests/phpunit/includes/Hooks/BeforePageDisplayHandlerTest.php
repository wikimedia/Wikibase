<?php

namespace Wikibase\Client\Tests\Hooks;

use PHPUnit4And6Compat;
use RequestContext;
use Skin;
use SkinFallback;
use Title;
use User;
use Wikibase\Client\Hooks\BeforePageDisplayHandler;
use Wikibase\Client\NamespaceChecker;

/**
 * @covers \Wikibase\Client\Hooks\BeforePageDisplayHandler
 *
 * @group WikibaseClient
 * @group WikibaseHooks
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class BeforePageDisplayHandlerTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	/**
	 * @dataProvider wikibaseForNamespaceProvider
	 */
	public function testHandle_WikibaseForNamespace( $expectedJsModules, $expectedCssModules,
		$enabledForNamespace, $langLinks, $prefixedId, $loggedIn
	) {
		$skin = $this->getSkin( $loggedIn, true );
		$output = $this->getOutputPage( $skin, $langLinks, $prefixedId );

		$namespaceChecker = $this->getNamespaceChecker( $enabledForNamespace );

		$handler = new BeforePageDisplayHandler( $namespaceChecker );
		$handler->addModules( $output, 'view' );

		$this->assertEquals( $expectedJsModules, $output->getModules(), 'js modules' );
		$this->assertEquals( $expectedCssModules, $output->getModuleStyles(), 'css modules' );
	}

	public function wikibaseForNamespaceProvider() {
		return [
			[
				[],
				[],
				false, // wikibase not enabled for namespace
				[ 'de:Rom' ], // local site link
				null, // not connected item, no prefixed id
				true // user logged in
			],
			[
				[],
				[ 'wikibase.client.init' ],
				true, // wikibase enabled for namespace
				[ 'de:Rom' ],
				'Q4', // has connected item
				true // user logged in
			]
		];
	}

	/**
	 * @dataProvider pageConnectedToWikibaseProvider
	 */
	public function testHandlePageConnectedToWikibase( $expectedJsModules, $expectedCssModules,
		$enabledForNamespace, $langLinks, $prefixedId, $loggedIn
	) {
		$skin = $this->getSkin( $loggedIn, true );
		$output = $this->getOutputPage( $skin, $langLinks, $prefixedId );

		$namespaceChecker = $this->getNamespaceChecker( $enabledForNamespace );

		$handler = new BeforePageDisplayHandler( $namespaceChecker );
		$handler->addModules( $output, 'view' );

		$this->assertEquals( $expectedJsModules, $output->getModules(), 'js modules' );
		$this->assertEquals( $expectedCssModules, $output->getModuleStyles(), 'css modules' );
	}

	public function pageConnectedToWikibaseProvider() {
		return [
			[
				[],
				[ 'wikibase.client.init' ],
				true, // wikibase enabled for namespace
				[ 'de:Rom' ],
				'Q4', // has connected item
				true // user logged in
			]
		];
	}

	public function testHandlePageConnectedToWikibase_noexternallinklinks() {
		$skin = $this->getSkin( true, true ); // user logged in

		// page connected, has links and noexternallanglinks
		$output = $this->getOutputPage( $skin, [ 'de:Rom' ], 'Q4', [ '*' ] );
		$namespaceChecker = $this->getNamespaceChecker( true );

		$handler = new BeforePageDisplayHandler( $namespaceChecker );
		$handler->addModules( $output, 'view' );

		$this->assertEquals( [], $output->getModules(), 'js modules' );
		$this->assertEquals( [], $output->getModuleStyles(), 'css modules' );
	}

	/**
	 * @dataProvider pageNotConnectedToWikibaseProvider
	 */
	public function testHandlePageNotConnectedToWikibase( $expectedJsModules, $expectedCssModules,
		$enabledForNamespace, $langLinks, $prefixedId, $loggedIn
	) {
		$skin = $this->getSkin( $loggedIn, true );
		$output = $this->getOutputPage( $skin, $langLinks, $prefixedId );

		$namespaceChecker = $this->getNamespaceChecker( $enabledForNamespace );

		$handler = new BeforePageDisplayHandler( $namespaceChecker );
		$handler->addModules( $output, 'view' );

		$this->assertEquals( $expectedJsModules, $output->getModules(), 'js modules' );
		$this->assertEquals( $expectedCssModules, $output->getModuleStyles(), 'css modules' );
	}

	public function pageNotConnectedToWikibaseProvider() {
		return [
			[
				[ 'wikibase.client.linkitem.init' ],
				[ 'wikibase.client.init' ],
				true, // wikibase enabled for namespace
				[], // no lang links
				null, // no prefixed id
				true // user logged in
			]
		];
	}

	/**
	 * @dataProvider handleUserLoggedOutWithEmptyLangLinksProvider
	 */
	public function testHandleUserLoggedOutWithEmptyLangLinks( $expectedJsModules, $expectedCssModules,
		$enabledForNamespace, $langLinks, $prefixedId, $loggedIn
	) {
		$skin = $this->getSkin( $loggedIn, true );
		$output = $this->getOutputPage( $skin, $langLinks, $prefixedId );

		$namespaceChecker = $this->getNamespaceChecker( $enabledForNamespace );

		$handler = new BeforePageDisplayHandler( $namespaceChecker );
		$handler->addModules( $output, 'view' );

		$this->assertEquals( $expectedJsModules, $output->getModules(), 'js modules' );
		$this->assertEquals( $expectedCssModules, $output->getModuleStyles(), 'css modules' );
	}

	public function handleUserLoggedOutWithEmptyLangLinksProvider() {
		return [
			[
				[],
				[ 'wikibase.client.init' ],
				true, // wikibase enabled for namespace
				[], // no lang links
				null, // no prefixed id
				false // user logged out
			]
		];
	}

	/**
	 * @dataProvider handleTitleNotExist_NoWikibaseLinksProvider
	 */
	public function testHandleTitleNotExist_NoWikibaseLinks( $expectedJsModules,
		$expectedCssModules, $enabledForNamespace, $langLinks, $prefixedId, $loggedIn
	) {
		$skin = $this->getSkin( $loggedIn, false );
		$output = $this->getOutputPage( $skin, $langLinks, $prefixedId );

		$namespaceChecker = $this->getNamespaceChecker( $enabledForNamespace );

		$handler = new BeforePageDisplayHandler( $namespaceChecker );
		$handler->addModules( $output, 'view' );

		$this->assertEquals( $expectedJsModules, $output->getModules(), 'js modules' );
		$this->assertEquals( $expectedCssModules, $output->getModuleStyles(), 'css modules' );
	}

	public function handleTitleNotExist_NoWikibaseLinksProvider() {
		return [
			[
				[],
				[],
				true, // wikibase enabled for namespace
				[], // no lang links
				null, // no prefixed id
				true // user logged in
			]
		];
	}

	/**
	 * @dataProvider handleHistoryActionWithEmptyLangLinksProvider
	 */
	public function testHandle_HistoryActionWithEmptyLangLinks( $expectedJsModules,
		$expectedCssModules, $enabledForNamespace, $langLinks, $prefixedId, $loggedIn
	) {
		$skin = $this->getSkin( $loggedIn, true );
		$output = $this->getOutputPage( $skin, $langLinks, $prefixedId );

		$namespaceChecker = $this->getNamespaceChecker( $enabledForNamespace );

		$handler = new BeforePageDisplayHandler( $namespaceChecker );
		$handler->addModules( $output, 'history' );

		$this->assertEquals( $expectedJsModules, $output->getModules(), 'js modules' );
		$this->assertEquals( $expectedCssModules, $output->getModuleStyles(), 'css modules' );
	}

	public function handleHistoryActionWithEmptyLangLinksProvider() {
		return [
			[
				[],
				[],
				true, // wikibase enabled for namespace
				[], // no lang links
				null, // no prefixed id
				true // user logged in
			]
		];
	}

	private function getContext( $loggedIn, $titleExists ) {
		$context = new RequestContext();

		$title = $this->getTitle( $titleExists );
		$context->setTitle( $title );

		$user = $this->getUser( $loggedIn );
		$context->setUser( $user );

		return $context;
	}

	/**
	 * @param bool $titleExists
	 *
	 * @return Title
	 */
	private function getTitle( $titleExists ) {
		$title = $this->getMockBuilder( Title::class )
			->disableOriginalConstructor()
			->getMock();

		$title->expects( $this->any() )
			->method( 'exists' )
			->will( $this->returnValue( $titleExists ) );

		return $title;
	}

	/**
	 * @param bool $loggedIn
	 *
	 * @return User
	 */
	private function getUser( $loggedIn ) {
		$user = $this->getMockBuilder( User::class )
			->disableOriginalConstructor()
			->getMock();

		$user->expects( $this->any() )
			->method( 'isLoggedIn' )
			->will( $this->returnValue( $loggedIn ) );

		return $user;
	}

	/**
	 * @param bool $wikibaseEnabled
	 *
	 * @return NamespaceChecker
	 */
	private function getNamespaceChecker( $wikibaseEnabled ) {
		$namespaceChecker = $this->getMockBuilder( NamespaceChecker::class )
			->disableOriginalConstructor()
			->getMock();

		$namespaceChecker->expects( $this->any() )
			->method( 'isWikibaseEnabled' )
			->will( $this->returnValue( $wikibaseEnabled ) );

		return $namespaceChecker;
	}

	private function getOutputPage( Skin $skin, $langLinks, $prefixedId = null,
		$noexternallanglinks = null
	) {
		$output = $skin->getOutput();
		$output->setLanguageLinks( $langLinks );

		if ( $prefixedId ) {
			$output->setProperty( 'wikibase_item', $prefixedId );
		}

		if ( $noexternallanglinks ) {
			$output->setProperty( 'noexternallanglinks', $noexternallanglinks );
		}

		return $output;
	}

	private function getSkin( $loggedIn, $titleExists ) {
		$context = $this->getContext( $loggedIn, $titleExists );
		$skin = new SkinFallback();
		$skin->setContext( $context );

		return $skin;
	}

}
