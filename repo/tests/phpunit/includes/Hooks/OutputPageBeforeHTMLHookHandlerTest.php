<?php

namespace Wikibase\Repo\Tests\Hooks;

use DerivativeContext;
use OutputPage;
use PHPUnit4And6Compat;
use RequestContext;
use Title;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\EntityFactory;
use Wikibase\Lib\Store\EntityRevision;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\Lib\StaticContentLanguages;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\UserLanguageLookup;
use Wikibase\Repo\Hooks\OutputPageBeforeHTMLHookHandler;
use Wikibase\Repo\Hooks\OutputPageEntityIdReader;
use Wikibase\View\Template\TemplateFactory;
use Wikibase\View\Termbox\TermboxView;

/**
 * @covers \Wikibase\Repo\Hooks\OutputPageBeforeHTMLHookHandler
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch < hoo@online.de >
 */
class OutputPageBeforeHTMLHookHandlerTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	/**
	 * @return OutputPage
	 */
	private function newOutputPage() {
		return new OutputPage( new DerivativeContext( RequestContext::getMain() ) );
	}

	/**
	 * @param string $uiLanguageCode
	 *
	 * @return OutputPageBeforeHTMLHookHandler
	 */
	private function getHookHandler( $uiLanguageCode ) {
		$userLanguageLookup = $this->getMock( UserLanguageLookup::class );
		$userLanguageLookup->expects( $this->once() )
			->method( 'getUserSpecifiedLanguages' )
			->will( $this->returnValue( [ 'de', 'es', 'ru' ] ) );
		$userLanguageLookup->expects( $this->once() )
			->method( 'getAllUserLanguages' )
			->will( $this->returnValue( array_unique( [ $uiLanguageCode, 'de', 'es', 'ru' ] ) ) );

		$languageNameLookup = $this->getMock( LanguageNameLookup::class );
		$languageNameLookup->expects( $this->never() )
			->method( 'getName' );

		$itemId = new ItemId( 'Q1' );

		$outputPageBeforeHTMLHookHandler = new OutputPageBeforeHTMLHookHandler(
			TemplateFactory::getDefaultInstance(),
			$userLanguageLookup,
			new StaticContentLanguages( [ 'en', 'es', 'ru' ] ),
			$this->getEntityRevisionLookupReturningEntity( $itemId ),
			$languageNameLookup,
			$this->getOutputPageEntityIdReaderReturningEntity( $itemId ),
			new EntityFactory( [] ),
			''
		);

		return $outputPageBeforeHTMLHookHandler;
	}

	/**
	 * Integration test mostly testing that things don't fatal/ throw.
	 */
	public function testOutputPageBeforeHTMLHookHandler() {
		$out = $this->newOutputPage();
		$outputPageBeforeHTMLHookHandler = $this->getHookHandler( $out->getLanguage()->getCode() );

		$html = '';
		$out->setTitle( Title::makeTitle( 0, 'OutputPageBeforeHTMLHookHandlerTest' ) );
		$out->setProperty(
			'wikibase-view-chunks',
			[ '$1' => [ 'entityViewPlaceholder-entitytermsview-entitytermsforlanguagelistview-class' ] ]
		);

		$outputPageBeforeHTMLHookHandler->doOutputPageBeforeHTML( $out, $html );

		// Verify the wbUserSpecifiedLanguages JS variable
		$jsConfigVars = $out->getJsConfigVars();
		$wbUserSpecifiedLanguages = $jsConfigVars['wbUserSpecifiedLanguages'];

		$this->assertSame( [ 'es', 'ru' ], $wbUserSpecifiedLanguages );
	}

	public function testGivenDeletedRevision_hookHandlerDoesNotFail() {
		$outputPageEntityIdReader = $this->getMockBuilder( OutputPageEntityIdReader::class )
			->disableOriginalConstructor()
			->getMock();
		$outputPageEntityIdReader->expects( $this->once() )
			->method( 'getEntityIdFromOutputPage' )
			->will( $this->returnValue( null ) );

		$handler = new OutputPageBeforeHTMLHookHandler(
			TemplateFactory::getDefaultInstance(),
			$this->newUserLanguageLookup(),
			new StaticContentLanguages( [] ),
			$this->getMock( EntityRevisionLookup::class ),
			$this->getMock( LanguageNameLookup::class ),
			$outputPageEntityIdReader,
			new EntityFactory( [] ),
			''
		);

		$out = $this->newOutputPage();
		$out->setProperty( 'wikibase-view-chunks', [ '$1' => [ 'termbox' ] ] );

		$html = '$1';
		$handler->doOutputPageBeforeHTML( $out, $html );
		$this->assertSame( '', $html );
	}

	public function testGivenExternallyRenderedMarkupBlob_usesRespectivePlaceholderExpander() {
		$entity = new ItemId( 'Q123' );
		$entityFactory = $this->createMock( EntityFactory::class );
		$entityFactory->expects( $this->once() )
			->method( 'newEmpty' )
			->willReturn( new Item( $entity ) );
		$handler = new OutputPageBeforeHTMLHookHandler(
			TemplateFactory::getDefaultInstance(),
			$this->newUserLanguageLookup(),
			new StaticContentLanguages( [] ),
			$this->createMock( EntityRevisionLookup::class ),
			$this->getMock( LanguageNameLookup::class ),
			$this->getOutputPageEntityIdReaderReturningEntity( $entity ),
			$entityFactory,
			''
		);

		$expectedHtml = '<div>termbox</div>';
		$placeholder = '$1';

		$out = $this->newOutputPage();
		$out->setProperty( TermboxView::TERMBOX_MARKUP_BLOB, $expectedHtml );
		$out->setProperty( 'wikibase-view-chunks', [ $placeholder => [ TermboxView::TERMBOX_PLACEHOLDER ] ] );

		$html = $placeholder;
		$handler->doOutputPageBeforeHTML( $out, $html );

		$this->assertSame( $expectedHtml, $html );
	}

	private function newUserLanguageLookup() {
		$userLanguageLookup = $this->getMock( UserLanguageLookup::class );
		$userLanguageLookup->expects( $this->any() )
			->method( 'getUserSpecifiedLanguages' )
			->will( $this->returnValue( [] ) );
		$userLanguageLookup->expects( $this->any() )
			->method( 'getAllUserLanguages' )
			->will( $this->returnValue( [] ) );
		return $userLanguageLookup;
	}

	/**
	 * @param $itemId
	 * @return \PHPUnit\Framework\MockObject\MockObject
	 */
	private function getOutputPageEntityIdReaderReturningEntity( $itemId ) {
		$outputPageEntityIdReader = $this->getMockBuilder( OutputPageEntityIdReader::class )
			->disableOriginalConstructor()
			->getMock();
		$outputPageEntityIdReader->expects( $this->once() )
			->method( 'getEntityIdFromOutputPage' )
			->will( $this->returnValue( $itemId ) );
		return $outputPageEntityIdReader;
	}

	/**
	 * @param $itemId
	 * @return \PHPUnit_Framework_MockObject_MockObject
	 */
	private function getEntityRevisionLookupReturningEntity( $itemId ) {
		$entityRevisionLookup = $this->getMock( EntityRevisionLookup::class );
		$entityRevisionLookup->expects( $this->once() )
			->method( 'getEntityRevision' )
			->will( $this->returnValue( new EntityRevision( new Item( $itemId ) ) ) );
		return $entityRevisionLookup;
	}

}
